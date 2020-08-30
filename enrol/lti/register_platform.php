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
 * LTI 1.3 page to create or edit a platform registration.
 *
 * This page is only used by LTI 1.3. Older versions do not require platforms to be registered with the tool during
 * registration.
 *
 * @package    enrol_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use enrol_lti\form\platform_registration_form;

require_once(__DIR__ . '/../../config.php');
global $CFG, $OUTPUT, $PAGE, $DB;
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/enrol/lti/lib.php');

$action = required_param('action', PARAM_ALPHA);
if (!in_array($action,['add', 'edit', 'delete'])) {
    throw new coding_exception("Invalid action param '$action'");
}

// The page to go back to when the respective action has been performed.
$toolregistrationurl = new moodle_url($CFG->wwwroot . "/" . $CFG->admin . "/settings.php",
    ['section' => 'enrolsettingslti_registrations']);

/**
 * Local helper to extend the nav for this page and call admin_externalpage_setup.
 */
function page_setup(string $pagetitle) {
    global $PAGE;
    navigation_node::override_active_url(
        new moodle_url('/admin/settings.php', ['section' => 'enrolsettingslti_registrations'])
    );
    admin_externalpage_setup('enrolsettingslti_registrations_edit', '', null, '', ['pagelayout' => 'admin']);
    $PAGE->navbar->add($pagetitle);
}

if ($action === 'add') {
    // TODO: Lang strings.
    page_setup("Register a platform");

    $pageurl = new moodle_url('/enrol/lti/register_platform.php', ['action' => 'add']);
    $mform = new platform_registration_form($pageurl->out(false));
    if ($data = $mform->get_data()) {
        $DB->insert_record('enrol_lti_platform_registry', $data);
        redirect($toolregistrationurl, "Platform registration created", null,  \core\output\notification::NOTIFY_SUCCESS);
    } else if (!$mform->is_cancelled()) {

        echo $OUTPUT->header();
        // TODO: lang string.
        echo $OUTPUT->heading("Register a platform");
        $mform->display();
        echo $OUTPUT->footer();
        die();
    }
    redirect($toolregistrationurl);

} else if ($action === 'edit') {
    $regid = required_param('regid', PARAM_INT);
    // TODO lang strings.
    page_setup("Edit platform registration");


    $pageurl = new moodle_url('/enrol/lti/register_platform.php', ['action' => 'edit', 'regid' => $regid]);
    $mform = new platform_registration_form($pageurl->out(false));
    if (($data = $mform->get_data()) && confirm_sesskey()) {
        $DB->update_record('enrol_lti_platform_registry', $data);
        redirect($toolregistrationurl, "Platform registration updated", null,  \core\output\notification::NOTIFY_SUCCESS);
    } else if (!$mform->is_cancelled()) {
        $data = $DB->get_record('enrol_lti_platform_registry', ['id' => $regid]);
        $mform->set_data($data);

        echo $OUTPUT->header();
        // TODO lang strings.
        echo $OUTPUT->heading("Edit platform registration");
        $mform->display();
        echo $OUTPUT->footer();
        die();
    }
    redirect($toolregistrationurl);

} else if ($action === 'delete') {
    $regid = required_param('regid', PARAM_INT);
    // TODO lang strings.
    page_setup("Delete platform registration");

    if (!optional_param('confirm', false, PARAM_BOOL)) {
        $continueparams = ['action' => 'delete', 'regid' => $regid, 'sesskey' => sesskey(), 'confirm' => true];
        $continueurl = new moodle_url('/enrol/lti/register_platform.php', $continueparams);
        $reg = $DB->get_record('enrol_lti_platform_registry', ['id' => $regid]);

        echo $OUTPUT->header();
        // TODO: lang strings.
        echo $OUTPUT->confirm(
            "Are you sure you want to delete the platform registration for the platform '".$reg->platformid."'?",
            $continueurl,
            $toolregistrationurl
        );
        echo $OUTPUT->footer();
    } else {
        require_sesskey();
        $DB->delete_records('enrol_lti_platform_registry', ['id' => $regid]);

        // TODO: Lang strings.
        redirect($toolregistrationurl,
            "Platform registration deleted", null,  \core\output\notification::NOTIFY_SUCCESS);
    }
}
