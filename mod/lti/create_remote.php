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
 * Page which:
 * - calls the remote api asking for a tool instance to be created
 * - creates the local external_tool instance with the return data
 * - redirects to course home
 *
 * @package    mod_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once("../../config.php");
require_once($CFG->libdir . "/filelib.php");

$course = required_param('id', PARAM_INT);
$section = required_param('section', PARAM_INT);
require_login($course);

$providerurl = $CFG->lticreatorurl ?? ''; // URL of the provider site, e.g. 'your.moodle'
$token = $CFG->lticreatortoken ?? ''; // WS token for a user on the provider site.
if (empty($providerurl) || empty($token)) {
    throw new coding_exception('CFG->lticreatorurl and CFG->lticreatortoken must both be set.');
}
$wspath = 'webservice/rest/server.php';
$function = 'tool_lti_creator_get_tool_instance';

$PAGE->set_url('/mod/lti/create_remote.php');

// Call the provider site, asking for creation of a tool instance for consumption.
$wsendpoint = $providerurl.'/'.$wspath.'?wstoken='.$token.'&wsfunction='.$function.'&moodlewsrestformat=json';
$curl = new curl();
$encodedresponse = $curl->get($wsendpoint, ['modulename' => 'assign']);
$response = json_decode($encodedresponse);

// Now, create the external_tool instance locally in the course we're in.
$data = (object) [
    'modulename' => 'lti',
    'course' => $course,
    'section' => $section,
    'visible' => true,
    'introeditor' => ['text' => 'sometext', 'format' => 1, 'itemid' => 1],
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
    'markingallocation' => 0,
    'toolurl' => $response->url,
    'password' => $response->secret,
    'resourcekey' => 'clientkey123'
];
$lti = create_module($data);

redirect($CFG->wwwroot . '/course/view.php?id='.$course);
