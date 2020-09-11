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
 * Handles LIT 1.3 deep linking launches.
 *
 * @package    enrol_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use \IMSGlobal\LTI13;
use enrol_lti\local\ltiadvantage\issuer_database;

require_once(__DIR__ . '/../../config.php');

global $CFG, $DB, $OUTPUT, $PAGE;

$id_token = optional_param('id_token', null, PARAM_RAW);
$launchid = optional_param('launchid', null, PARAM_RAW);

// Launchid will ONLY be set in cases when the user must log in to the tool. The wantsurl is used to redirect back to
// this page, with the launchid, at which point the launch can be retrieved from the session (account association can
// be made if desired at this time too) and the content selection process can continue.
if ($launchid) {
    $launch = LTI13\LTI_Message_Launch::from_cache($launchid, new issuer_database());
}

// Get the launch data in cases where the platform has posted the id_token and state params here as part of a launch.
if ($id_token) {
    $launch = LTI13\LTI_Message_Launch::new(new issuer_database())
        ->validate();
}

$PAGE->set_context(context_system::instance());
$url = new moodle_url('/enrol/lti/launch_deeplink.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('opentool', 'enrol_lti'));

if (empty($launch)) {
    // TODO: lang strings.
    print_error();
    exit();
}

// Check if the authentication plugin is disabled.
if (!is_enabled_auth('lti')) {
    print_error('pluginnotenabled', 'auth', '', get_string('pluginname', 'auth_lti'));
    exit();
}

// Check if the enrolment plugin is disabled.
if (!enrol_is_enabled('lti')) {
    print_error('enrolisdisabled', 'enrol_lti');
    exit();
}

// Note: we don't check if any enrolment instance is disabled, because we're not sure which one applies until content
// has been selected.

// If the user is not signed in to the tool, ask them to do so, coming back here after successful login.
$PAGE->set_url('/enrol/lti/launch_deeplink.php?launchid='.urlencode($launch->get_launch_id()));
require_login();



// comment out the above and uncomment this to let the page load without requiring a deep link launch.
/*$launchid= 1;
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/enrol/lti/launch_deeplink.php?launchid='.$launchid);
*/

// Render the list of courses having published content.
// The user will be able to expand a course to select published modules but the expansion will be an ajax action.
[$insql, $inparams] = $DB->get_in_or_equal(['LTI-1p3']);
$sql = "SELECT DISTINCT c.id, c.fullname
          FROM {enrol_lti_tools} elt
          JOIN {enrol} e
            ON (e.id = elt.enrolid)
          JOIN {course} c
            ON (c.id = e.courseid)
         WHERE elt.ltiversion $insql";
$courses = $DB->get_records_sql($sql, $inparams);

$formattedcourses = array_map(function($course) {
    $course->fullname = format_string($course->fullname);
    return $course;
}, $courses);

usort($formattedcourses, function($a, $b) {
   return $a->fullname > $b->fullname;
});

$context = [
    'action' => $CFG->wwwroot . '/enrol/lti/configure.php',
    'launchid' => $launch->get_launch_id(),
    'hascontent' => !empty($formattedcourses),
    'sesskey' => sesskey(),
    'courses' => $formattedcourses
];

// TODO: eventually, only one course worth of modules will be rendered on load so this will need to be changed.
//  The others will be lazy loaded via treegrid interaction.
// For now, grab the published modules for each course, adding the module name to the template context.
[$insql, $inparams] = $DB->get_in_or_equal(['LTI-1p3']);
$sql = "SELECT elt.id AS enrol_lti_id, e.id AS enrol_id, e.name AS name, e.courseid AS courseid, elt.contextid
          FROM {enrol} e
          JOIN {enrol_lti_tools} elt
            ON (e.id = elt.enrolid)
         WHERE elt.ltiversion $insql
      ORDER BY courseid";
$contentitems = $DB->get_records_sql($sql, $inparams);

foreach ($contentitems as $contentitem) {
    foreach ($context['courses'] as $index => $course) {
        if ($course->id == $contentitem->courseid) {
            $mods = get_fast_modinfo($contentitem->courseid)->get_cms();
            foreach ($mods as $mod) {
                if ($mod->context->id == $contentitem->contextid) {
                    $context['courses'][$index]->modules[] = [
                        'name' => $mod->name,
                        'id' => $contentitem->enrol_lti_id
                    ];
                }
            }
        }
    }
}

$renderer = $PAGE->get_renderer('core');
echo $OUTPUT->header();
echo $renderer->render_from_template('enrol_lti/content_select', $context);
echo $OUTPUT->footer();
