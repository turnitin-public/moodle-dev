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
 * Returns the deep link resource via a POST to the platform.
 *
 * @package     enrol_lti
 * @copyright   2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \IMSGlobal\LTI13;
use enrol_lti\local\ltiadvantage\issuer_database;

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
global $CFG, $DB;
require_login(null, false);

$launchid = required_param('launchid', PARAM_TEXT);
$enrolid = required_param('enrolid', PARAM_INT);
$launch = LTI13\LTI_Message_Launch::from_cache($launchid, new issuer_database());

// TODO: If we were to use a non-session cache for launch data (e.g. a file which shared by all users) we'd want to
//  check that the launchid is the same launchid as in the user session. We don't want to be able to affect other users
//  launch data.

if (!$launch->is_deep_link_launch()) {
    throw new coding_exception('Configuration can only be accessed as part of a content selection deep link launch.');
}

// Get the name of the course or module to which the enrolment method provides access.
$sql = 'SELECT courseid, contextid
          FROM {enrol} e
          JOIN {enrol_lti_tools} et ON (e.id = et.enrolid)
         WHERE et.id = :enrolid';
$instance = $DB->get_record_sql($sql, ['enrolid' => $enrolid]);

$modinfo = get_fast_modinfo($instance->courseid);
$coursecontext = context_course::instance($instance->courseid);
if ($coursecontext->id == $instance->contextid) {
    $name = ($modinfo->get_course())->shortname;
} else {
    $mods = $modinfo->get_cms();
    foreach ($mods as $mod) {
        if ($mod->context->id == $instance->contextid) {
            $name = $mod->name;
        }
    }
}

$resource = LTI13\LTI_Deep_Link_Resource::new()
    ->set_url($CFG->wwwroot . '/enrol/lti/launch.php')
    ->set_custom_params(['id' => $enrolid])
    ->set_title($name);

$dl = $launch->get_deep_link();

$dl->output_response_form([$resource]);
