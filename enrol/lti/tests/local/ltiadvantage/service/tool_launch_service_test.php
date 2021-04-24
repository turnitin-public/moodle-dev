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
use IMSGlobal\LTI13\LTI_Message_Launch;

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
     * Test the use case "A user launches a tool so they can view an external resource/activity".
     *
     * @dataProvider user_launch_provider
     * @param array|null $legacydata array detailing what legacy information to create, or null if not required.
     * @param array|null $launchdata array containing details of the launch, including user and migration claim.
     * @param array $expected the array detailing expectations.
     */
    public function test_user_launches_tool(?array $legacydata, ?array $launchdata, array $expected) {
        // Setup.
        $contextrepo = new context_repository();
        $resourcelinkrepo = new resource_link_repository();
        $deploymentrepo = new deployment_repository();
        $userrepo = new user_repository();
        [
            $course,
            $modresource,
            $modresource2,
            $courseresource,
            $registration,
            $deployment
        ] = $this->create_test_environment();

        // Generate the legacy data, on which the user migration is based.
        if ($legacydata) {
            [$legacytools, $legacyconsumer, $legacyusers] = $this->setup_legacy_data($course, $legacydata);
        }

        // Get a mock 1.3 launch, optionally including the lti1p1 migration claim based on a legacy tool secret.
        $mocklaunch = $this->get_mock_launch($modresource, $launchdata['user'], null,
            $launchdata['launch_migration_claim']);

        // Call the service.
        $launchservice = $this->get_tool_launch_service();
        if (isset($expected['exception'])) {
            $this->expectException($expected['exception']);
            $this->expectExceptionMessage($expected['exception_message']);
        }
        [$userid, $resource] = $launchservice->user_launches_tool($mocklaunch, $modresource);

        // As part of the launch, we expect to now have an lti-enrolled user who is recorded against the deployment.
        $users = $userrepo->find_by_resource($resource->id);
        $this->assertCount(1, $users);
        $user = array_pop($users);
        $this->assertInstanceOf(user::class, $user);
        $this->assertEquals($deployment->get_id(), $user->get_deploymentid());

        // In cases where the lti user is migrated, we expect the underlying user record to be the same as legacy.
        // We also expect a mapping of the consumer key to be present on the deployment instance.
        if ($expected['user_migrated']) {
            $legacyuserids = array_column($legacyusers, 'id');
            $this->assertContains((string)$user->get_localid(), $legacyuserids);
        } else {
            // No migration took place, verify user is not linked.
            if ($legacydata) {
                $legacyuserids = array_column($legacyusers, 'id');
                $this->assertNotContains($user->get_localid(), $legacyuserids);
            }
        }

        // Deployment should be mapped to the legacy consumer key even if the user wasn't matched and migrated.
        $updateddeployment = $deploymentrepo->find($deployment->get_id());
        $this->assertEquals($expected['deployment_consumer_key'], $updateddeployment->get_legacy_consumer_key());

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

    /**
     * Provider for user launch testing.
     *
     * @return array[] the test case data.
     */
    public function user_launch_provider(): array {
        return [
            'New tool: no legacy data, no migration claim sent' => [
                'legacy_data' => null,
                'launch_data' => [
                    'user' => $this->get_mock_launch_users_with_ids(['1p3_1'])[0],
                    'launch_migration_claim' => null,
                ],
                'expected' => [
                    'user_migrated' => false,
                    'deployment_consumer_key' => null,
                ]
            ],
            'Migrated tool: Legacy data exists, no change in user_id so omitted from claim' => [
                'legacy_data' => [
                    'users' => [
                        ['user_id' => '1'],
                    ],
                    'consumer_key' => 'CONSUMER_1',
                    'tools' => [
                        ['secret' => 'toolsecret1'],
                        ['secret' => 'toolsecret2'],
                    ]
                ],
                'launch_data' => [
                    'user' => $this->get_mock_launch_users_with_ids(['1'])[0],
                    'launch_migration_claim' => [
                        'consumer_key' => 'CONSUMER_1',
                        'signing_secret' => 'toolsecret1',
                        'context_id' => 'd345b',
                        'tool_consumer_instance_guid' => '12345-123',
                        'resource_link_id' => '4b6fa'
                    ],
                ],
                'expected' => [
                    'user_migrated' => true,
                    'deployment_consumer_key' => 'CONSUMER_1',
                ]
            ],
            'Migrated tool: Legacy data exists, claim includes change in user_id' => [
                'legacy_data' => [
                    'users' => [
                        ['user_id' => '123-abc'],
                    ],
                    'consumer_key' => 'CONSUMER_1',
                    'tools' => [
                        ['secret' => 'toolsecret1'],
                        ['secret' => 'toolsecret2'],
                    ]
                ],
                'launch_data' => [
                    'user' => $this->get_mock_launch_users_with_ids(['1p3_1'])[0],
                    'launch_migration_claim' => [
                        'consumer_key' => 'CONSUMER_1',
                        'signing_secret' => 'toolsecret1',
                        'user_id' => '123-abc',
                        'context_id' => 'd345b',
                        'tool_consumer_instance_guid' => '12345-123',
                        'resource_link_id' => '4b6fa'
                    ],
                ],
                'expected' => [
                    'user_migrated' => true,
                    'deployment_consumer_key' => 'CONSUMER_1',
                ]
            ],
            'Migrated tool: Legacy data exists, claim includes user_id, signs with different valid secret' => [
                'legacy_data' => [
                    'users' => [
                        ['user_id' => '123-abc'],
                    ],
                    'consumer_key' => 'CONSUMER_1',
                    'tools' => [
                        ['secret' => 'toolsecret1'],
                        ['secret' => 'toolsecret2'],
                    ]
                ],
                'launch_data' => [
                    'user' => $this->get_mock_launch_users_with_ids(['1p3_1'])[0],
                    'launch_migration_claim' => [
                        'consumer_key' => 'CONSUMER_1',
                        'signing_secret' => 'toolsecret2',
                        'user_id' => '123-abc',
                        'context_id' => 'd345b',
                        'tool_consumer_instance_guid' => '12345-123',
                        'resource_link_id' => '4b6fa'
                    ],
                ],
                'expected' => [
                    'user_migrated' => true,
                    'deployment_consumer_key' => 'CONSUMER_1',
                ]
            ],
            'Migrated tool: Legacy data exists, claim sent and user_id not matched' => [
                'legacy_data' => [
                    'users' => [
                        ['user_id' => '123-abc'],
                    ],
                    'consumer_key' => 'CONSUMER_1',
                    'tools' => [
                        ['secret' => 'toolsecret1'],
                        ['secret' => 'toolsecret2'],
                    ]
                ],
                'launch_data' => [
                    'user' => $this->get_mock_launch_users_with_ids(['1p3_1'])[0],
                    'launch_migration_claim' => [
                        'consumer_key' => 'CONSUMER_1',
                        'signing_secret' => 'toolsecret1',
                        'user_id' => 'user-id-123',
                        'context_id' => 'd345b',
                        'tool_consumer_instance_guid' => '12345-123',
                        'resource_link_id' => '4b6fa'
                    ],
                ],
                'expected' => [
                    'user_migrated' => false,
                    'deployment_consumer_key' => 'CONSUMER_1',
                ]
            ],
            'Migrated tool: Legacy data exists, no migration claim sent' => [
                'legacy_data' => [
                    'users' => [
                        ['user_id' => '123-abc'],
                    ],
                    'consumer_key' => 'CONSUMER_1',
                    'tools' => [
                        ['secret' => 'toolsecret1'],
                        ['secret' => 'toolsecret2'],
                    ]
                ],
                'launch_data' => [
                    'user' => $this->get_mock_launch_users_with_ids(['1p3_1'])[0],
                    'launch_migration_claim' => null,
                ],
                'expected' => [
                    'user_migrated' => false,
                    'deployment_consumer_key' => null,
                ]
            ],
            'Migrated tool: Legacy data exists, migration claim signature generated using invalid secret' => [
                'legacy_data' => [
                    'users' => [
                        ['user_id' => '123-abc'],
                    ],
                    'consumer_key' => 'CONSUMER_1',
                    'tools' => [
                        ['secret' => 'toolsecret1'],
                        ['secret' => 'toolsecret2'],
                    ]
                ],
                'launch_data' => [
                    'user' => $this->get_mock_launch_users_with_ids(['1p3_1'])[0],
                    'launch_migration_claim' => [
                        'consumer_key' => 'CONSUMER_1',
                        'signing_secret' => 'secret-not-mapped-to-consumer',
                        'user_id' => 'user-id-123',
                        'context_id' => 'd345b',
                        'tool_consumer_instance_guid' => '12345-123',
                        'resource_link_id' => '4b6fa'
                    ],
                ],
                'expected' => [
                    'user_migrated' => false,
                    'exception' => \coding_exception::class,
                    'exception_message' => "Invalid 'oauth_consumer_key_sign' signature in lti1p1 claim"
                ]
            ],
            'Migrated tool: Legacy data exists, migration claim signature omitted' => [
                'legacy_data' => [
                    'users' => [
                        ['user_id' => '123-abc'],
                    ],
                    'consumer_key' => 'CONSUMER_1',
                    'tools' => [
                        ['secret' => 'toolsecret1'],
                        ['secret' => 'toolsecret2'],
                    ]
                ],
                'launch_data' => [
                    'user' => $this->get_mock_launch_users_with_ids(['1p3_1'])[0],
                    'launch_migration_claim' => [
                        'consumer_key' => 'CONSUMER_1',
                        'user_id' => 'user-id-123',
                        'context_id' => 'd345b',
                        'tool_consumer_instance_guid' => '12345-123',
                        'resource_link_id' => '4b6fa'
                    ],
                ],
                'expected' => [
                    'user_migrated' => false,
                    'exception' => \coding_exception::class,
                    'exception_message' => "Missing 'oauth_consumer_key_sign' property in lti1p1 migration claim."
                ]
            ]
        ];
    }

    /**
     * Test confirming that an exception is thrown if trying to launch a published resource without a custom id.
     */
    public function test_user_launches_tool_missing_custom_id() {
        // Setup.
        [
            $course,
            $modresource,
            $modresource2,
            $courseresource,
            $registration,
            $deployment
        ] = $this->create_test_environment();

        // Call the service.
        $launchservice = $this->get_tool_launch_service();
        $mockuser = $this->get_mock_launch_users_with_ids(['1p3_1'])[0];

        $mocklaunch = $this->getMockBuilder(LTI_Message_Launch::class)
            ->onlyMethods(['get_launch_data'])
            ->disableOriginalConstructor()
            ->getMock();
        $mocklaunch->expects($this->any())
            ->method('get_launch_data')
            ->will($this->returnCallback(function() use ($modresource, $mockuser) {
                // This simulates the data in the jwt['body'] of a real resource link launch.
                // Real launches would of course have this data and authenticity of the user verified.
                return [
                    'iss' => 'https://lms.example.org', // Must match registration in create_test_environment.
                    'aud' => '123', // Must match registration in create_test_environment.
                    'sub' => $mockuser['user_id'], // User id on the platform site.
                    'exp' => time() + 60,
                    'nonce' => 'some-nonce-value-123',
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
                        // NOTE: Lack of custom id here.
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

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessageMatches("/Invalid launch data. The custom claim field 'id' is required/");
        [$userid, $resource] = $launchservice->user_launches_tool($mocklaunch, $modresource);
    }

    /**
     * Test confirming that an exception is thrown if trying to launch the tool where no application can be found.
     */
    public function test_user_launches_tool_missing_registration() {
        // Setup.
        [
            $course,
            $modresource,
            $modresource2,
            $courseresource,
            $registration,
            $deployment
        ] = $this->create_test_environment();

        // Delete the registration before trying to launch.
        $appregrepo = new application_registration_repository();
        $appregrepo->delete($registration->get_id());

        // Call the service.
        $launchservice = $this->get_tool_launch_service();
        $mockuser = $this->get_mock_launch_users_with_ids(['1p3_1'])[0];
        $mocklaunch = $this->get_mock_launch($modresource, $mockuser);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessageMatches("/Invalid launch. Cannot launch tool for invalid registration/");
        [$userid, $resource] = $launchservice->user_launches_tool($mocklaunch, $modresource);
    }

    /**
     * Test confirming that an exception is thrown if trying to launch the tool where no deployment can be found.
     */
    public function test_user_launches_tool_missing_deployment() {
        // Setup.
        [
            $course,
            $modresource,
            $modresource2,
            $courseresource,
            $registration,
            $deployment
        ] = $this->create_test_environment();

        // Delete the deployment before trying to launch.
        $deploymentrepo = new deployment_repository();
        $deploymentrepo->delete($deployment->get_id());

        // Call the service.
        $launchservice = $this->get_tool_launch_service();
        $mockuser = $this->get_mock_launch_users_with_ids(['1p3_1'])[0];
        $mocklaunch = $this->get_mock_launch($modresource, $mockuser);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessageMatches("/Invalid launch. Cannot launch tool for invalid deployment id/");
        [$userid, $resource] = $launchservice->user_launches_tool($mocklaunch, $modresource);
    }

    /**
     * Verify that legacy mapping changes only occur the first time a migrated tool is launched for a given user.
     */
    public function test_user_launches_tool_migration_idempotency() {
        // Setup.
        $userrepo = new user_repository();
        [
            $course,
            $modresource,
            $modresource2,
            $courseresource,
            $registration,
            $deployment
        ] = $this->create_test_environment();

        // The user represented in the mock LTI 1.3 launch.
        $mockuser = $this->get_mock_launch_users_with_ids(['1p3_1'])[0];

        // Generate the legacy data, on which the user migration is based.
        $legacydata = [
            'users' => [
                ['user_id' => '123-abc'],
            ],
            'consumer_key' => 'CONSUMER_1',
            'tools' => [
                ['secret' => 'toolsecret1'],
                ['secret' => 'toolsecret2'],
            ]
        ];
        [$legacytools, $legacyconsumer, $legacyusers] = $this->setup_legacy_data($course, $legacydata);

        // Get a mock 1.3 launch, optionally including the lti1p1 migration claim based on a legacy tool secret.
        $migrationclaiminfo = [
            'consumer_key' => 'CONSUMER_1',
            'signing_secret' => 'toolsecret2',
            'user_id' => '123-abc',
            'context_id' => 'd345b',
            'tool_consumer_instance_guid' => '12345-123',
            'resource_link_id' => '4b6fa'
        ];
        $mocklaunch = $this->get_mock_launch($modresource, $mockuser, null, $migrationclaiminfo);

        // Setup the service.
        $launchservice = $this->get_tool_launch_service();

        // Launch once.
        $launchservice->user_launches_tool($mocklaunch, $modresource);
        $user1 = $userrepo->find_by_resource($modresource->id)[0];
        $this->assertEquals((string)$user1->get_localid(), $legacyusers[0]->id);

        // Launch again.
        $launchservice->user_launches_tool($mocklaunch, $modresource);
        $users = $userrepo->find_by_resource($modresource->id);
        $this->assertCount(1, $users);
        $user2 = $users[0];

        // Confirm the user doesn't change, except for potentially 'lastaccess', which may.
        $this->assertEquals($user1->get_issuer(), $user2->get_issuer());
        $this->assertEquals($user1->get_deploymentid(), $user2->get_deploymentid());
        $this->assertEquals($user1->get_firstname(), $user2->get_firstname());
        $this->assertEquals($user1->get_lastname(), $user2->get_lastname());
        $this->assertEquals($user1->get_email(), $user2->get_email());
        $this->assertEquals($user1->get_city(), $user2->get_city());
        $this->assertEquals($user1->get_country(), $user2->get_country());
        $this->assertEquals($user1->get_institution(), $user2->get_institution());
        $this->assertEquals($user1->get_timezone(), $user2->get_timezone());
        $this->assertEquals($user1->get_maildisplay(), $user2->get_maildisplay());
        $this->assertEquals($user1->get_mnethostid(), $user2->get_mnethostid());
        $this->assertEquals($user1->get_confirmed(), $user2->get_confirmed());
        $this->assertEquals($user1->get_lang(), $user2->get_lang());
        $this->assertEquals($user1->get_auth(), $user2->get_auth());
        $this->assertEquals($user1->get_resourcelinkid(), $user2->get_resourcelinkid());
        $this->assertEquals($user1->get_localid(), $user2->get_localid());
        $this->assertEquals($user1->get_id(), $user2->get_id());
    }
}
