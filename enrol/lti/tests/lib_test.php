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
 * Tests for the enrol_lti_plugin class.
 *
 * @package enrol_lti
 * @copyright 2016 Jun Pataleta <jun@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti;

use course_enrolment_manager;
use enrol_lti_plugin;
use IMSGlobal\LTI\ToolProvider\ResourceLink;
use IMSGlobal\LTI\ToolProvider\ToolConsumer;
use IMSGlobal\LTI\ToolProvider\ToolProvider;
use IMSGlobal\LTI\ToolProvider\User;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/local/ltiadvantage/lti_advantage_testcase.php');

/**
 * Tests for the enrol_lti_plugin class.
 *
 * @package enrol_lti
 * @copyright 2016 Jun Pataleta <jun@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lib_test extends \lti_advantage_testcase {

    /**
     * Test set up.
     *
     * This is executed before running any tests in this file.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test for enrol_lti_plugin::delete_instance().
     */
    public function test_delete_instance() {
        global $DB;

        // Create tool enrolment instance.
        $data = new \stdClass();
        $data->enrolstartdate = time();
        $data->secret = 'secret';
        $tool = $this->getDataGenerator()->create_lti_tool($data);

        // Create consumer and related data.
        $dataconnector = new data_connector();
        $consumer = new ToolConsumer('testkey', $dataconnector);
        $consumer->secret = $tool->secret;
        $consumer->ltiVersion = ToolProvider::LTI_VERSION1;
        $consumer->name = 'TEST CONSUMER NAME';
        $consumer->consumerName = 'TEST CONSUMER INSTANCE NAME';
        $consumer->consumerGuid = 'TEST CONSUMER INSTANCE GUID';
        $consumer->consumerVersion = 'TEST CONSUMER INFO VERSION';
        $consumer->enabled = true;
        $consumer->protected = true;
        $consumer->save();

        $resourcelink = ResourceLink::fromConsumer($consumer, 'testresourcelinkid');
        $resourcelink->save();

        $ltiuser = User::fromResourceLink($resourcelink, '');
        $ltiuser->ltiResultSourcedId = 'testLtiResultSourcedId';
        $ltiuser->ltiUserId = 'testuserid';
        $ltiuser->email = 'user1@example.com';
        $ltiuser->save();

        $tp = new tool_provider($tool->id);
        $tp->user = $ltiuser;
        $tp->resourceLink = $resourcelink;
        $tp->consumer = $consumer;
        $tp->map_tool_to_consumer();

        $mappingparams = [
            'toolid' => $tool->id,
            'consumerid' => $tp->consumer->getRecordId()
        ];

        // Check first that the related records exist.
        $this->assertTrue($DB->record_exists('enrol_lti_tool_consumer_map', $mappingparams));
        $this->assertTrue($DB->record_exists('enrol_lti_lti2_consumer', [ 'id' => $consumer->getRecordId() ]));
        $this->assertTrue($DB->record_exists('enrol_lti_lti2_resource_link', [ 'id' => $resourcelink->getRecordId() ]));
        $this->assertTrue($DB->record_exists('enrol_lti_lti2_user_result', [ 'id' => $ltiuser->getRecordId() ]));

        // Perform deletion.
        $enrollti = new enrol_lti_plugin();
        $instance = $DB->get_record('enrol', ['id' => $tool->enrolid]);
        $enrollti->delete_instance($instance);

        // Check that the related records have been deleted.
        $this->assertFalse($DB->record_exists('enrol_lti_tool_consumer_map', $mappingparams));
        $this->assertFalse($DB->record_exists('enrol_lti_lti2_consumer', [ 'id' => $consumer->getRecordId() ]));
        $this->assertFalse($DB->record_exists('enrol_lti_lti2_resource_link', [ 'id' => $resourcelink->getRecordId() ]));
        $this->assertFalse($DB->record_exists('enrol_lti_lti2_user_result', [ 'id' => $ltiuser->getRecordId() ]));

        // Check that the enrolled users and the tool instance has been deleted.
        $this->assertFalse($DB->record_exists('enrol_lti_users', [ 'toolid' => $tool->id ]));
        $this->assertFalse($DB->record_exists('enrol_lti_tools', [ 'id' => $tool->id ]));
        $this->assertFalse($DB->record_exists('enrol', [ 'id' => $instance->id ]));
    }

    /**
     * Test confirming that relevant data is removed after enrol instance removal.
     */
    public function test_delete_instance_lti_advantage() {
        // Setup.
        [
            $course,
            $modresource,
            $modresource2,
            $courseresource,
            $registration,
            $deployment
        ] = $this->create_test_environment();

        // Generate the legacy data, on which the user migration is based.
        $legacydata = [
            'users' => [
                ['user_id' => '123-abc'],
                ['user_id' => '234-def'],
            ],
            'consumer_key' => 'CONSUMER_1',
            'tools' => [
                ['secret' => 'toolsecret1'],
                ['secret' => 'toolsecret2'],
            ]
        ];
        [$legacytools, $legacyconsumer, $legacyusers] = $this->setup_legacy_data($course, $legacydata);

        // Launch the tool, including a change in user ids which will result in map records.
        $mockuser = $this->get_mock_launch_users_with_ids(['1p3_1'])[0];
        $migrationclaiminfo = [
            'consumer_key' => 'CONSUMER_1',
            'signing_secret' => 'toolsecret1',
            'user_id' => '123-abc',
            'context_id' => 'd345b',
            'tool_consumer_instance_guid' => '12345-123',
            'resource_link_id' => '4b6fa'
        ];
        $mocklaunch = $this->get_mock_launch($modresource, $mockuser, null, true, true, $migrationclaiminfo);
        $launchservice = $this->get_tool_launch_service();
        $launchservice->user_launches_tool($mocklaunch);

        // Verify data exists.
        global $DB;
        $this->assertEquals(1, $DB->count_records('enrol_lti_adv_user'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_user_resource_link'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_resource_link'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_app_registration'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_deployment'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_context'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_users'));

        // Now delete the enrol instance.
        $enrollti = new enrol_lti_plugin();
        $instance = $DB->get_record('enrol', ['id' => $modresource->enrolid]);
        $enrollti->delete_instance($instance);

        $this->assertEquals(0, $DB->count_records('enrol_lti_user_resource_link'));
        $this->assertEquals(0, $DB->count_records('enrol_lti_resource_link'));
        $this->assertEquals(0, $DB->count_records('enrol_lti_users'));

        // App registration, Deployment, Context and the LTI Adv User tables are not affected by instance removal.
        $this->assertEquals(1, $DB->count_records('enrol_lti_adv_user')); // Records here share lifecycle of the user.
        $this->assertEquals(1, $DB->count_records('enrol_lti_app_registration'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_deployment'));
        $this->assertEquals(1, $DB->count_records('enrol_lti_context'));
    }

    /**
     * Test for getting user enrolment actions.
     */
    public function test_get_user_enrolment_actions() {
        global $CFG, $DB, $PAGE;
        $this->resetAfterTest();

        // Set page URL to prevent debugging messages.
        $PAGE->set_url('/enrol/editinstance.php');

        $pluginname = 'lti';

        // Only enable the lti enrol plugin.
        $CFG->enrol_plugins_enabled = $pluginname;

        $generator = $this->getDataGenerator();

        // Get the enrol plugin.
        $plugin = enrol_get_plugin($pluginname);

        // Create a course.
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

        // Enable this enrol plugin for the course.
        $fields = ['contextid' => $context->id, 'roleinstructor' => $teacherroleid, 'rolelearner' => $studentroleid];
        $plugin->add_instance($course, $fields);

        // Create a student.
        $student = $generator->create_user();
        // Enrol the student to the course.
        $generator->enrol_user($student->id, $course->id, 'student', $pluginname);

        // Teachers don't have enrol/lti:unenrol capability by default. Login as admin for simplicity.
        $this->setAdminUser();

        require_once($CFG->dirroot . '/enrol/locallib.php');
        $manager = new course_enrolment_manager($PAGE, $course);
        $userenrolments = $manager->get_user_enrolments($student->id);
        $this->assertCount(1, $userenrolments);

        $ue = reset($userenrolments);
        $actions = $plugin->get_user_enrolment_actions($manager, $ue);
        // LTI enrolment has 1 enrol actions for active users -- unenrol.
        $this->assertCount(1, $actions);
    }
}
