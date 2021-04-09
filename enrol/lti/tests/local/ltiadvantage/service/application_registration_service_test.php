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
 * Contains tests for the application_registration_service.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\local\ltiadvantage\service;

defined('MOODLE_INTERNAL') || die();

use enrol_lti\helper;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\context_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;

require_once(__DIR__ . '/../lti_advantage_testcase.php');

/**
 * Tests for the application_registration_service.
 *
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class application_registration_service_testcase extends \lti_advantage_testcase {

    /**
     * Setup run for each test case.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test the use case "As an admin, I can register an application as an LTI consumer (platform)".
     */
    public function test_register_application() {
        $reg = (object) [
            'name' => 'Example LMS application',
            'platformid' => 'https://lms.example.org',
            'clientid' => '123',
            'authenticationrequesturl' => new \moodle_url('https://example.org/authrequesturl'),
            'jwksurl' => new \moodle_url('https://example.org/jwksurl'),
            'accesstokenurl' => new \moodle_url('https://example.org/accesstokenurl')
        ];

        $regrepo = new application_registration_repository();
        $service = new application_registration_service($regrepo, new deployment_repository(),
            new resource_link_repository(), new context_repository(), new user_repository());
        $createdreg = $service->create_application_registration($reg);

        $this->assertTrue($regrepo->exists($createdreg->get_id()));
    }

    /**
     * Test the use case "As an admin, I can update an application registered as an LTI consumer (platform)".
     */
    public function test_update_application_registration() {
        $reg = (object) [
            'name' => 'Example LMS application',
            'platformid' => 'https://lms.example.org',
            'clientid' => '123',
            'authenticationrequesturl' => new \moodle_url('https://example.org/authrequesturl'),
            'jwksurl' => new \moodle_url('https://example.org/jwksurl'),
            'accesstokenurl' => new \moodle_url('https://example.org/accesstokenurl')
        ];

        $regrepo = new application_registration_repository();
        $service = new application_registration_service($regrepo, new deployment_repository(),
            new resource_link_repository(), new context_repository(), new user_repository());
        $createdreg = $service->create_application_registration($reg);

        $reg->id = $createdreg->get_id();
        $reg->jwksurl = new \moodle_url('https://example.org/updated_jwksurl');

        $updatedreg = $service->update_application_registration($reg);
        $this->assertEquals($reg->name, $updatedreg->get_name());
        $this->assertEquals($reg->jwksurl, $updatedreg->get_jwksurl());
    }

    /**
     * Test verifying that the service requires an object id.
     */
    public function test_update_application_registration_missing_id() {
        $reg = (object) [
            'name' => 'Example LMS application',
            'platformid' => 'https://lms.example.org',
            'clientid' => '123',
            'authenticationrequesturl' => new \moodle_url('https://example.org/authrequesturl'),
            'jwksurl' => new \moodle_url('https://example.org/jwksurl'),
            'accesstokenurl' => new \moodle_url('https://example.org/accesstokenurl')
        ];

        $regrepo = new application_registration_repository();
        $service = new application_registration_service($regrepo, new deployment_repository(),
            new resource_link_repository(), new context_repository(), new user_repository());
        $service->create_application_registration($reg);

        $reg->jwksurl = new \moodle_url('https://example.org/updated_jwksurl');

        $this->expectException(\coding_exception::class);
        $service->update_application_registration($reg);
    }

    /**
     * Test that removing an application registration also removes all associated data.
     */
    public function test_delete_application_registration() {
        // Setup.
        $registrationrepo = new application_registration_repository();
        $deploymentrepo = new deployment_repository();
        $contextrepo = new context_repository();
        $resourcelinkrepo = new resource_link_repository();
        $userrepo = new user_repository();
        [$course, $resource] = $this->create_test_environment();
        $this->fake_user_launch($resource, $this->create_mock_platform_user());

        // Check all the expected data exists for the deployment after setup.
        $registrations = $registrationrepo->find_all();
        $this->assertCount(1, $registrations);
        $registration = array_pop($registrations);

        $deployments = $deploymentrepo->find_all_by_registration($registration->get_id());
        $this->assertCount(1, $deployments);
        $deployment = array_pop($deployments);

        $resourcelinks = $resourcelinkrepo->find_by_resource($resource->id);
        $this->assertCount(1, $resourcelinks);
        $resourcelink = array_pop($resourcelinks);

        $context = $contextrepo->find($resourcelink->get_contextid());
        $this->assertNotNull($context);

        $users = $userrepo->find_by_resource($resource->id);
        $this->assertCount(1, $users);
        $user = array_pop($users);

        $enrolledusers = get_enrolled_users(\context_course::instance($course->id));
        $this->assertCount(1, $enrolledusers);

        // Now delete the application_registration using the service.
        $service = new application_registration_service(
            new application_registration_repository(),
            $deploymentrepo,
            $resourcelinkrepo,
            $contextrepo,
            $userrepo
        );
        $service->delete_application_registration($registration->get_id());

        // Verify that the context, resourcelink, user, deployment and registration instances are all deleted.
        $this->assertFalse($registrationrepo->exists($registration->get_id()));
        $this->assertFalse($deploymentrepo->exists($deployment->get_id()));
        $this->assertFalse($contextrepo->exists($context->get_id()));
        $this->assertFalse($resourcelinkrepo->exists($resourcelink->get_id()));
        $this->assertFalse($userrepo->exists($user->get_id()));

        // Verify that all users are unenrolled.
        $enrolledusers = get_enrolled_users(\context_course::instance($course->id));
        $this->assertCount(0, $enrolledusers);

        // Verify the tool record stays in place (I.e. the published resource is still available).
        $this->assertNotEmpty(helper::get_lti_tool($resource->id));
    }
}
