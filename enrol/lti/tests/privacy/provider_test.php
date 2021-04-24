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
 * Privacy provider tests.
 *
 * @package    enrol_lti
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\privacy;

use enrol_lti\privacy\provider;
use \core_privacy\local\request\transform;


defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider tests class.
 *
 * @package    enrol_lti
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_test extends \core_privacy\tests\provider_testcase {

    /**
     * @var stdClass The user
     */
    protected $user = null;

    /**
     * @var stdClass Another user
     */
    protected $anotheruser = null;

    /**
     * @var stdClass The course
     */
    protected $course = null;

    /**
     * @var stdClass The activity
     */
    protected $activity = null;

    /** @var stdClass The enrol_lti_adv_user record info for user. */
    protected $advinfo1 = null;

    /** @var stdClass The enrol_lti_adv_user record info for otheruser. */
    protected $advinfo2 = null;

    /**
     * Basic setup for these tests.
     */
    public function setUp(): void {
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->user = $this->getDataGenerator()->create_user();
        $this->activity = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);

        // Get the course and activity contexts.
        $coursecontext = \context_course::instance($this->course->id);
        $cmcontext = \context_module::instance($this->activity->cmid);

        // Create LTI tools in different contexts.
        $this->create_lti_users($coursecontext, $this->user->id);
        $this->create_lti_users($coursecontext, $this->user->id);
        $this->create_lti_users($cmcontext, $this->user->id);

        // Create LTI Advantage record for the user.
        $this->advinfo1 = $this->create_lti_advantage_info_for_user($this->user->id);

        // Create another LTI user.
        $this->anotheruser = $this->getDataGenerator()->create_user();
        $this->create_lti_users($coursecontext, $this->anotheruser->id);

        // Create LTI Advantage record for the other user.
        $this->advinfo2 = $this->create_lti_advantage_info_for_user($this->anotheruser->id);
    }

    /**
     * Test getting the context for the user ID related to this plugin.
     */
    public function test_get_contexts_for_userid() {
        $contextlist = provider::get_contexts_for_userid($this->user->id);

        $this->assertCount(3, $contextlist);

        $coursectx = \context_course::instance($this->course->id);
        $activityctx = \context_module::instance($this->activity->cmid);
        $userctx = \context_user::instance($this->user->id);
        $expectedids = [$coursectx->id, $activityctx->id, $userctx->id];

        $actualids = $contextlist->get_contextids();
        $this->assertEqualsCanonicalizing($expectedids, $actualids);
    }

    /**
     * Test for provider::export_user_data().
     */
    public function test_export_for_context() {
        $coursecontext = \context_course::instance($this->course->id);
        $cmcontext = \context_module::instance($this->activity->cmid);
        $usercontext = \context_user::instance($this->user->id);

        // Export all of the data for the course context.
        $this->export_context_data_for_user($this->user->id, $coursecontext, 'enrol_lti');
        $writer = \core_privacy\local\request\writer::with_context($coursecontext);
        $this->assertTrue($writer->has_any_data());

        $data = (array) $writer->get_data(['enrol_lti_users']);
        $this->assertCount(2, $data);
        foreach ($data as $ltiuser) {
            $this->assertArrayHasKey('lastgrade', $ltiuser);
            $this->assertArrayHasKey('timecreated', $ltiuser);
            $this->assertArrayHasKey('timemodified', $ltiuser);
        }

        // Export all of the data for the activity context.
        $this->export_context_data_for_user($this->user->id, $cmcontext, 'enrol_lti');
        $writer = \core_privacy\local\request\writer::with_context($cmcontext);
        $this->assertTrue($writer->has_any_data());

        $data = (array) $writer->get_data(['enrol_lti_users']);
        $this->assertCount(1, $data);
        foreach ($data as $ltiuser) {
            $this->assertArrayHasKey('lastgrade', $ltiuser);
            $this->assertArrayHasKey('timecreated', $ltiuser);
            $this->assertArrayHasKey('timemodified', $ltiuser);
        }

        // Export all of the data for the user context (lti advantage specific information tracking platform user id).
        $this->export_context_data_for_user($this->user->id, $usercontext, 'enrol_lti');
        $writer = \core_privacy\local\request\writer::with_context($usercontext);
        $this->assertTrue($writer->has_any_data());
        $ltiadvantageuserinfo = (array) $writer->get_data([get_string('privacy:ltiadvantageuserpath', 'enrol_lti')]);
        $entry = array_shift($ltiadvantageuserinfo);
        $this->assertEquals($this->advinfo1->issuer, $entry['issuer']);
        $this->assertEquals($this->advinfo1->sub, $entry['sub']);
        $this->assertEquals($this->advinfo1->legacymigrated, $entry['legacymigrated']);
        $this->assertEquals(transform::datetime($this->advinfo1->timecreated), $entry['timecreated']);
        $this->assertEquals(transform::datetime($this->advinfo1->timemodified), $entry['timemodified']);
    }

    /**
     * Test for provider::delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $count = $DB->count_records('enrol_lti_users');
        $this->assertEquals(4, $count);
        $this->assertEquals(2, $DB->count_records('enrol_lti_adv_user'));

        // Delete data based on context.
        $coursecontext = \context_course::instance($this->course->id);
        provider::delete_data_for_all_users_in_context($coursecontext);

        $ltiusers = $DB->get_records('enrol_lti_users');
        $this->assertCount(1, $ltiusers);
        $this->assertEquals(2, $DB->count_records('enrol_lti_adv_user'));

        // Now delete all data within a given user context, confirming the lti advantage data is removed.
        $usercontext = context_user::instance($this->user->id);
        provider::delete_data_for_all_users_in_context($usercontext);
        $this->assertEquals(1, $DB->count_records('enrol_lti_adv_user'));

        $ltiuser = reset($ltiusers);
        $this->assertEquals($ltiuser->userid, $this->user->id);
    }

    /**
     * Test for provider::delete_data_for_user().
     */
    public function test_delete_data_for_user() {
        global $DB;

        $cmcontext = \context_module::instance($this->activity->cmid);
        $coursecontext = \context_course::instance($this->course->id);
        $usercontext = \context_user::instance($this->user->id);

        $count = $DB->count_records('enrol_lti_users');
        $this->assertEquals(4, $count);
        $this->assertEquals(2, $DB->count_records('enrol_lti_adv_user'));

        $contextlist = new \core_privacy\local\request\approved_contextlist($this->user, 'enrol_lti',
            [\context_system::instance()->id, $coursecontext->id, $cmcontext->id, $usercontext->id]);
        provider::delete_data_for_user($contextlist);

        $ltiusers = $DB->get_records('enrol_lti_users');
        $this->assertCount(1, $ltiusers);
        $this->assertEquals(1, $DB->count_records('enrol_lti_adv_user'));

        $ltiuser = reset($ltiusers);
        $this->assertNotEquals($ltiuser->userid, $this->user->id);
    }

    /**
     * Creates a LTI user given the provided context
     *
     * @param context $context
     * @param int $userid
     */
    private function create_lti_users(\context $context, int $userid) {
        global $DB;

        // Create a tool.
        $ltitool = (object) [
            'enrolid' => 5,
            'contextid' => $context->id,
            'roleinstructor' => 5,
            'rolelearner' => 5,
            'timecreated' => time(),
            'timemodified' => time() + DAYSECS
        ];
        $toolid = $DB->insert_record('enrol_lti_tools', $ltitool);

        // Create a user.
        $ltiuser = (object) [
            'userid' => $userid,
            'toolid' => $toolid,
            'lastgrade' => 50,
            'lastaccess' => time() + DAYSECS,
            'timecreated' => time()
        ];
        $DB->insert_record('enrol_lti_users', $ltiuser);
    }

    /**
     * Creates an LTI Advantage launch information record for the given user id.
     *
     * @param int $userid the id of the user to link to.
     * @return stdClass the lti advantage user info record.
     */
    private function create_lti_advantage_info_for_user(int $userid): stdClass {
        global $DB;
        $timenow = time();
        $ltiadvuser = (object) [
            'userid' => $userid,
            'issuer' => 'https://lms.example.org',
            'issuer256' => hash('sha256', 'https://lms.example.org'),
            'sub' => '1ab2-c3d4-'.$userid,
            'sub256' => hash('sha256', '1ab2-c3d4-'.$userid),
            'legacymigrated' => true,
            'timecreated' => $timenow,
            'timemodified' => $timenow
        ];
        $ltiadvuser->id = $DB->insert_record('enrol_lti_adv_user', $ltiadvuser);
        return $ltiadvuser;
    }

    /**
     * Test for provider::get_users_in_context() when the context is a course.
     */
    public function test_get_users_in_context_course() {
        $coursecontext = \context_course::instance($this->course->id);
        $userlist = new \core_privacy\local\request\userlist($coursecontext, 'enrol_lti');
        provider::get_users_in_context($userlist);

        $this->assertEqualsCanonicalizing(
                [$this->user->id, $this->anotheruser->id],
                $userlist->get_userids());
    }

    /**
     * Test for provider::get_users_in_context() when the context is an activity.
     */
    public function test_get_users_in_context_activity() {
        $activityctx = \context_module::instance($this->activity->cmid);
        $userlist = new \core_privacy\local\request\userlist($activityctx, 'enrol_lti');
        provider::get_users_in_context($userlist);

        $this->assertEquals(
                [$this->user->id],
                $userlist->get_userids());
    }

    /**
     * Test for  provider::get_users_in_context() when the context is a user.
     */
    public function test_get_users_in_context_user() {
        $userctx = context_user::instance($this->user->id);
        $userlist = new \core_privacy\local\request\userlist($userctx, 'enrol_lti');
        provider::get_users_in_context($userlist);

        $this->assertEquals([$this->user->id], $userlist->get_userids());
    }

    /**
     * Test for provider::delete_data_for_users() when the context is a course.
     */
    public function test_delete_data_for_users_course() {
        global $DB;

        $coursecontext = \context_course::instance($this->course->id);

        $count = $DB->count_records('enrol_lti_users');
        $this->assertEquals(4, $count);
        $this->assertEquals(2, $DB->count_records('enrol_lti_adv_user'));

        $approveduserlist = new \core_privacy\local\request\approved_userlist($coursecontext, 'enrol_lti',
                [$this->user->id]);
        provider::delete_data_for_users($approveduserlist);

        $ltiusers = $DB->get_records('enrol_lti_users');
        $this->assertCount(2, $ltiusers);
        $this->assertEquals(2, $DB->count_records('enrol_lti_adv_user'));

        foreach ($ltiusers as $ltiuser) {
            $leftover = false;
            if ($ltiuser->userid == $this->user->id) {
                $contextid = $DB->get_field('enrol_lti_tools', 'contextid', ['id' => $ltiuser->toolid]);
                if ($contextid == $coursecontext->id) {
                    $leftover = true;
                }
            }
        }
        $this->assertFalse($leftover);
    }

    /**
     * Test for provider::delete_data_for_users() when the context is an activity.
     */
    public function test_delete_data_for_users_activity() {
        global $DB;

        $cmcontext = \context_module::instance($this->activity->cmid);

        $count = $DB->count_records('enrol_lti_users');
        $this->assertEquals(4, $count);
        $this->assertEquals(2, $DB->count_records('enrol_lti_adv_user'));

        $approveduserlist = new \core_privacy\local\request\approved_userlist($cmcontext, 'enrol_lti',
                [$this->user->id]);
        provider::delete_data_for_users($approveduserlist);

        $ltiusers = $DB->get_records('enrol_lti_users');
        $this->assertCount(3, $ltiusers);
        $this->assertEquals(2, $DB->count_records('enrol_lti_adv_user'));

        foreach ($ltiusers as $ltiuser) {
            $leftover = false;
            if ($ltiuser->userid == $this->user->id) {
                $contextid = $DB->get_field('enrol_lti_tools', 'contextid', ['id' => $ltiuser->toolid]);
                if ($contextid == $cmcontext->id) {
                    $leftover = true;
                }
            }
        }
        $this->assertFalse($leftover);
    }

    /**
     * Test for provider::delete_data_for_users() when the context is a user.
     */
    public function test_delete_data_for_users_user_context() {
        global $DB;

        $usercontext = context_user::instance($this->user->id);

        $count = $DB->count_records('enrol_lti_users');
        $this->assertEquals(4, $count);
        $this->assertEquals(2, $DB->count_records('enrol_lti_adv_user'));

        $approveduserlist = new \core_privacy\local\request\approved_userlist($usercontext, 'enrol_lti',
            [$this->user->id]);
        provider::delete_data_for_users($approveduserlist);

        $ltiusers = $DB->get_records('enrol_lti_users');
        $this->assertCount(4, $ltiusers);
        $this->assertEquals(1, $DB->count_records('enrol_lti_adv_user'));
    }
}
