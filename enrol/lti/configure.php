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

use enrol_lti\local\ltiadvantage\launch_cache_session;
use enrol_lti\local\ltiadvantage\issuer_database;
use enrol_lti\local\ltiadvantage\repository\published_resource_repository;
use IMSGlobal\LTI13\LTI_Deep_Link_Resource;
use IMSGlobal\LTI13\LTI_Lineitem;
use IMSGlobal\LTI13\LTI_Message_Launch;

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
global $CFG, $DB, $PAGE, $USER;
require_login(null, false);

confirm_sesskey();
$launchid = required_param('launchid', PARAM_TEXT);
$modules = optional_param_array('modules', [], PARAM_INT);
$grades = optional_param_array('grades', [], PARAM_INT);

$sessionlaunchcache = new launch_cache_session();
$launch = LTI_Message_Launch::from_cache($launchid, new issuer_database(), $sessionlaunchcache);
if (!$launch->is_deep_link_launch()) {
    throw new coding_exception('Configuration can only be accessed as part of a content item selection deep link '.
        'launch.');
}
$sessionlaunchcache->purge();

// Get the selected resources and create the resource link content items to post back.
$resourcerepo = new published_resource_repository();
$resources = $resourcerepo->find_all_by_ids_for_user($modules, $USER->id);

$contentitems = [];
foreach ($resources as $resource) {

    $contentitem = LTI_Deep_Link_Resource::new()
        ->set_url($CFG->wwwroot . '/enrol/lti/launch.php')
        ->set_custom_params(['id' => $resource->get_uuid()])
        ->set_title($resource->get_name());

    // If the activity supports grading, and the user has selected it, then include line item information.
    if ($resource->supports_grades() && in_array($resource->get_id(), $grades)) {
        require_once($CFG->libdir . '/gradelib.php');

        $lineitem = LTI_Lineitem::new()
            ->set_score_maximum($resource->get_grademax())
            ->set_resource_id($resource->get_uuid())
            ->set_tag('testing'); // TODO: remove tag if possible or set sensible default.

        $contentitem->set_lineitem($lineitem);
    }

    $contentitems[] = $contentitem;
}

$dl = $launch->get_deep_link();
$dl->output_response_form($contentitems);

