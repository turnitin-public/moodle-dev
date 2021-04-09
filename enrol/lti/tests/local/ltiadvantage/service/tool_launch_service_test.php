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
 * Contains tests for the tool_launch_service.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\local\ltiadvantage\service;

defined('MOODLE_INTERNAL') || die();

use core_availability\info_module;
use enrol_lti\local\ltiadvantage\entity\resource_link;
use enrol_lti\local\ltiadvantage\entity\user;
use enrol_lti\local\ltiadvantage\entity\context;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\context_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;

require_once(__DIR__ . '/../lti_advantage_testcase.php');

/**
 * Tests for the tool_launch_service.
 *
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_launch_service_testcase extends \lti_advantage_testcase {

    /**
     * Setup run for each test case.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test the use case "A user launches the tool so they can view an external resource".
     */
    public function test_user_launches_tool() {
        // Setup.
        $contextrepo = new context_repository();
        $resourcelinkrepo = new resource_link_repository();
        $userrepo = new user_repository();
        [
            $course,
            $modresource,
            $modresource2,
            $courseresource,
            $registration,
            $deployment
        ] = $this->create_test_environment();

        // Call the service.
        $launchservice = new tool_launch_service(
            new deployment_repository(),
            new application_registration_repository(),
            $resourcelinkrepo,
            $userrepo,
            $contextrepo
        );
        $mockuser = $this->create_mock_platform_user();
        $mocklaunch = $this->get_mock_launch($modresource, $mockuser);
        [$userid, $resource] = $launchservice->user_launches_tool($mocklaunch, $modresource);

        // As part of the launch, we expect to now have an lti-enrolled user who is recorded against the deployment.
        $users = $userrepo->find_by_resource($resource->id);
        $this->assertCount(1, $users);
        $user = array_pop($users);
        $this->assertInstanceOf(user::class, $user);
        $this->assertEquals($deployment->get_id(), $user->get_deploymentid());

        // The user comes from a resource_link, details of which should also be saved and linked to the deployment.
        $resourcelinks = $resourcelinkrepo->find_by_resource_and_user($resource->id, $user->get_id());
        $this->assertCount(1, $resourcelinks);
        $resourcelink = array_pop($resourcelinks);
        $this->assertInstanceOf(resource_link::class, $resourcelink);
        $this->assertEquals($deployment->get_id(), $resourcelink->get_deploymentid());

        // The resourcelink should have a context, which should also be saved and linked to the deployment.
        $context = $contextrepo->find($resourcelink->get_contextid());
        $this->assertInstanceOf(context::class, $context);
        $this->assertEquals($deployment->get_id(), $context->get_deploymentid());

        $enrolledusers = get_enrolled_users(\context_course::instance($course->id));
        $this->assertCount(1, $enrolledusers);

        // Verify the module is visible to the user.
        $cmcontext = \context::instance_by_id($modresource->contextid);
        $this->assertTrue(info_module::is_user_visible($cmcontext->instanceid, $userid));

        // And that other published modules are not yet visible to the user.
        $cmcontext = \context::instance_by_id($modresource2->contextid);
        $this->assertFalse(info_module::is_user_visible($cmcontext->instanceid, $userid));
    }
}
