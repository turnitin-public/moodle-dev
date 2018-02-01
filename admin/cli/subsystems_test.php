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
 * CLI script to purge caches without asking for confirmation.
 *
 * @package    core
 * @subpackage cli
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');

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




// Testing the privacy manager class.

//core_privacy\manager::get_contexts_for_userid(10);


if (core_privacy\manager::component_is_compliant('mod_assign')) {
    echo "Is compliant\n";
} else {
    echo "Is not compliant\n";
}

// Testing the get_metadata call.
core_privacy\manager::get_metadata_for_components();

