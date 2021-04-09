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
 * Handles LTI 1.3 resource link launches.
 *
 * See enrol/lti/launch_deeplink.php for deep linking launches.
 *
 * @package    enrol_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_lti\local\ltiadvantage\launch_cache_session;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\context_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use enrol_lti\local\ltiadvantage\service\tool_launch_service;
use enrol_lti\local\ltiadvantage\issuer_database;
use IMSGlobal\LTI13\LTI_Message_Launch;

require_once(__DIR__ . '/../../config.php');
global $PAGE;

// This is checked by the library. Just verify its existence here.
$id_token = required_param('id_token', PARAM_RAW);

$launch = LTI_Message_Launch::new(new issuer_database(), new launch_cache_session())
    ->validate();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/enrol/lti/launch.php'));
$PAGE->set_pagelayout('popup'); // Same layout as the tool.php page in Legacy 1.1/2.0 launches.
$PAGE->set_title(get_string('opentool', 'enrol_lti'));

if (!is_enabled_auth('lti')) {
    print_error('pluginnotenabled', 'auth', '', get_string('pluginname', 'auth_lti'));
}
if (!enrol_is_enabled('lti')) {
    print_error('enrolisdisabled', 'enrol_lti');
}

$toollauchservice = new tool_launch_service(
    new deployment_repository(),
    new application_registration_repository(),
    new resource_link_repository(),
    new user_repository(),
    new context_repository()
);
[$userid, $resource] = $toollauchservice->user_launches_tool($launch);

complete_user_login(\core_user::get_user($userid));

$context = context::instance_by_id($resource->contextid);
if ($context->contextlevel == CONTEXT_COURSE) {
    $courseid = $context->instanceid;
    $redirecturl = new moodle_url('/course/view.php', ['id' => $courseid]);
} else if ($context->contextlevel == CONTEXT_MODULE) {
    $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
    $redirecturl = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
} else {
    print_error('invalidcontext');
}

if (empty($CFG->allowframembedding)) {
    $stropentool = get_string('opentool', 'enrol_lti');
    echo html_writer::tag('p', get_string('frameembeddingnotenabled', 'enrol_lti'));
    echo html_writer::link($redirecturl, $stropentool, ['target' => '_blank']);
} else {
    redirect($redirecturl);
}
