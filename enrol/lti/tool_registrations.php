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
 * List all registered platforms for the given tool.
 *
 * @package    enrol_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use enrol_lti\form\platform_registration_form;

require_once(__DIR__ . '/../../config.php');
global $CFG, $OUTPUT, $PAGE, $DB;
require_once($CFG->dirroot . '/enrol/lti/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$toolid = required_param('toolid', PARAM_INT);
$action = optional_param('action', null, PARAM_ALPHA);

$context = context_course::instance($courseid);

require_login($courseid);
require_capability('moodle/course:enrolreview', $context);

// TODO make sure to confirm sesskey where necessary.

// Gets all platform registrations for the current tool.
function get_tool_platforms(int $toolid) {
    global $DB;
    return $DB->get_records('enrol_lti_platform_registry', ['toolid' => $toolid]);
}

// Returns the template-ready context based on the platforms we have to show.
function format(array $platforms, int $toolid, int $courseid) {
    $data = [
        'registrations' => [],
        'addurl' => (new moodle_url('/enrol/lti/tool_registrations.php', ['action' => 'add', 'toolid' => $toolid,
            'courseid' => $courseid, 'id' => 2]))->out(false),
        'cancelurl' => (new moodle_url('/enrol/lti/index.php', ['courseid' => $courseid]))->out(false)
    ];

    foreach ($platforms as $id => $record) {
        $data['registrations'][] = [
            'id' => $record->id,
            'platformid' => $record->platformid,
            'clientid' => $record->clientid,
            'authenticationrequesturl' => $record->authenticationrequesturl,
            'jwksurl' => $record->jwksurl,
            'accesstokenurl' => $record->accesstokenurl,
            'editurl' => (new moodle_url('/enrol/lti/tool_registrations.php', ['action' => 'edit', 'toolid' => $toolid,
                'courseid' => $courseid, 'id' => $id]))->out(false),
            'deleteurl' => (new moodle_url('/enrol/lti/tool_registrations.php', ['action' => 'delete',
                'toolid' => $toolid, 'courseid' => $courseid, 'id' => $id]))->out(false)
        ];
    }

    return $data;
}

// Create the tool/platform-regn specific private key.
function create_private_key() {
    // Create the private key.
    $kid = bin2hex(openssl_random_pseudo_bytes(10));
    $config = array(
        "digest_alg" => "sha256",
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    );
    $res = openssl_pkey_new($config);
    openssl_pkey_export($res, $privatekey);

    // TODO: also needs handling in form validation.
    if (empty($privatekey)) {
        throw new coding_exception('problem with openssl config, cant create private key');
    }

    return [$kid, $privatekey];
}

// Handle create/edit.
if ($action === 'add') {

    $action = (new moodle_url('/enrol/lti/tool_registrations.php', ['toolid' => $toolid, 'courseid' => $courseid, 'action' => 'add']))
        ->out(false);
    $mform = new platform_registration_form($action);

    if ($data = $mform->get_data()) {
        // Handle submit.

        // Generate a private key for this platform registration. A contract per platform.
        [$kid, $privatekey] = create_private_key();
        $data->privatekey = $privatekey;
        $data->kid = $kid;

        $DB->insert_record('enrol_lti_platform_registry', $data);
    } else if (!$mform->is_cancelled()) {
        // Show the form.
        $PAGE->set_pagelayout('admin');
        $pageurl = new moodle_url('/enrol/lti/tool_registrations.php', ['toolid' => $toolid, 'courseid' => $courseid, 'action' => 'add']);
        $PAGE->set_url($pageurl);

        echo $OUTPUT->header();
        echo $OUTPUT->heading("Add platform registration for tool");
        $mform->set_data(['toolid' => $toolid]);
        $mform->display();
        echo $OUTPUT->footer();
        die();
    }

    // Go back to the list page when done.
    // TODO notification when creating a new item.
    redirect(new moodle_url('/enrol/lti/tool_registrations.php', ['toolid' => $toolid, 'courseid' => $courseid]));
} else if ($action === 'edit') {

    $id = optional_param('id', null, PARAM_INT);

    $action = (new moodle_url('/enrol/lti/tool_registrations.php', ['toolid' => $toolid, 'courseid' => $courseid,
        'id' => $id, 'action' => 'edit']))->out(false);
    $mform = new platform_registration_form($action);

    if ($data = $mform->get_data()) {
        require_sesskey();
        $DB->update_record('enrol_lti_platform_registry', $data);
    } else if (!$mform->is_cancelled()) {

        $data = $DB->get_record('enrol_lti_platform_registry', ['id' => $id]);

        $mform->set_data($data);

        $PAGE->set_pagelayout('admin');
        $pageurl = new moodle_url('/enrol/lti/tool_registrations.php', ['toolid' => $toolid, 'courseid' => $courseid,
            'id' => $id, 'action' => 'edit']);
        $PAGE->set_url($pageurl);

        echo $OUTPUT->header();
        echo $OUTPUT->heading("Edit platform registration for tool");
        $mform->display();
        echo $OUTPUT->footer();
        die();
    }
    redirect(new moodle_url('/enrol/lti/tool_registrations.php', ['courseid' => $courseid, 'toolid' => $toolid]));

} else if ($action === 'delete') {
    $id = optional_param('id', null, PARAM_INT);
    if (!optional_param('confirm', false, PARAM_BOOL)) {
        $continueparams = ['action' => 'delete', 'toolid' => $toolid, 'courseid' => $courseid, 'id' => $id,
            'sesskey' => sesskey(), 'confirm' => true];
        $continueurl = new moodle_url('/enrol/lti/tool_registrations.php', $continueparams);
        $cancelurl = new moodle_url('/enrol/lti/tool_registrations.php', ['toolid' => $toolid, 'courseid' => $courseid]);

        $PAGE->set_pagelayout('admin');
        $pageurl = new moodle_url('/enrol/lti/tool_registrations.php', ['toolid' => $toolid, 'courseid' => $courseid, 'action' => 'delete']);
        $PAGE->set_url($pageurl);

        echo $OUTPUT->header();

        $reg = $DB->get_record('enrol_lti_platform_registry', ['id' => $id]);

        echo $OUTPUT->confirm("Are you sure you want to delete the platform registration for the platform '".$reg->platformid."'?", $continueurl, $cancelurl);
        echo $OUTPUT->footer();
    } else {
        require_sesskey();
        $DB->delete_records('enrol_lti_platform_registry', ['id' => $id]);
        //redirect($PAGE->url, get_string('issuerdeleted', 'tool_oauth2'), null, \core\output\notification::NOTIFY_SUCCESS);
        redirect(new moodle_url('/enrol/lti/tool_registrations.php', ['toolid' => $toolid, 'courseid' => $courseid]),
            "Platform registration deleted", null,  \core\output\notification::NOTIFY_SUCCESS);
    }
} else {

    // List the registered platforms.
    $pageurl = new moodle_url('/enrol/lti/tool_registrations.php', ['toolid' => $toolid, 'courseid' => $courseid]);
    $PAGE->set_url($pageurl);
    $tool = \enrol_lti\helper::get_lti_tool($toolid);

    $PAGE->set_title($tool->name. ": registered platforms");
    $PAGE->set_heading($tool->name . ": registered platforms");
    $PAGE->set_pagelayout('admin');

    $renderer = $PAGE->get_renderer('core');
    $templatecontext = format(get_tool_platforms($toolid), $toolid, $courseid);

    echo $OUTPUT->header();
    echo $OUTPUT->heading("Platforms");

    echo $renderer->render_from_template('enrol_lti/platform_registry', $templatecontext);
    echo $OUTPUT->footer();
}


