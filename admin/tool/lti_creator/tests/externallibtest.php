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
 * External learning plans webservice API tests.
 *
 * @package tool_lti_creator
 * @copyright 2020 Adrian Greeve
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');


class tool_lti_creator_external_testcase extends externallib_advanced_testcase {

    public function test_mything() {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        $return = \tool_lti_creator\external::get_tool_instance('assign');
        print_object($return['url']);
        print_object($return['secret']);
    }
}