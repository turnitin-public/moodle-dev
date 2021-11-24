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
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_lti\local\ltiadvantage\launch_cache_session;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\context_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\legacy_consumer_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use enrol_lti\local\ltiadvantage\service\tool_launch_service;
use enrol_lti\local\ltiadvantage\issuer_database;
use IMSGlobal\LTI13\LTI_Message_Launch;

require_once(__DIR__ . '/../../config.php');

$idtoken = optional_param('id_token', null, PARAM_RAW);
$launchid = optional_param('launchid', null, PARAM_RAW);

if (!is_enabled_auth('lti')) {
    throw new moodle_exception('pluginnotenabled', 'auth', '', get_string('pluginname', 'auth_lti'));
}
if (!enrol_is_enabled('lti')) {
    throw new moodle_exception('enrolisdisabled', 'enrol_lti');
}
if (empty($idtoken) && empty($launchid)) {
    throw new coding_exception('Error: launch requires id_token');
}

// Support caching the launch and retrieving it after the account binding process described in auth::complete_login().
$sessionlaunchcache = new launch_cache_session();
$issuerdb = new issuer_database(new application_registration_repository(), new deployment_repository());
if ($idtoken) {
    $launch = LTI_Message_Launch::new($issuerdb, $sessionlaunchcache)
        ->validate();
}
if ($launchid) {
    $launch = LTI_Message_Launch::from_cache($launchid, $issuerdb, $sessionlaunchcache);
}
if (empty($launch)) {
    throw new moodle_exception('Bad launch. Deep linking launch data could not be found');
}

// Authenticate the platform user, which could be an instructor, an admin or a learner.
// Auth code needs to be told about consumer secrets for the purposes of migration, since these reside in enrol_lti.
$launchdata = $launch->get_launch_data();
if (!empty($launchdata['https://purl.imsglobal.org/spec/lti/claim/lti1p1']['oauth_consumer_key'])) {
    $legacyconsumerrepo = new legacy_consumer_repository();
    $legacyconsumersecrets = $legacyconsumerrepo->get_consumer_secrets(
        $launchdata['https://purl.imsglobal.org/spec/lti/claim/lti1p1']['oauth_consumer_key']
    );
}
$auth = get_auth_plugin('lti');
$auth->complete_login(
    $launch->get_launch_data(),
    new moodle_url('/enrol/lti/launch.php', ['launchid' => $launch->get_launch_id()]),
    $legacyconsumersecrets ?? []
);

require_login(null, false);
global $USER, $CFG, $PAGE;
// Service code will now do the work of creating domain objects, making updates to the user record, etc.
// TODO: move this to service code. -------
// Do the following only if the user is auth='lti' i.e. auto provisioned.
// If the user was preexisting, we don't want to change anything.
// - update user->firstname
// - update user->lastname
// - update user->email
// - update user profile image.
if ($USER->auth == 'lti') {
    $userupdate = (object) [
        'id' => $USER->id,
        'firstname' => $launchdata['given_name'] ?? $launchdata['sub'],
        'lastname' => $launchdata['family_name'] ?? $launchdata['iss'],
        'email' => !empty($launchdata['email']) ? $launchdata['email'] :
            'enrol_lti_13_' . sha1($launchdata['iss'] . '_' . $launchdata['sub']) . '@example.com'
    ];
    require_once($CFG->dirroot . '/user/lib.php');
    user_update_user($userupdate);

    if (!empty($launchdata['picture'])) {
        \enrol_lti\helper::update_user_profile_image($USER->id, $launchdata['picture']);
    }
}
// TODO: end move -------

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/enrol/lti/launch.php'));
$PAGE->set_pagelayout('popup'); // Same layout as the tool.php page in Legacy 1.1/2.0 launches.
$PAGE->set_title(get_string('opentool', 'enrol_lti'));

$toollauchservice = new tool_launch_service(
    new deployment_repository(),
    new application_registration_repository(),
    new resource_link_repository(),
    new user_repository(),
    new context_repository()
);
[$userid, $resource] = $toollauchservice->user_launches_tool($USER, $launch);

//complete_user_login(\core_user::get_user($userid));

$context = context::instance_by_id($resource->contextid);
if ($context->contextlevel == CONTEXT_COURSE) {
    $courseid = $context->instanceid;
    $redirecturl = new moodle_url('/course/view.php', ['id' => $courseid]);
} else if ($context->contextlevel == CONTEXT_MODULE) {
    $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
    $redirecturl = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
} else {
    throw new moodle_exception('invalidcontext');
}

if (empty($CFG->allowframembedding)) {
    $stropentool = get_string('opentool', 'enrol_lti');
    echo html_writer::tag('p', get_string('frameembeddingnotenabled', 'enrol_lti'));
    echo html_writer::link($redirecturl, $stropentool, ['target' => '_blank']);
} else {
    redirect($redirecturl);
}
