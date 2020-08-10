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
 * Prints an instance of mod_tooltest.
 *
 * @package     mod_tooltest
 * @copyright   2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
global $DB, $OUTPUT, $USER, $PAGE;
use \IMSGlobal\LTI13;

$launchid = required_param('launchid', PARAM_TEXT);
$type = required_param('type', PARAM_ALPHANUM);
$toolid = required_param('toolid', PARAM_INT);

require_once($CFG->dirroot . '/enrol/lti/issuer_database.php');

$launch = LTI13\LTI_Message_Launch::from_cache($launchid, new issuer_database($toolid));

// TODO: Should probably verify that the toolid passed in matches the signed toolid in the launch jwt.

if (!$launch->is_deep_link_launch()) {
    throw new coding_exception("Not deep link launch");
}
global $CFG;
// TODO: this should redirect to a registered ODIC deep link redirect URL.
$resource = LTI13\LTI_Deep_Link_Resource::new()
//    ->set_url($CFG->wwwroot . '/mod/tooltest/view.php?id=92')
    ->set_url($CFG->wwwroot . '/enrol/lti/tool.php?id=19')
    ->set_custom_params(['type' =>  $type])
    ->set_title('Tooltest instance [difficulty: '.$type.']');

$dl = $launch->get_deep_link();

$dl->output_response_form([$resource]);
