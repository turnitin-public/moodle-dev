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
 * Contains tests for the published_resource_repository class.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\local\ltiadvantage\repository;

/**
 * Tests for published_resource_repository objects.
 *
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class published_resource_repository_testcase extends \advanced_testcase {
    /**
     * Setup run for each test case.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Get a list of published resources for testing.
     *
     * @return array a list of relevant test data; users courses and mods.
     */
    protected function generate_published_resources() {
        // Create a course and publish it.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $course2 = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'editingteacher');
        $user2 = $generator->create_and_enrol($course2, 'editingteacher');
        $user3 = $generator->create_user();
        $this->setAdminUser();
        $mod = $generator->create_module('resource', ['course' => $course->id]);
        $mod2 = $generator->create_module('resource', ['course' => $course2->id]);
        $courseresourcedata = (object) [
            'courseid' => $course->id,
            'membersyncmode' => 0,
            'membersync' => 0,
            'ltiversion' => 'LTI-1p3'
        ];
        $moduleresourcedata = (object) [
            'courseid' => $course->id,
            'cmid' => $mod->cmid,
            'membersyncmode' => 1,
            'membersync' => 1,
            'ltiversion' => 'LTI-1p3'
        ];
        $module2resourcedata = (object) [
            'courseid' => $course2->id,
            'cmid' => $mod2->cmid,
            'membersyncmode' => 1,
            'membersync' => 1,
            'ltiversion' => 'LTI-1p3'
        ];
        $generator->create_lti_tool($courseresourcedata);
        $generator->create_lti_tool($moduleresourcedata);
        $generator->create_lti_tool($module2resourcedata);
        return [$user, $user2, $user3, $course, $course2, $mod, $mod2];
    }

    /**
     * Test finding published resources for a given user.
     */
    public function test_find_all_for_user() {
        [$user, $user2, $user3, $course, $course2, $mod, $mod2] = $this->generate_published_resources();

        $resourcerepo = new published_resource_repository();

        $resources = $resourcerepo->find_all_for_user($user->id);
        $this->assertCount(2, $resources);
        usort($resources, function($a, $b) {
            return $a->get_contextid() > $b->get_contextid();
        });
        $this->assertEquals($resources[0]->get_contextid(), \context_course::instance($course->id)->id);
        $this->assertEquals($resources[1]->get_contextid(), \context_module::instance($mod->cmid)->id);

        $resources = $resourcerepo->find_all_for_user($user2->id);
        $this->assertCount(1, $resources);
        $this->assertEquals($resources[0]->get_contextid(), \context_module::instance($mod2->cmid)->id);

        $this->assertEmpty($resourcerepo->find_all_for_user($user3->id));
    }
}
