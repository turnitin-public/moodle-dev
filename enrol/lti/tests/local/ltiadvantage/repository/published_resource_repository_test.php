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

namespace enrol_lti\local\ltiadvantage\repository;

/**
 * Tests for published_resource_repository objects.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class published_resource_repository_test extends \advanced_testcase {
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
        $mod = $generator->create_module('assign', ['course' => $course->id]);
        $mod2 = $generator->create_module('resource', ['course' => $course2->id]);
        $mod3 = $generator->create_module('assign', ['course' => $course->id, 'grade' => 0]);
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
        $module3resourcedata = (object) [
            'courseid' => $course->id,
            'cmid' => $mod3->cmid,
            'membersyncmode' => 1,
            'membersync' => 1,
            'ltiversion' => 'LTI-1p3'
        ];
        $tool = $generator->create_lti_tool($courseresourcedata);
        $tool2 = $generator->create_lti_tool($moduleresourcedata);
        $tool3 = $generator->create_lti_tool($module2resourcedata);
        $tool4 = $generator->create_lti_tool($module3resourcedata);
        return [$user, $user2, $user3, $course, $course2, $mod, $mod2, $mod3, $tool, $tool2, $tool3, $tool4];
    }

    /**
     * Test finding published resources for a given user.
     */
    public function test_find_all_for_user() {
        $this->resetAfterTest();
        [$user, $user2, $user3, $course, $course2, $mod, $mod2, $mod3] = $this->generate_published_resources();

        $resourcerepo = new published_resource_repository();

        $resources = $resourcerepo->find_all_for_user($user->id);
        $this->assertCount(3, $resources);
        usort($resources, function($a, $b) {
            return strcmp($a->get_contextid(), $b->get_contextid());
        });
        $this->assertEquals($resources[0]->get_contextid(), \context_course::instance($course->id)->id);
        $this->assertEquals($resources[1]->get_contextid(), \context_module::instance($mod->cmid)->id);
        $this->assertEquals($resources[2]->get_contextid(), \context_module::instance($mod3->cmid)->id);
        $this->assertTrue($resources[0]->supports_grades());
        $this->assertTrue($resources[1]->supports_grades());
        $this->assertFalse($resources[2]->supports_grades());

        $resources = $resourcerepo->find_all_for_user($user2->id);
        $this->assertCount(1, $resources);
        $this->assertEquals($resources[0]->get_contextid(), \context_module::instance($mod2->cmid)->id);
        $this->assertFalse($resources[0]->supports_grades());

        $this->assertEmpty($resourcerepo->find_all_for_user($user3->id));
    }

    /**
     * Test finding a subset of published resources, by id, for a user.
     */
    public function test_find_all_by_ids_for_user() {
        $this->resetAfterTest();
        [$user, $user2, $user3, $course, $course2, $mod, $mod2, $mod3, $tool, $tool2, $tool3, $tool4] =
            $this->generate_published_resources();

        $resourcerepo = new published_resource_repository();

        $resources = $resourcerepo->find_all_by_ids_for_user([$tool2->id, $tool3->id, $tool4->id], $user->id);
        $this->assertCount(2, $resources);
        usort($resources, function($a, $b) {
            return strcmp($a->get_contextid(), $b->get_contextid());
        });
        $this->assertEquals($resources[0]->get_contextid(), \context_module::instance($mod->cmid)->id);
        $this->assertEquals($resources[1]->get_contextid(), \context_module::instance($mod3->cmid)->id);
        $this->assertTrue($resources[0]->supports_grades());
        $this->assertFalse($resources[1]->supports_grades());

        $resources = $resourcerepo->find_all_by_ids_for_user([$tool2->id, $tool3->id, $tool4->id], $user2->id);
        $this->assertCount(1, $resources);
        $this->assertEquals($resources[0]->get_contextid(), \context_module::instance($mod2->cmid)->id);
        $this->assertFalse($resources[0]->supports_grades());

        $this->assertEmpty($resourcerepo->find_all_by_ids_for_user([$tool2->id], $user2->id));
        $this->assertEmpty($resourcerepo->find_all_by_ids_for_user([], $user2->id));
    }
}
