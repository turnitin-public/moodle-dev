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
 * Quick dirty place to put functions.
 *
 * @package     tool_lti_creator
 * @copyright   2020 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lti_creator;

defined('MOODLE_INTERNAL') || die();

// I named this API.php as a joke / homage to the bad practice of putting everything into one massive class.
class api {

    public function get_course() {
        global $DB;
        // Get the first available course.
        $records = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID]);
        return array_shift($records);
    }

    public function create_assignment_activity($courseid, $section) {
        // We just need a call to each module to get these sensible default required values.
        $moduleinfo = (object) [
            'modulename' => 'assign',
            'name' => 'My new assignment',
            'course' => $courseid,
            'section' => $section,
            'visible' => true,
            'introeditor' => ['text' => 'Sometext', 'format' => 1, 'itemid' => 1],
            'submissiondrafts' => 1,
            'requiresubmissionstatement' => 0,
            'sendnotifications' => 0,
            'sendlatenotifications' => 0,
            'duedate' => 0,
            'cutoffdate' => 0,
            'gradingduedate' => 0,
            'allowsubmissionsfromdate' => 0,
            'grade' => 0,
            'teamsubmission' => 0,
            'requireallteammemberssubmit' => 0,
            'blindmarking' => 0,
            'markingworkflow' => 0,
            'markingallocation' => 0
        ];

        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        return \create_module($moduleinfo);
    }

    public function create_lti_enrolment_instance(\stdClass $course, $modulecontext) {
        $ltienroldata = [
            'name' => 'Automatic LTI enrolment',
            'contextid' => $modulecontext->id, // course or module context?
            'enrolperiod' => 0,
            'enrolstartdate' => 0,
            'enrolenddate' => 0,
            'maxenrolled' => 0,
            'roleinstructor' =>  3,
            'rolelearner' => 5,
            'secret' => random_string(32),
            'gradesync' => 1,
            'gradesynccompletion' =>0,
            'membersync' => 1,
            'membersyncmode' => 1,
            'maildisplay' => 2,
            'city' => 'Perth',
            'country' => 'AU',
            'timezone' => 99,
            'lang' => 'en',
            'institution' => 'moodle',
            'id' => 0,
            'courseid' => $course->id,
            'type' => 'lti',
            'returnurl' => new \moodle_url('enrol/instances.php', ['id' => $course->id])
        ];
        $plugin = \enrol_get_plugin('lti');
        $instanceid = $plugin->add_instance($course, $ltienroldata);
        return $instanceid;
    }

}
