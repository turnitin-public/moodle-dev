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
 * Tests for the enrol_lti\local\ltiadvantage\task\sync_members scheduled task class.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\local\ltiadvantage\task;

defined('MOODLE_INTERNAL') || die();

use enrol_lti\helper;
use enrol_lti\local\ltiadvantage\entity\user;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\context_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use enrol_lti\local\ltiadvantage\service\tool_launch_service;
require_once(__DIR__ . '/../lti_advantage_testcase.php');

/**
 * Tests for the enrol_lti\local\ltiadvantage\task\sync_members scheduled task.
 *
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_members_testcase extends \lti_advantage_testcase {
    /**
     * Setup run for each test case.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Generate a list of fake users for use with the mocked memberships service.
     *
     * @param int $numusers the number of fake users to generate.
     * @param bool $names whether to include names in the user data or not.
     * @param bool $emails whether to include email in the user data or not.
     * @param bool $linklevel whether to mock the user return data at link-level (true) or context-level (false).
     * @param bool $picture whether to mock a user's picture field in the return data.
     * @return array the array of users.
     */
    protected function get_mock_users($numusers = 5, $names = true, $emails = true, bool $linklevel = true,
            bool $picture = false) {

        $users = [];
        foreach (range(1, $numusers) as $usernum) {
            $user = ['user_id' => $usernum];
            if ($picture) {
                $user['picture'] = $this->getExternalTestFileUrl('/test.jpg', false);
            }
            if ($names) {
                $user['given_name'] = 'Firstname' . $usernum;
                $user['family_name'] = 'Surname' . $usernum;
            }
            if ($emails) {
                $user['email'] = "firstname.surname{$usernum}@lms.example.org";
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
     * @return sync_members|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function get_mock_task_resource_link_level() {
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
            ->will($this->returnCallback(function() {
                return $this->get_mock_users(2);
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
                return $this->get_mock_users(3, true, true, false);
            }));;
        return $mocktask;
    }

    /**
     * Gets a sync task, with the remote call mocked to return the supplied users.
     *
     * See get_mocked_users() for generating the users for input.
     *
     * @param array $users
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
     * Check that all the given ltiusers are also enrolled in the course.
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
     * Test a resource-link-level membership sync, confirming that all relevant domain objects are updated properly.
     */
    public function test_resource_link_level_sync() {
        $mockuser = $this->get_mock_users(1)[0];
        [$course, $resource] = $this->create_test_environment();
        $this->fake_user_launch($resource, $mockuser);

        $task = $this->get_mock_task_resource_link_level();
        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify 2 users and their corresponding course enrolments exist.
        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(2, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);
    }

    /**
     * Verify the task will update users' profile pictures if the 'picture' member field is provided.
     */
    public function test_user_profile_image_sync() {
        $mockuser = $this->get_mock_users(1)[0];
        [$course, $resource] = $this->create_test_environment();
        $this->fake_user_launch($resource, $mockuser);

        $task = $this->get_mock_task_with_users($this->get_mock_users(1, true, true, true, true));
        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify 1 users and their corresponding course enrolments exist.
        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(1, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);

        // Verify user profile image has been updated.
        global $CFG;
        $user = \core_user::get_user($ltiusers[0]->get_localid());
        $page = new \moodle_page();
        $page->set_url('/user/profile.php');
        $page->set_context(\context_system::instance());
        $renderer = $page->get_renderer('core');
        $usercontext = \context_user::instance($user->id);
        $userpicture = new \user_picture($user);
        $this->assertSame(
            $CFG->wwwroot . '/pluginfile.php/' . $usercontext->id . '/user/icon/boost/f2?rev='. $user->picture,
            $userpicture->get_url($page, $renderer)->out(false)
        );
    }

    /**
     * Test a context-level membership sync, confirming that all relevant domain objects are updated properly.
     */
    public function test_context_level_sync() {
        $mockuser = $this->get_mock_users(1)[0];
        [$course, $resource] = $this->create_test_environment();
        $this->fake_user_launch($resource, $mockuser);

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
        $mockuser = $this->get_mock_users(1)[0];
        [$course, $resource] = $this->create_test_environment();
        $this->fake_user_launch($resource, $mockuser);

        $task = $this->get_mock_task_with_users($this->get_mock_users(5, false, false));
        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify 5 users and their corresponding course enrolments exist.
        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(5, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);

        // Since user data wasn't included in the response, the users will have been synced using fallbacks,
        // so verify these.
        foreach ($ltiusers as $ltiuser) {
            // Firstname falls back to sourceid.
            $this->assertEquals($ltiuser->get_sourceid(), $ltiuser->get_firstname());

            // Lastname falls back to resource context id.
            $this->assertEquals($resource->contextid, $ltiuser->get_lastname());

            // Email falls back.
            $this->assertEquals($ltiuser->get_username() . '@example.com', $ltiuser->get_email());
        }

        // Sync again, this time with user data user data.
        $task = $this->get_mock_task_with_users($this->get_mock_users(5, true, true));
        ob_start();
        $task->execute();
        ob_end_clean();

        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(5, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);

        // User data was included in the response and should have been updated.
        foreach ($ltiusers as $ltiuser) {
            // Firstname falls back to sourceid.
            $this->assertEquals('Firstname'.$ltiuser->get_sourceid(), $ltiuser->get_firstname());
            $this->assertEquals('Surname'.$ltiuser->get_sourceid(), $ltiuser->get_lastname());
            $this->assertEquals("firstname.surname{$ltiuser->get_sourceid()}@lms.example.org", $ltiuser->get_email());
        }
    }

    /**
     * Test verifying the task won't sync members for shared resources having member sync disabled.
     */
    public function test_membership_sync_disabled() {
        $mockuser = $this->get_mock_users(1)[0];
        [$course, $resource] = $this->create_test_environment(true, true, false);
        $this->fake_user_launch($resource, $mockuser);

        $task = $this->get_mock_task_with_users($this->get_mock_users(4));
        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify no users were added or removed.
        // A single user (the user who launched the resource link) is expected.
        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(1, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);
    }

    /**
     * Test verifying the sync task for resources configured as 'helper::MEMBER_SYNC_ENROL_AND_UNENROL'.
     */
    public function test_sync_mode_enrol_and_unenrol() {
        $mockuser = $this->get_mock_users(1)[0];
        [$course, $resource] = $this->create_test_environment();
        $this->fake_user_launch($resource, $mockuser);

        $mockusers = $this->get_mock_users(6);
        $task = $this->get_mock_task_with_users([
            $mockusers[0],
            $mockusers[1],
            $mockusers[2]
        ]);

        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify 3 users and their corresponding course enrolments exist.
        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(3, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);

        // Now, simulate a subsequent sync in which 1 existing user maintains access,
        // 2 existing users are unenrolled and 3 new users are enrolled.
        $task2 = $this->get_mock_task_with_users([
            $mockusers[0],
            $mockusers[3],
            $mockusers[4],
            $mockusers[5]
        ]);

        ob_start();
        $task2->execute();
        ob_end_clean();

        // Verify the missing users have been unenrolled and new users enrolled.
        $unenrolleduserids = [
            $mockusers[1]['user_id'],
            $mockusers[2]['user_id']
        ];
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(4, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);

        $ltiuserids = array_map(function($user) {
            return $user->get_sourceid();
        }, $ltiusers);
        $this->assertEmpty(array_intersect($unenrolleduserids, $ltiuserids));
    }

    /**
     * Confirm the sync task operation for resources configured as 'helper::MEMBER_SYNC_UNENROL_MISSING'.
     */
    public function test_sync_mode_unenrol_missing() {
        $mockuser = $this->get_mock_users(1)[0];
        [$course, $resource] = $this->create_test_environment(true, true, true, helper::MEMBER_SYNC_UNENROL_MISSING);
        $this->fake_user_launch($resource, $mockuser);

        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(1, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);

        $mockusers = $this->get_mock_users();
        $task = $this->get_mock_task_with_users([
            $mockusers[1],
            $mockusers[2]
        ]);

        ob_start();
        $task->execute();
        ob_end_clean();

        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(0, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);
    }

    /**
     * Confirm the sync task operation for resources configured as 'helper::MEMBER_SYNC_ENROL_NEW'.
     */
    public function test_sync_mode_enrol_new() {
        $mockuser = $this->get_mock_users(1)[0];
        [$course, $resource] = $this->create_test_environment(true, true, true, helper::MEMBER_SYNC_ENROL_NEW);
        $this->fake_user_launch($resource, $mockuser);

        $mockusers = $this->get_mock_users();
        $task = $this->get_mock_task_with_users([
            $mockusers[1],
            $mockusers[2]
        ]);

        ob_start();
        $task->execute();
        ob_end_clean();

        // Verify we now have 3 enrolments. The original user (who was not unenrolled) and the 2 new users.
        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(3, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);
    }

    /**
     * Test confirming that no changes take place if the auth_lti plugin is not enabled.
     */
    public function test_sync_auth_disabled() {
        $mockuser = $this->get_mock_users(1)[0];
        [$course, $resource] = $this->create_test_environment(false);
        $this->fake_user_launch($resource, $mockuser);

        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(1, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);

        // If the task were to run, this would trigger 1 unenrolment (the launching user) and 3 enrolments.
        $mockusers = $this->get_mock_users(4);
        $task = $this->get_mock_task_with_users([
            $mockusers[1],
            $mockusers[2],
            $mockusers[3]
        ]);

        ob_start();
        $task->execute();
        $output = ob_get_contents();
        ob_end_clean();

        // Verify that the sync didn't take place.
        $this->assertStringContainsString("Skipping task - Authentication plugin 'LTI' is not enabled", $output);
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(1, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);
    }

    /**
     * Test confirming that no sync takes place when the enrol_lti plugin is not enabled.
     */
    public function test_sync_enrol_disabled() {
        $mockuser = $this->get_mock_users(1)[0];
        [$course, $resource] = $this->create_test_environment(true, false);
        $this->fake_user_launch($resource, $mockuser);

        $userrepo = new user_repository();
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(1, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);

        // If the task were to run, this would trigger 1 unenrolment of the launching user and enrolment of 3 users.
        $mockusers = $this->get_mock_users(4);
        $task = $this->get_mock_task_with_users([
            $mockusers[1],
            $mockusers[2],
            $mockusers[3]
        ]);

        ob_start();
        $task->execute();
        $output = ob_get_contents();
        ob_end_clean();

        // Verify that the sync didn't take place.
        $this->assertStringContainsString("Skipping task - The 'Publish as LTI tool' plugin is disabled", $output);
        $ltiusers = $userrepo->find_by_resource($resource->id);
        $this->assertCount(1, $ltiusers);
        $this->verify_course_enrolments($course, $ltiusers);
    }
}
