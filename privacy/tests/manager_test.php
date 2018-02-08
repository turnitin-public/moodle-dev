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
 * Privacy manager unit tests.
 *
 * @package     core_privacy
 * @copyright   2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;

/**
 * Privacy manager unit tests.
 *
 * @package     core_privacy
 * @copyright   2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class privacy_manager_testcase extends advanced_testcase {

    public function test_component_is_compliant() {
        // TODO: Once core implements required classes, add tests for:
        // - Those relying on only metadata\null_provider.
        // - Those implementing all 3 (metadata\provider, request\plugin_provider and request\plugin_deleter).
        // - Those implementing none of the privacy interfaces.

        // Check that the compliance check find the null_provider and passes.
        $this->assertFalse(core_privacy\manager::component_is_compliant('mod_thismodnamewontexist'));
    }
}
