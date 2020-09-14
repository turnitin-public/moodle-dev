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
 * Handles LTI 1.3 launches.
 *
 * See launch_deeplink.php for deep linking launches.
 *
 * @package    enrol_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_lti\helper;
use \IMSGlobal\LTI13;
use enrol_lti\local\ltiadvantage\issuer_database;

require_once(__DIR__ . '/../../config.php');

global $CFG, $DB, $OUTPUT, $PAGE;

$id_token = required_param('id_token', PARAM_RAW);

// Get the 1.3 launch data and the enrol_lti_tools record.
$launch = LTI13\LTI_Message_Launch::new(new issuer_database())
    ->validate();
$data = $launch->get_launch_data();

// Only permit launches when an enrol id is provided.
$enrolid = $data['https://purl.imsglobal.org/spec/lti/claim/custom']['id'] ?? null;
if (!$enrolid) {
    // TODO lang strings + not coding_exception.
    throw new coding_exception('Invalid tool launch. The id was missing.');
}
$toolrecord = helper::get_lti_tool($enrolid);

$PAGE->set_context(context_system::instance());
$url = new moodle_url('/enrol/lti/launch.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('opentool', 'enrol_lti'));

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

// Check if the enrolment instance is disabled.
if ($toolrecord->status != ENROL_INSTANCE_ENABLED) {
    print_error('enrolisdisabled', 'enrol_lti');
    exit();
}

// Check enrolment, auth and load the content.
$context = context::instance_by_id($toolrecord->contextid);

// TODO: this is where the LTI 1.1 code does all the enrolment checks, etc, making sure the user is enrolled and signed in.
$messagelaunch = new \enrol_lti\local\ltiadvantage\message_launch($launch, $toolrecord);
$messagelaunch->launch();



// TODO In 1.1, this code lives in tool_provider->onLaunch(), but we need a 1.3 suitable location for this.
if ($context->contextlevel == CONTEXT_COURSE) {
    $courseid = $context->instanceid;
    $redirecturl = new moodle_url('/course/view.php', ['id' => $courseid]);
} else if ($context->contextlevel == CONTEXT_MODULE) {
    $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
    $redirecturl = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
} else {
    print_error('invalidcontext');
    exit();
}

redirect($redirecturl);
