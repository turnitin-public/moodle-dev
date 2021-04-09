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
 * Handles LTI 1.3 deep linking launches.
 *
 * There are 2 pathways through this page:
 * 1. When first making a deep linking launch from the platform. The launch data is cached at this point, pending user
 * authentication, and the page is set such that the post-authentication redirect will return here.
 * 2. The post-authentication redirect. The launch data is fetched from the session launch cache, and the resource
 * selection view is rendered.
 *
 * @package    enrol_lti
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_lti\local\ltiadvantage\launch_cache_session;
use enrol_lti\local\ltiadvantage\repository\published_resource_repository;
use enrol_lti\local\ltiadvantage\issuer_database;
use IMSGlobal\LTI13\LTI_Message_Launch;

require_once(__DIR__ . '/../../config.php');
global $CFG, $DB, $OUTPUT, $PAGE;

$id_token = optional_param('id_token', null, PARAM_RAW);
$launchid = optional_param('launchid', null, PARAM_RAW);

// First launch from the platform: get launch data and cache it in case the user's not authenticated.
$sessionlaunchcache = new launch_cache_session();
if ($id_token) {
    $launch = LTI_Message_Launch::new(new issuer_database(), $sessionlaunchcache)
        ->validate();
    $PAGE->set_url('/enrol/lti/launch_deeplink.php?launchid='.urlencode($launch->get_launch_id()));
}

// Redirect after authentication: Fetch launch data from the session launch cache.
if ($launchid) {
    $launch = LTI_Message_Launch::from_cache($launchid, new issuer_database(), $sessionlaunchcache);
    if (empty($launch)) {
        throw new coding_exception('Bad launchid. Deep linking launch data could not be found');
    }
}

require_login(null, false);

$PAGE->set_context(context_system::instance());
$url = new moodle_url('/enrol/lti/launch_deeplink.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('opentool', 'enrol_lti'));

if (!is_enabled_auth('lti')) {
    print_error('pluginnotenabled', 'auth', '', get_string('pluginname', 'auth_lti'));
}
if (!enrol_is_enabled('lti')) {
    print_error('enrolisdisabled', 'enrol_lti');
}

// Get all the published_resource view objects and render them for selection.
global $USER;
$resourcerepo = new published_resource_repository();
$resources = $resourcerepo->find_all_for_user($USER->id);
$renderer = $PAGE->get_renderer('enrol_lti');

echo $OUTPUT->header();
echo $renderer->render_published_resource_selection_view($launch, $resources);
echo $OUTPUT->footer();
