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
 * LTI 1.3 admin page listing the tool endpoints as well as those platforms registered with the tool.
 *
 * LTI 1.3 only. This page presents information used in tool registration using LTI 1.3 and the OAuth2/OIDC flows.
 *
 * @package    enrol_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
global $CFG, $OUTPUT, $PAGE, $DB;

require_admin();

$action = optional_param('action', null, PARAM_ALPHA);

// TODO: Probably need some admin capability here to view the tool details.
//require_capability('moodle/course:enrolreview', $context);

$tooldetails = [
    'urls' => [
        [
            'name' => 'Tool URL',
            'url' => 'http://example.com/example.php',
            'id' => uniqid()
        ],
        [
            'name' => 'OIDC Initiate Login URL',
            'url' => 'http://example.com/example.php',
            'id' => uniqid()
        ],
        [
            'name' => 'JWKS URL',
            'url' => 'http://example.com/example.php',
            'id' => uniqid()
        ],
        [
            'name' => 'Redirection URL',
            'url' => 'http://example.com/example.php',
            'id' => uniqid()
        ],
        [
            'name' => 'Deep Linking URL',
            'url' => 'http://example.com/example.php',
            'id' => uniqid()
        ],
    ],
];

// List the registered platforms.
$PAGE->set_context(context_system::instance());
$pageurl = new moodle_url('/enrol/lti/tool_details.php');
$PAGE->set_url($pageurl);

$PAGE->set_title("Tool details");
$PAGE->set_heading("Tool details");
$PAGE->set_pagelayout('admin');

$renderer = $PAGE->get_renderer('core');

echo $OUTPUT->header();
echo $OUTPUT->heading("Tool endpoints");

echo $renderer->render_from_template('enrol_lti/tool_details', $tooldetails);
echo $OUTPUT->footer();



