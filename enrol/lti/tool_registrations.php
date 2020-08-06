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

require_once(__DIR__ . '/../../config.php');
global $CFG, $OUTPUT, $PAGE, $DB;
require_once($CFG->dirroot . '/enrol/lti/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$toolid = required_param('toolid', PARAM_INT);
$context = context_course::instance($courseid);

require_login($courseid);
require_capability('moodle/course:enrolreview', $context);

// Gets all platform registrations for the current tool.
function get_tool_platforms(int $toolid) {
    global $DB;
    $regs = $DB->get_records('enrol_lti_platform_registry');
}

// Returns the template-ready context based on the platforms we have to show.
function format($platforms) {
    // TODO format real input data and remove below junk.

    return [
        'registrations' => [
            [
                'platformid' => 'http://test.com',
                'clientid' => '1234abcd',
            ],
            [
                'platformid' => 'http://example.org',
                'clientid' => 'qwetyy',
            ]
        ]
    ];
}


$pageurl = new moodle_url('/enrol/lti/tool_registrations.php', ['toolid' => $toolid, 'courseid' => $courseid]);
$PAGE->set_url($pageurl);
$PAGE->set_title("Tool registered platforms");
$PAGE->set_heading("Registered platforms");
$PAGE->set_pagelayout('admin');

$renderer = $PAGE->get_renderer('core');
$templatecontext = format(get_tool_platforms($toolid));

echo $OUTPUT->header();
echo $OUTPUT->heading("Platforms");

echo $renderer->render_from_template('enrol_lti/platform_registry', $templatecontext);
echo $OUTPUT->footer();


