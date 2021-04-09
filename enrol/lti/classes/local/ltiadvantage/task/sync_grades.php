<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Contains an LTI Advantage-specific task responsible for pushing grades to tool platforms.
 *
 * @package    enrol_lti
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\local\ltiadvantage\task;
use core\task\scheduled_task;
use enrol_lti\helper;
use enrol_lti\local\ltiadvantage\issuer_database;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use IMSGlobal\LTI13\LTI_Assignments_Grades_Service;
use IMSGlobal\LTI13\LTI_Grade;
use IMSGlobal\LTI13\LTI_Service_Connector;

/**
 * LTI Advantage task responsible for pushing grades to tool platforms.
 *
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_grades extends scheduled_task {

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksyncgrades', 'enrol_lti');
    }

    /**
     * Sync grades to the platform using the Assignment and Grade Services.
     *
     * @param \stdClass $resource the enrol_lti_tools data record for the shared resource.
     * @return array an array containing the
     */
    protected function sync_grades_for_resource($resource): array {
        $usercount = 0;
        $sendcount = 0;
        $userrepo = new user_repository();
        $resourcelinkrepo = new resource_link_repository();
        $appregistrationrepo = new application_registration_repository();
        $issuerdb = new issuer_database();

        if ($users = $userrepo->find_by_resource($resource->id)) {
            $completion = new \completion_info(get_course($resource->courseid));
            foreach ($users as $user) {
                $mtracecontent = "for the user '{$user->get_localid()}', for the resource '$resource->id' and the course " .
                    "'$resource->courseid'";
                $usercount++;

                // Check if we do not have a grade service endpoint in either of the resource links.
                // Remember, not all launches need to support grade services.
                $userresourcelinks = $resourcelinkrepo->find_by_resource_and_user($resource->id, $user->get_id());
                foreach ($userresourcelinks as $userresourcelink) {
                    if (!$gradeservice = $userresourcelink->get_grade_service()) {
                        mtrace("Skipping - No grade service found $mtracecontent.");
                        continue;

                    }

                    if (!$context = \context::instance_by_id($resource->contextid, IGNORE_MISSING)) {
                        mtrace("Failed - Invalid contextid '$resource->contextid' for the resource '$resource->id'.");
                        continue;
                    }

                    $grade = false;
                    $dategraded = false;
                    if ($context->contextlevel == CONTEXT_COURSE) {
                        if ($resource->gradesynccompletion && !$completion->is_course_complete($user->get_localid())) {
                            mtrace("Skipping - Course not completed $mtracecontent.");
                            continue;
                        }

                        // Get the grade.
                        if ($grade = grade_get_course_grade($user->get_localid(), $resource->courseid)) {
                            $grademax = floatval($grade->item->grademax);
                            $dategraded = $grade->dategraded;
                            $grade = $grade->grade;
                        }
                    } else if ($context->contextlevel == CONTEXT_MODULE) {
                        $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);

                        if ($resource->gradesynccompletion) {
                            $data = $completion->get_data($cm, false, $user->get_localid());
                            if ($data->completionstate != COMPLETION_COMPLETE_PASS &&
                                $data->completionstate != COMPLETION_COMPLETE) {
                                mtrace("Skipping - Activity not completed $mtracecontent.");
                                continue;
                            }
                        }

                        $grades = grade_get_grades($cm->course, 'mod', $cm->modname, $cm->instance,
                            $user->get_localid());
                        if (!empty($grades->items[0]->grades)) {
                            $grade = reset($grades->items[0]->grades);
                            if (!empty($grade->item)) {
                                $grademax = floatval($grade->item->grademax);
                            } else {
                                $grademax = floatval($grades->items[0]->grademax);
                            }
                            $dategraded = $grade->dategraded;
                            $grade = $grade->grade;
                        }
                    }

                    if ($grade === false || $grade === null || strlen($grade) < 1) {
                        mtrace("Skipping - Invalid grade $mtracecontent.");
                        continue;
                    }

                    if (empty($grademax)) {
                        mtrace("Skipping - Invalid grademax $mtracecontent.");
                        continue;
                    }

                    if (!grade_floats_different($grade, $user->get_lastgrade())) {
                        mtrace("Not sent - The grade $mtracecontent was not sent as the grades are the same.");
                        continue;
                    }
                    $floatgrade = $grade / $grademax;

                    try {
                        // Get a service worker for the corresponding application registration.
                        $appregistration = $appregistrationrepo->find_by_deployment(
                            $userresourcelink->get_deploymentid()
                        );
                        $registration = $issuerdb->find_registration_by_issuer($appregistration->get_platformid());
                        $sc = new LTI_Service_Connector($registration);

                        $lineitemurl = $gradeservice->get_lineitemurl();
                        $servicedata = [
                            'lineitems' => $gradeservice->get_lineitemsurl()->out(false),
                            'lineitem' => $lineitemurl ? $lineitemurl->out(false) : null,
                            'scope' => $gradeservice->get_scopes(),
                        ];

                        $ags = new LTI_Assignments_Grades_Service($sc, $servicedata);
                        $ltigrade = LTI_Grade::new()
                            ->set_score_given($grade)
                            ->set_score_maximum($grademax)
                            ->set_user_id($user->get_sourceid())
                            ->set_timestamp(date(\DateTime::ISO8601, $dategraded))
                            ->set_activity_progress('Completed')
                            ->set_grading_progress('FullyGraded');

                        // Don't specify the line item.
                        // The default line item will be used or a line item will be created.
                        $response = $ags->put_grade($ltigrade);
                    } catch (\Exception $e) {
                        mtrace("Failed - The grade '$floatgrade' $mtracecontent failed to send.");
                        mtrace($e->getMessage());
                        continue;
                    }

                    $httpheader = $response['headers'][0];
                    $responsecode = explode(' ', $httpheader)[1];
                    if ($responsecode == '200') {
                        $user->set_lastgrade(grade_floatval($grade));
                        $user = $userrepo->save($user);
                        mtrace("Success - The grade '$floatgrade' $mtracecontent was sent.");
                        $sendcount = $sendcount + 1;
                    } else {
                        mtrace("Failed - The grade '$floatgrade' $mtracecontent failed to send.");
                        mtrace("Header: $httpheader");
                    }
                }
            }
        }
        return [$usercount, $sendcount];
    }

    /**
     * Performs the synchronisation of grades from the tool to any registered platforms.
     *
     * @return bool|void
     */
    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/lib/completionlib.php');
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/querylib.php');

        if (!is_enabled_auth('lti')) {
            mtrace('Skipping task - ' . get_string('pluginnotenabled', 'auth', get_string('pluginname', 'auth_lti')));
            return true;
        }
        if (!enrol_is_enabled('lti')) {
            mtrace('Skipping task - ' . get_string('enrolisdisabled', 'enrol_lti'));
            return true;
        }

        foreach (helper::get_lti_tools(['status' => ENROL_INSTANCE_ENABLED, 'gradesync' => 1]) as $resource) {
            if ($resource->ltiversion != 'LTI-1p3') {
                continue;
            }
            mtrace("Starting - LTI Advantage grade sync for shared resource '$resource->id' in course '$resource->courseid'.");
            [$usercount, $sendcount] = $this->sync_grades_for_resource($resource);

            mtrace("Completed - Synced grades for tool '$resource->id' in the course '$resource->courseid'. " .
                "Processed $usercount users; sent $sendcount grades.");
            mtrace("");
        }
    }
}
