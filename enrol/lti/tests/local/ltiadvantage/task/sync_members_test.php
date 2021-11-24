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

namespace enrol_lti\local\ltiadvantage\task;

use enrol_lti\helper;
use enrol_lti\local\ltiadvantage\entity\user;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lti_advantage_testcase.php');

/**
 * Tests for the enrol_lti\local\ltiadvantage\task\sync_members scheduled task.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_members_test extends \lti_advantage_testcase {
    /**
     * Helper to get a list of mocked member entries for use in the mocked sync task.
     *
     * @param array $userids the array of lti user ids to use.
     * @param array|null $legacyuserids legacy user ids for the lti11_legacy_user_id property, null if not desired.
     * @param bool $names whether to include names in the user data or not.
     * @param bool $emails whether to include email in the user data or not.
     * @param bool $linklevel whether to mock the user return data at link-level (true) or context-level (false).
     * @param bool $picture whether to mock a user's picture field in the return data.
     * @return array the array of users.
     * @throws \Exception if the legacyuserids array doesn't contain the correct number of ids.
     */
    protected function get_mock_members_with_ids(array $userids, ?array $legacyuserids = null, $names = true,
            $emails = true, bool $linklevel = true, bool $picture = false): array {

        if (!is_null($legacyuserids) && count($legacyuserids) != count($userids)) {
            throw new \Exception('legacyuserids must contain the same number of ids as $userids.');
        }

        $users = [];
        foreach ($userids as $userid) {
            $user = ['user_id' => (string) $userid];
            if ($picture) {
                $user['picture'] = $this->getExternalTestFileUrl('/test.jpg', false);
            }
            if ($names) {
                $user['given_name'] = 'Firstname' . $userid;
                $user['family_name'] = 'Surname' . $userid;
            }
            if ($emails) {
                $user['email'] = "firstname.surname{$userid}@lms.example.org";
            }
            if ($legacyuserids) {
                $user['lti11_legacy_user_id'] = array_shift($legacyuserids);
            }
            if ($linklevel) {
                // Link-level memberships also include a message property.
                $user['message'] = [
                    'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiResourceLinkRequest'
                ];
            }
            $users[] = $user;
        }
        return $users;
    }

    /**
     * Gets a task mocked to only support resource-link-level memberships request.
     *
     * @param array $resourcelinks array for stipulating per link users, containing list of [resourcelink, members].
     * @return sync_members|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function get_mock_task_resource_link_level(array $resourcelinks = []) {
        $mocktask = $this->getMockBuilder(sync_members::class)
            ->onlyMethods(['get_resource_link_level_members', 'get_context_level_members'])
            ->getMock();
        $mocktask->expects($this->any())
            ->method('get_context_level_members')
            ->will($this->returnCallback(function() {
                return false;
            }));
        $expectedcount = !empty($resourcelinks) ? count($resourcelinks) : 1;
        $mocktask->expects($this->exactly($expectedcount))
            ->method('get_resource_link_level_members')
            ->will($this->returnCallback(function ($nrpsinfo, $serviceconnector, $reslink) use ($resourcelinks) {
                if ($resourcelinks) {
                    foreach ($resourcelinks as $rl) {
                        if ($reslink->get_resourcelinkid() === $rl[0]->get_resourcelinkid()) {
                            return $rl[1];
                        }
                    }
                } else {
                    return $this->get_mock_members_with_ids(range(1, 2));
                }
            }));
        return $mocktask;
    }

    /**
     * Gets a task mocked to only support context-level memberships request.
     *
     * @return sync_members|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function get_mock_task_context_level() {
        $mocktask = $this->getMockBuilder(sync_members::class)
            ->onlyMethods(['get_resource_link_level_members', 'get_context_level_members'])
            ->getMock();
        $mocktask->expects($this->any())
            ->method('get_resource_link_level_members')
            ->will($this->returnCallback(function() {
                return false;
            }));
        $mocktask->expects($this->any())
            ->method('get_context_level_members')
            ->will($this->returnCallback(function() {
                return $this->get_mock_members_with_ids(range(1, 3), null, true, true, false);
            }));;
        return $mocktask;
    }

    /**
     * Gets a sync task, with the remote calls mocked to return the supplied users.
     *
     * See get_mock_members_with_ids() for generating the users for input.
     *
     * @param array $users a list of users, the result of a call to get_mock_members_with_ids().
     * @return \PHPUnit\Framework\MockObject\MockObject the mock task.
     */
    protected function get_mock_task_with_users(array $users) {
        $mocktask = $this->getMockBuilder(sync_members::class)
            ->onlyMethods(['get_resource_link_level_members', 'get_context_level_members'])
            ->getMock();
        $mocktask->expects($this->any())
            ->method('get_context_level_members')
            ->will($this->returnCallback(function() {
                return false;
            }));
        $mocktask->expects($this->any())
            ->method('get_resource_link_level_members')
            ->will($this->returnCallback(function () use ($users) {
                return $users;
            }));
        return $mocktask;
    }

    /**
     * Check that all the given ltiusers are enrolled in the course.
     *
     * @param \stdClass $course the course instance.
     * @param user[] $ltiusers array of lti user instances.
     */
    protected function verify_course_enrolments(\stdClass $course, array $ltiusers) {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        $enrolledusers = get_enrolled_users(\context_course::instance($course->id));
        $this->assertCount(count($ltiusers), $enrolledusers);
        $enrolleduserids = array_map(function($stringid) {
            return (int) $stringid;
        }, array_column($enrolledusers, 'id'));
        foreach ($ltiusers as $ltiuser) {
            $this->assertContains($ltiuser->get_localid(), $enrolleduserids);
        }
    }

    /**
     * Test confirming task name.
     */
    public function test_get_name() {
        $this->assertEquals(get_string('tasksyncmembers', 'enrol_lti'), (new sync_members())->get_name());
    }

    /**
     * Test a resource-link-level membership sync, confirming that all relevant domain objects are updated properly.
     */
    public function test_resource_link_level_sync() {
        $this->resetAfterTest();
        [$course, $resource] = $this->create_test_environment();

        // Launch the tool for a user.
        $mocklaunch = $this->get_mock_launch($resource, $this->get_mock_launch_users_with_ids(['1'])[0]);
        $instructoruser = $this->lti_advantage_user_authenticates('1');
        $launchservice = $this->get_tool_launch_service();
        $launchservice->user_launches_tool($instructoruser, $mocklaunch);

        // Sync members.
        $task = $this->get_mock_task_resource_link_level();
        $task->execute();

        // Verify 2 users and their corresponding course enrolments exist.
        $this->expectOutputRegex(
            "/Completed - Synced members for tool '$resource->id' in the course '$course->id'. ".
            "Processed 2 users; enrolled 2 members; unenrolled 0 members./"
        );
        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(2, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);
    }

    /**
     * Test a resource-link-level membership sync when there are more than one resource links for the resource.
     */
    public function test_resource_link_level_sync_multiple_resource_links() {
        $this->resetAfterTest();
        [$course, $resource] = $this->create_test_environment();

        // Launch twice - once from each resource link in the platform.
        $launchservice = $this->get_tool_launch_service();
        $instructoruser = $this->lti_advantage_user_authenticates('1');
        $mocklaunch = $this->get_mock_launch($resource, $this->get_mock_launch_users_with_ids(['1'])[0], '123');
        $launchservice->user_launches_tool($instructoruser, $mocklaunch);
        $mocklaunch = $this->get_mock_launch($resource, $this->get_mock_launch_users_with_ids(['1'])[0], '456');
        $launchservice->user_launches_tool($instructoruser, $mocklaunch);

        // Now, grab the resource links.
        $rlrepo = new resource_link_repository();
        $reslinks = $rlrepo->find_by_resource($resource->id);
        $mockmembers = $this->get_mock_members_with_ids(range(1, 10));
        $mockusers1 = array_slice($mockmembers, 0, 6);
        $mockusers2 = array_slice($mockmembers, 6);
        $resourcelinks = [
            [$reslinks[0], $mockusers1],
            [$reslinks[1], $mockusers2]
        ];

        // Sync the members, using the mock task set up to sync different sets of users for each resource link.
        $task = $this->get_mock_task_resource_link_level($resourcelinks);
        ob_start();
        $task->execute();
        $output = ob_get_contents();
        ob_end_clean();

        // Verify 10 users and their corresponding course enrolments exist.
        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(10, $ltiusers);
        $this->assertStringContainsString("Completed - Synced 6 members for the resource link", $output);
        $this->assertStringContainsString("Completed - Synced 4 members for the resource link", $output);
        $this->assertStringContainsString("Completed - Synced members for tool '$resource->id' in the course '".
            "$resource->courseid'. Processed 10 users; enrolled 10 members; unenrolled 0 members.\n", $output);
        $this->verify_course_enrolments($course, $ltiusers);
    }

    /**
     * Verify the task will update users' profile pictures if the 'picture' member field is provided.
     */
    public function test_user_profile_image_sync() {
        $this->resetAfterTest();
        [$course, $resource] = $this->create_test_environment();

        // Launch the tool for a user.
        $mocklaunch = $this->get_mock_launch($resource, $this->get_mock_launch_users_with_ids(['1'])[0]);
        $launchservice = $this->get_tool_launch_service();
        $instructoruser = $this->lti_advantage_user_authenticates('1');
        $launchservice->user_launches_tool($instructoruser, $mocklaunch);

        // Sync members.
        $task = $this->get_mock_task_with_users($this->get_mock_members_with_ids(['1'], null, true, true, true, true));
        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify 1 users and their corresponding course enrolments exist.
        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(1, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);

        // Verify user profile image has been updated.
        $this->verify_user_profile_image_updated($ltiusers[0]->get_localid());
    }

    /**
     * Test a context-level membership sync, confirming that all relevant domain objects are updated properly.
     */
    public function test_context_level_sync() {
        $this->resetAfterTest();
        [$course, $resource] = $this->create_test_environment();

        // Launch the tool for a user.
        $mocklaunch = $this->get_mock_launch($resource, $this->get_mock_launch_users_with_ids(['1'])[0]);
        $launchservice = $this->get_tool_launch_service();
        $instructoruser = $this->lti_advantage_user_authenticates('1');
        $launchservice->user_launches_tool($instructoruser, $mocklaunch);

        // Sync members.
        $task = $this->get_mock_task_context_level();
        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify 3 users and their corresponding course enrolments exist.
        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(3, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);
    }

    /**
     * Test verifying the sync task handles the omission/inclusion of PII information for users.
     */
    public function test_sync_user_data() {
        $this->resetAfterTest();
        [$course, $resource, $resource2, $resource3, $appreg] = $this->create_test_environment();
        $userrepo = new user_repository();

        // Launch the tool for a user.
        $mocklaunch = $this->get_mock_launch($resource, $this->get_mock_launch_users_with_ids(['1'])[0]);
        $launchservice = $this->get_tool_launch_service();
        $instructoruser = $this->lti_advantage_user_authenticates('1');
        $launchservice->user_launches_tool($instructoruser, $mocklaunch);

        // Sync members.
        $task = $this->get_mock_task_with_users($this->get_mock_members_with_ids(range(1, 5), null, false, false));

        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify 5 users and their corresponding course enrolments exist.
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(5, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);

        // Since user data wasn't included in the response, the users will have been synced using fallbacks,
        // so verify these.
        foreach ($ltiusers as $ltiuser) {
            $user = \core_user::get_user($ltiuser->get_localid());
            // Firstname falls back to sourceid.
            $this->assertEquals($ltiuser->get_sourceid(), $user->firstname);

            // Lastname falls back to resource context id.
            $this->assertEquals($appreg->get_platformid(), $user->lastname);

            // Email falls back to example.com.
            $issuersubhash = sha1($appreg->get_platformid() . '_' . $ltiuser->get_sourceid());
            $this->assertEquals("enrol_lti_13_{$issuersubhash}@example.com", $user->email);
        }

        // Sync again, this time with user data included.
        $mockmembers = $this->get_mock_members_with_ids(range(1, 5));
        $task = $this->get_mock_task_with_users($mockmembers);

        ob_start();
        $task->execute();
        ob_end_clean();

        // User data was included in the response and should have been updated.
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(5, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);
        foreach ($ltiusers as $ltiuser) {
            $user = \core_user::get_user($ltiuser->get_localid());
            $mockmemberindex = array_search($ltiuser->get_sourceid(), array_column($mockmembers, 'user_id'));
            $mockmember = $mockmembers[$mockmemberindex];
            $this->assertEquals($mockmember['given_name'], $user->firstname);
            $this->assertEquals($mockmember['family_name'], $user->lastname);
            $this->assertEquals($mockmember['email'], $user->email);
        }
    }

    /**
     * Test verifying the task won't sync members for shared resources having member sync disabled.
     */
    public function test_membership_sync_disabled() {
        $this->resetAfterTest();
        [$course, $resource] = $this->create_test_environment(true, true, false);

        // Launch the tool for a user.
        $mockuser = $this->get_mock_launch_users_with_ids(['1'])[0];
        $mocklaunch = $this->get_mock_launch($resource, $mockuser);
        $launchservice = $this->get_tool_launch_service();
        $instructoruser = $this->lti_advantage_user_authenticates('1');
        $launchservice->user_launches_tool($instructoruser, $mocklaunch);

        // Sync members.
        $task = $this->get_mock_task_with_users($this->get_mock_launch_users_with_ids(range(1, 4)));
        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify no users were added or removed.
        // A single user (the user who launched the resource link) is expected.
        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(1, $ltiusers);
        $this->assertEquals($mockuser['user_id'], $ltiusers[0]->get_sourceid());
        $this->verify_course_enrolments($course, $ltiusers);
    }

    /**
     * Test verifying the sync task for resources configured as 'helper::MEMBER_SYNC_ENROL_AND_UNENROL'.
     */
    public function test_sync_mode_enrol_and_unenrol() {
        $this->resetAfterTest();
        [$course, $resource] = $this->create_test_environment();
        $userrepo = new user_repository();

        // Launch the tool for a user.
        $mockuser = $this->get_mock_launch_users_with_ids(['1'])[0];
        $mocklaunch = $this->get_mock_launch($resource, $mockuser);
        $launchservice = $this->get_tool_launch_service();
        $instructoruser = $this->lti_advantage_user_authenticates('1');
        $launchservice->user_launches_tool($instructoruser, $mocklaunch);

        // Sync members.
        $task = $this->get_mock_task_with_users($this->get_mock_members_with_ids(range(1, 3)));

        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify 3 users and their corresponding course enrolments exist.
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(3, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);

        // Now, simulate a subsequent sync in which 1 existing user maintains access,
        // 2 existing users are unenrolled and 3 new users are enrolled.
        $task2 = $this->get_mock_task_with_users($this->get_mock_members_with_ids(['1', '4', '5', '6']));
        ob_start();
        $task2->execute();
        ob_end_clean();

        // Verify the missing users have been unenrolled and new users enrolled.
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(4, $ltiusers);
        $unenrolleduserids = ['2', '3'];
        $enrolleduserids = ['1', '4', '5', '6'];
        foreach ($ltiusers as $ltiuser) {
            $this->assertNotContains($ltiuser->get_sourceid(), $unenrolleduserids);
            $this->assertContains($ltiuser->get_sourceid(), $enrolleduserids);
        }
        $this->verify_course_enrolments($course, $ltiusers);
    }

    /**
     * Confirm the sync task operation for resources configured as 'helper::MEMBER_SYNC_UNENROL_MISSING'.
     */
    public function test_sync_mode_unenrol_missing() {
        $this->resetAfterTest();
        [$course, $resource] = $this->create_test_environment(true, true, true, helper::MEMBER_SYNC_UNENROL_MISSING);
        $userrepo = new user_repository();

        // Launch the tool for a user.
        $mocklaunch = $this->get_mock_launch($resource, $this->get_mock_launch_users_with_ids([1])[0]);
        $launchservice = $this->get_tool_launch_service();
        $instructoruser = $this->lti_advantage_user_authenticates('1');
        $launchservice->user_launches_tool($instructoruser, $mocklaunch);
        $this->assertCount(1, $userrepo->find_by_resource($resource->id));

        // Sync members using a payload which doesn't include the original launch user (User id = 1).
        $task = $this->get_mock_task_with_users($this->get_mock_members_with_ids(range(2, 3)));

        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify the original user (launching user) has been unenrolled and that no new members have been enrolled.
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(0, $ltiusers);
    }

    /**
     * Confirm the sync task operation for resources configured as 'helper::MEMBER_SYNC_ENROL_NEW'.
     */
    public function test_sync_mode_enrol_new() {
        $this->resetAfterTest();
        [$course, $resource] = $this->create_test_environment(true, true, true, helper::MEMBER_SYNC_ENROL_NEW);
        $userrepo = new user_repository();

        // Launch the tool for a user.
        $mocklaunch = $this->get_mock_launch($resource, $this->get_mock_launch_users_with_ids([1])[0]);
        $launchservice = $this->get_tool_launch_service();
        $instructoruser = $this->lti_advantage_user_authenticates('1');
        $launchservice->user_launches_tool($instructoruser, $mocklaunch);
        $this->assertCount(1, $userrepo->find_by_resource($resource->id));

        // Sync members using a payload which includes two new members only (i.e. not the original launching user).
        $task = $this->get_mock_task_with_users($this->get_mock_members_with_ids(range(2, 3)));

        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify we now have 3 enrolments. The original user (who was not unenrolled) and the 2 new users.
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(3, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);
    }

    /**
     * Test confirming that no changes take place if the auth_lti plugin is not enabled.
     */
    public function test_sync_auth_disabled() {
        $this->resetAfterTest();
        [$course, $resource] = $this->create_test_environment(false);
        $userrepo = new user_repository();

        // Launch the tool for a user.
        $mocklaunch = $this->get_mock_launch($resource, $this->get_mock_launch_users_with_ids([1])[0]);
        $launchservice = $this->get_tool_launch_service();
        $instructoruser = $this->lti_advantage_user_authenticates('1');
        $launchservice->user_launches_tool($instructoruser, $mocklaunch);
        $this->assertCount(1, $userrepo->find_by_resource($resource->id));

        // If the task were to run, this would trigger 1 unenrolment (the launching user) and 3 enrolments.
        $task = $this->get_mock_task_with_users($this->get_mock_members_with_ids(range(2, 2)));
        $task->execute();

        // Verify that the sync didn't take place.
        $this->expectOutputRegex("/Skipping task - Authentication plugin 'LTI' is not enabled/");
        $this->assertCount(1, $userrepo->find_by_resource($resource->id));
    }

    /**
     * Test confirming that no sync takes place when the enrol_lti plugin is not enabled.
     */
    public function test_sync_enrol_disabled() {
        $this->resetAfterTest();
        [$course, $resource] = $this->create_test_environment(true, false);
        $userrepo = new user_repository();

        // Launch the tool for a user.
        $mocklaunch = $this->get_mock_launch($resource, $this->get_mock_launch_users_with_ids([1])[0]);
        $launchservice = $this->get_tool_launch_service();
        $instructoruser = $this->lti_advantage_user_authenticates('1');
        $launchservice->user_launches_tool($instructoruser, $mocklaunch);
        $this->assertCount(1, $userrepo->find_by_resource($resource->id));

        // If the task were to run, this would trigger 1 unenrolment of the launching user and enrolment of 3 users.
        $task = $this->get_mock_task_with_users($this->get_mock_members_with_ids(range(2, 2)));
        $task->execute();

        // Verify that the sync didn't take place.
        $this->expectOutputRegex("/Skipping task - The 'Publish as LTI tool' plugin is disabled/");
        $this->assertCount(1, $userrepo->find_by_resource($resource->id));
    }

    /**
     * Test syncing members for a membersync-enabled resource when the launch omits the NRPS service endpoints.
     */
    public function test_sync_no_nrps_support() {
        $this->resetAfterTest();
        [$course, $resource] = $this->create_test_environment();
        $userrepo = new user_repository();

        // Launch the tool for a user.
        $mockinstructor = $this->get_mock_launch_users_with_ids([1])[0];
        $mocklaunch = $this->get_mock_launch($resource, $mockinstructor, null, false, false);
        $launchservice = $this->get_tool_launch_service();
        $instructoruser = $this->lti_advantage_user_authenticates('1');
        $launchservice->user_launches_tool($instructoruser, $mocklaunch);
        $this->assertCount(1, $userrepo->find_by_resource($resource->id));

        // The task would sync an additional 2 users if the link had NRPS service support.
        $task = $this->get_mock_task_with_users($this->get_mock_members_with_ids(range(2, 2)));

        // We expect the task to report that it is skipping the resource due to a lack of NRPS support.
        $task->execute();

        // Verify no enrolments or unenrolments.
        $this->expectOutputRegex(
            "/Skipping - No names and roles service found.\n".
            "Completed - Synced members for tool '{$resource->id}' in the course '{$course->id}'. ".
            "Processed 0 users; enrolled 0 members; unenrolled 0 members./"
        );
        $this->assertCount(1, $userrepo->find_by_resource($resource->id));
    }

    /**
     * Test the member sync for a range of scenarios including migrated tools, unlaunched tools.
     *
     * @dataProvider member_sync_data_provider
     * @param array|null $legacydata array detailing what legacy information to create, or null if not required.
     * @param array $launchdata array containing details of the launch, including user and migration claim.
     * @param array|null $syncmembers the members to use in the mock sync.
     * @param array $expected the array detailing expectations.
     */
    public function test_sync_user_migration(?array $legacydata, array $launchdata,
            ?array $syncmembers, array $expected) {

        $this->resetAfterTest();
        // Set up the environment.
        [$course, $resource] = $this->create_test_environment(true, true, true, helper::MEMBER_SYNC_ENROL_NEW);

        // Set up legacy tool and user data.
        [$legacytools, $legacyconsumerrecord, $legacyusers] = $this->setup_legacy_data($course, $legacydata);

        // Mock the launch for the specified user.
        $mocklaunch = $this->get_mock_launch($resource, $launchdata['user'], null, true, true,
            $launchdata['launch_migration_claim']);

        // Perform the launch.
        // TODO: this next call causes the problem since it doesn't properly simulate an auth
        //  call in which migration takes place - resulting in a new user account instead of finding a legacy one.
        //  we need to improve the auth mocking function to support migration-enabled launches.
        $instructoruser = $this->lti_advantage_user_authenticates(
            $launchdata['user']['user_id'],
            $launchdata['launch_migration_claim'] ?? []
        );
        $this->get_tool_launch_service()->user_launches_tool($instructoruser, $mocklaunch);

        // Prepare the sync task, with a stubbed list of members.
        $task = $this->get_mock_task_with_users($syncmembers);

        // Run the member sync.
        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify enrolments.
        $ltiusers = (new user_repository())->find_by_resource($resource->id);
        $this->assertCount(count($expected['enrolments']), $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);

        // Verify migration process.
        $legacyuserids = array_column($legacyusers, 'id');
        foreach ($ltiusers as $ltiuser) {
            $this->assertArrayHasKey($ltiuser->get_sourceid(), $expected['enrolments']);
            if (!$expected['enrolments'][$ltiuser->get_sourceid()]['is_migrated']) {
                // Those members who hadn't launched over 1p1 prior will have new lti user records created.
                $this->assertNotContains((string)$ltiuser->get_localid(), $legacyuserids);
            } else {
                // Those members who were either already migrated during launch, or were migrated during the sync,
                // will be mapped to their legacy user accounts.
                // TODO: Check this passes once we update auth code with the migration stuff.
                $this->assertContains((string)$ltiuser->get_localid(), $legacyuserids);
            }
        }
    }

    /**
     * Data provider for member syncs.
     *
     * @return array[] the array of test data.
     */
    public function member_sync_data_provider(): array {
        return [
            'Migrated tool, user ids changed, new and existing users present in sync' => [
                'legacy_data' => [
                    'users' => [
                        ['user_id' => '1'],
                        ['user_id' => '2'],
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
                        'user_id' => '1',
                        'context_id' => 'd345b',
                        'tool_consumer_instance_guid' => '12345-123',
                        'resource_link_id' => '4b6fa'
                    ],
                ],
                'sync_members_data' => [
                    $this->get_mock_members_with_ids(['1p3_1'], ['1'])[0],
                    $this->get_mock_members_with_ids(['1p3_2'], ['2'])[0],
                    $this->get_mock_members_with_ids(['1p3_3'], ['3'])[0],
                    $this->get_mock_members_with_ids(['1p3_4'], ['4'])[0],
                ],
                'expected' => [
                    'enrolments' => [
                        '1p3_1' => [
                            'is_migrated' => true,
                        ],
                        '1p3_2' => [
                            'is_migrated' => true,
                        ],
                        '1p3_3' => [
                            'is_migrated' => false,
                        ],
                        '1p3_4' => [
                            'is_migrated' => false,
                        ]
                    ]
                ]
            ],
            'Migrated tool, no change in user ids, new and existing users present in sync' => [
                'legacy_data' => [
                    'users' => [
                        ['user_id' => '1'],
                        ['user_id' => '2'],
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
                'sync_members_data' => [
                    $this->get_mock_members_with_ids(['1'], null)[0],
                    $this->get_mock_members_with_ids(['2'], null)[0],
                    $this->get_mock_members_with_ids(['3'], null)[0],
                    $this->get_mock_members_with_ids(['4'], null)[0],
                ],
                'expected' => [
                    'enrolments' => [
                        '1' => [
                            'is_migrated' => true,
                        ],
                        '2' => [
                            'is_migrated' => true,
                        ],
                        '3' => [
                            'is_migrated' => false,
                        ],
                        '4' => [
                            'is_migrated' => false,
                        ]
                    ]
                ]
            ],
            'New tool, no launch migration claim, change in user ids, new and existing users present in sync' => [
                'legacy_data' => [
                    'users' => [
                        ['user_id' => '1'],
                        ['user_id' => '2'],
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
                'sync_members_data' => [
                    $this->get_mock_members_with_ids(['1p3_1'], null)[0],
                    $this->get_mock_members_with_ids(['1p3_2'], null)[0],
                    $this->get_mock_members_with_ids(['1p3_3'], null)[0],
                    $this->get_mock_members_with_ids(['1p3_4'], null)[0],
                ],
                'expected' => [
                    'enrolments' => [
                        '1p3_1' => [
                            'is_migrated' => false,
                        ],
                        '1p3_2' => [
                            'is_migrated' => false,
                        ],
                        '1p3_3' => [
                            'is_migrated' => false,
                        ],
                        '1p3_4' => [
                            'is_migrated' => false,
                        ]
                    ]
                ]
            ],
            'New tool, no launch migration claim, no change in user ids, new and existing users present in sync' => [
                'legacy_data' => [
                    'users' => [
                        ['user_id' => '1'],
                        ['user_id' => '2'],
                    ],
                    'consumer_key' => 'CONSUMER_1',
                    'tools' => [
                        ['secret' => 'toolsecret1'],
                        ['secret' => 'toolsecret2'],
                    ]
                ],
                'launch_data' => [
                    'user' => $this->get_mock_launch_users_with_ids(['1'])[0],
                    'launch_migration_claim' => null,
                ],
                'sync_members_data' => [
                    $this->get_mock_members_with_ids(['1'], null)[0],
                    $this->get_mock_members_with_ids(['2'], null)[0],
                    $this->get_mock_members_with_ids(['3'], null)[0],
                    $this->get_mock_members_with_ids(['4'], null)[0],
                ],
                'expected' => [
                    'enrolments' => [
                        '1' => [
                            'is_migrated' => false,
                        ],
                        '2' => [
                            'is_migrated' => false,
                        ],
                        '3' => [
                            'is_migrated' => false,
                        ],
                        '4' => [
                            'is_migrated' => false,
                        ]
                    ]
                ]
            ],
            'New tool, migration only via member sync, no launch claim, new and existing users present in sync' => [
                'legacy_data' => [
                    'users' => [
                        ['user_id' => '1'],
                        ['user_id' => '2'],
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
                'sync_members_data' => [
                    $this->get_mock_members_with_ids(['1p3_1'], ['1'])[0],
                    $this->get_mock_members_with_ids(['1p3_2'], ['2'])[0],
                    $this->get_mock_members_with_ids(['1p3_3'], ['3'])[0],
                    $this->get_mock_members_with_ids(['1p3_4'], ['4'])[0],
                ],
                'expected' => [
                    'enrolments' => [
                        '1p3_1' => [
                            'is_migrated' => false,
                        ],
                        '1p3_2' => [
                            'is_migrated' => false,
                        ],
                        '1p3_3' => [
                            'is_migrated' => false,
                        ],
                        '1p3_4' => [
                            'is_migrated' => false,
                        ]
                    ]
                ]
            ]
        ];
    }
}
