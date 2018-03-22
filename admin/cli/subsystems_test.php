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

define('CLI_SCRIPT', true);
require(__DIR__.'/../../config.php');

$manager = core_plugin_manager::instance();
$pluginslist = $manager::standard_plugins_list(null);
$types = $manager->get_plugin_types();
$subsystems = core_component::get_core_subsystems();

/*foreach($types as $key => $val) {
    $parts = preg_split('/moodle\//', $val);
    //echo $parts[count($parts)-1] . PHP_EOL;
}
*/
foreach($subsystems as $key => $val) {
    $parts = preg_split('/moodle\//', $val);
    //echo $key . ": ".$parts[count($parts)-1] . PHP_EOL;
}


$userid = 3;

// Testing the privacy manager class.
$manager = new core_privacy\manager();

// Testing get_metadata_for_components().
$metadata = $manager->get_metadata_for_components();
print_r($metadata);
die;

// Testing get_contexts_for_userid().
echo "Getting contexts for userid $userid\n";
$contextlistcollection = $manager->get_contexts_for_userid($userid);
$contextlists = $contextlistcollection->get_contextlists();
//print_r($contextlists);

// Convert to approved context list.
$approvedcontextlistcollection = new \core_privacy\local\request\contextlist_collection($userid);
foreach ($contextlistcollection->get_contextlists() as $contextlist) {
    $user = core_user::get_user($userid);
    $approvedcontextlist = new \core_privacy\local\request\approved_contextlist($user, $contextlist->get_component(),
        $contextlist->get_contextids());
    $approvedcontextlistcollection->add_contextlist($approvedcontextlist);
}
print_r($approvedcontextlistcollection);
$exportedcontent = $manager->export_user_data($approvedcontextlistcollection);

echo "\n";
echo "== File was successfully exported to {$exportedcontent}\n";

$basedir = make_temp_directory('privacy');
$exportpath = make_unique_writable_directory($basedir, true);
$fp = get_file_packer();
$fp->extract_to_pathname($exportedcontent, $exportpath);

echo "== File export was uncompressed to {$exportpath}\n";
echo "============================================================================\n";
exec("dolphin $exportpath");

/*if ($manager->component_is_compliant('mod_assign')) {
    echo "Is compliant\n";
} else {
    echo "Is not compliant\n";
}*/
// Testing the get_metadata call.
//core_privacy\manager::get_metadata_for_components();

