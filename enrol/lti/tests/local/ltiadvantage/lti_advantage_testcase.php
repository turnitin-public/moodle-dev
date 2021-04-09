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
 * Contains a parent class used in LTI Advantage testing.
 *
 * @package    enrol_lti
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use enrol_lti\helper;
use enrol_lti\local\ltiadvantage\entity\application_registration;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\context_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use enrol_lti\local\ltiadvantage\service\tool_launch_service;
use IMSGlobal\LTI13\LTI_Message_Launch;

/**
 * Parent class for LTI Advantage tests, providing environment setup and mock user launches.
 *
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class lti_advantage_testcase extends \advanced_testcase {

    /**
     * Create the minimal user data to perform a mock launch.
     *
     * @return string[] the user data.
     */
    protected function create_mock_platform_user() {
        return [
            'user_id' => 'user-123',
            'given_name' => 'John',
            'family_name' => 'Smith',
            'email' => 'john.smith@lms.example.org'
        ];
    }

    /**
     * Get a mock LTI_Message_Launch object, as if a user had launched from a resource link in the platform.
     * @param \stdClass $resource the resource record, allowing the mock to generate a link to this.
     * @param array $mockuser the user on the platform who is performing the launch.
     * @return LTI_Message_Launch the mock launch object with test launch data.
     */
    protected function get_mock_launch(\stdClass $resource, array $mockuser): LTI_Message_Launch {
        $mocklaunch = $this->getMockBuilder(LTI_Message_Launch::class)
            ->onlyMethods(['get_launch_data'])
            ->disableOriginalConstructor()
            ->getMock();
        $mocklaunch->expects($this->any())
            ->method('get_launch_data')
            ->will($this->returnCallback(function() use ($resource, $mockuser) {
                // This simulates the data in the jwt['body'] of a real resource link launch.
                // Real launches would of course have this data and authenticity of the user verified.
                return [
                    'iss' => 'https://lms.example.org', // Must match registration in create_test_environment.
                    'aud' => '123', // Must match registration in create_test_environment.
                    'sub' => $mockuser['user_id'], // User id on the platform site.
                    'https://purl.imsglobal.org/spec/lti/claim/deployment_id' => '1', // Must match registration.
                    'https://purl.imsglobal.org/spec/lti/claim/roles' => [
                        'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor'
                    ],
                    'https://purl.imsglobal.org/spec/lti/claim/resource_link' => [
                        'title' => 'Name of resource link in platform',
                        'id' => '12345', // Arbitrary, will be mapped to the user during resource link launch.
                    ],
                    "https://purl.imsglobal.org/spec/lti/claim/context" => [
                        "id" => "context-id-12345",
                        "label" => "ITS 123",
                        "title" => "ITS 123 Machine Learning",
                        "type" => ["http://purl.imsglobal.org/vocab/lis/v2/course#CourseOffering"]
                    ],
                    'https://purl.imsglobal.org/spec/lti/claim/target_link_uri' =>
                        'https://this-moodle-tool.example.org/context/24/resource/14',
                    'https://purl.imsglobal.org/spec/lti/claim/custom' => [
                        'id' => $resource->uuid,
                        'force_embed' => true
                    ],
                    'given_name' => $mockuser['given_name'],
                    'family_name' => $mockuser['family_name'],
                    'email' => $mockuser['email'],
                    'https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice' => [
                        'context_memberships_url' => 'https://lms.example.org/context/24/memberships',
                        'service_versions' => ['2.0']
                    ]
                ];
            }));

        return $mocklaunch;
    }

    /**
     * Sets up and returns a test course, including LTI-published resources, ready for testing.
     *
     * @param bool $enableauthplugin whether to enable the auth plugin during setup.
     * @param bool $enableenrolplugin whether to enable the enrol plugin during setup.
     * @param bool $membersync whether or not the published resource support membership sync with the platform.
     * @param int $membersyncmode the mode of member sync to set up on the shared resource.
     * @return array array of objects for use in individual tests; courses, tools.
     */
    protected function create_test_environment(bool $enableauthplugin = true, bool $enableenrolplugin = true,
            bool $membersync = true, int $membersyncmode = helper::MEMBER_SYNC_ENROL_AND_UNENROL) {

        if ($enableauthplugin) {
            $this->enable_auth();
        }
        if ($enableenrolplugin) {
            $this->enable_enrol();
        }

        // Set up the registration and deployment.
        $reg = application_registration::create(
            'Example LMS application',
            'https://lms.example.org',
            '123',
            new \moodle_url('https://example.org/authrequesturl'),
            new \moodle_url('https://example.org/jwksurl'),
            new \moodle_url('https://example.org/accesstokenurl')
        );
        $regrepo = new application_registration_repository();
        $reg = $regrepo->save($reg);
        $deployment = $reg->add_tool_deployment('My tool deployment', '1');
        $deploymentrepo = new deployment_repository();
        $deployment = $deploymentrepo->save($deployment);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        // Create a module and publish it.
        $mod = $generator->create_module('assign', ['course' => $course->id]);
        $tooldata = [
            'cmid' => $mod->cmid,
            'courseid' => $course->id,
            'membersyncmode' => $membersyncmode,
            'membersync' => $membersync,
            'ltiversion' => 'LTI-1p3'
        ];
        $tool = $generator->create_lti_tool((object)$tooldata);
        $tool = helper::get_lti_tool($tool->id);

        // Create a second module and publish it.
        $mod = $generator->create_module('assign', ['course' => $course->id]);
        $tooldata = [
            'cmid' => $mod->cmid,
            'courseid' => $course->id,
            'membersyncmode' => $membersyncmode,
            'membersync' => $membersync,
            'ltiversion' => 'LTI-1p3'
        ];
        $tool2 = $generator->create_lti_tool((object)$tooldata);
        $tool2 = helper::get_lti_tool($tool2->id);

        // Create a course and publish it.
        $tooldata = [
            'courseid' => $course->id,
            'membersyncmode' => $membersyncmode,
            'membersync' => $membersync,
            'ltiversion' => 'LTI-1p3'
        ];
        $tool3 = $generator->create_lti_tool((object)$tooldata);
        $tool3 = helper::get_lti_tool($tool3->id);

        return [$course, $tool, $tool2, $tool3, $reg, $deployment];
    }

    /**
     * Fake a user launch for the given published resource.
     *
     * @param \stdClass $resource the published course or module.
     * @param array $user a mock platform user who is performing the launch.
     */
    protected function fake_user_launch(\stdClass $resource, array $user) {
        $launchservice = new tool_launch_service(
            new deployment_repository(),
            new application_registration_repository(),
            new resource_link_repository(),
            new user_repository(),
            new context_repository()
        );
        $mocklaunch = $this->get_mock_launch($resource, $user);

        $launchservice->user_launches_tool($mocklaunch, $resource);
    }

    /**
     * Enable auth_lti plugin.
     */
    protected function enable_auth() {
        $auths = get_enabled_auth_plugins();
        if (!in_array('lti', $auths)) {
            $auths[] = 'lti';
        }
        set_config('auth', implode(',', $auths));
    }

    /**
     * Enable enrol_lti plugin.
     */
    protected function enable_enrol() {
        $enabled = enrol_get_plugins(true);
        $enabled['lti'] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }
}
