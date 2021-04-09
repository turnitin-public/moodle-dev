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
 * Contains tests for the context_repository.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\local\ltiadvantage\repository;
use enrol_lti\local\ltiadvantage\entity\context;
use enrol_lti\local\ltiadvantage\entity\application_registration;

/**
 * Tests for context_repository.
 *
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class context_repository_testcase extends \advanced_testcase {
    /**
     * Setup run for each test case.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Helper to create test context objects for use with the repository tests.
     *
     * @return context the context.
     */
    protected function create_test_context(): context {
        $registration = application_registration::create(
            'Test',
            'http://lms.example.org',
            'clientid_123',
            new \moodle_url('https://example.org/authrequesturl'),
            new \moodle_url('https://example.org/jwksurl'),
            new \moodle_url('https://example.org/accesstokenurl')
        );
        $registrationrepo = new application_registration_repository();
        $createdregistration = $registrationrepo->save($registration);

        $deployment = $createdregistration->add_tool_deployment('Deployment 1', 'DeployID123');
        $deploymentrepo = new deployment_repository();
        $saveddeployment = $deploymentrepo->save($deployment);

        return $saveddeployment->add_context('CTX123', ['http://purl.imsglobal.org/vocab/lis/v2/course#CourseSection']);
    }

    /**
     * Helper to assert that all the key elements of two contexts (i.e. excluding id) are equal.
     *
     * @param context $expected the context whose values are deemed correct.
     * @param context $check the context to check.
     */
    protected function assert_same_context_values(context $expected, context $check): void {
        $this->assertEquals($expected->get_deploymentid(), $check->get_deploymentid());
        $this->assertEquals($expected->get_contextid(), $check->get_contextid());
        $this->assertEquals($expected->get_types(), $check->get_types());
    }

    /**
     * Helper to assert that all the key elements of a context are present in the DB.
     *
     * @param context $expected the context whose values are deemed correct.
     */
    protected function assert_context_db_values(context $expected) {
        global $DB;
        $checkrecord = $DB->get_record('enrol_lti_context', ['id' => $expected->get_id()]);
        $this->assertEquals($expected->get_id(), $checkrecord->id);
        $this->assertEquals($expected->get_deploymentid(), $checkrecord->deploymentid);
        $this->assertEquals($expected->get_contextid(), $checkrecord->contextid);
        $this->assertEquals(json_encode($expected->get_types()), $checkrecord->type);
        $this->assertNotEmpty($checkrecord->timecreated);
        $this->assertNotEmpty($checkrecord->timemodified);
    }

    /**
     * Test saving a new context.
     */
    public function test_save_new() {
        $context = $this->create_test_context();
        $contextrepo = new context_repository();
        $saved = $contextrepo->save($context);

        $this->assertIsInt($saved->get_id());
        $this->assert_same_context_values($context, $saved);
        $this->assert_context_db_values($saved);
    }

    /**
     * Test saving an existing context.
     */
    public function test_save_existing() {
        $context = $this->create_test_context();
        $contextrepo = new context_repository();
        $saved = $contextrepo->save($context);

        $context2 = $context::create(
            $saved->get_deploymentid(),
            $saved->get_contextid(),
            $saved->get_types(),
            $saved->get_id()
        );
        $saved2 = $contextrepo->save($saved);

        $this->assertEquals($saved->get_id(), $saved2->get_id());
        $this->assert_same_context_values($saved, $saved2);
        $this->assert_context_db_values($saved2);
    }

    /**
     * Test trying to save two contexts with the same id for the same deployment.
     */
    public function test_save_unique_constraints_not_met() {
        $context = $this->create_test_context();
        $context2 = clone $context;

        $contextrepo = new context_repository();
        $saved = $contextrepo->save($context);
        $this->assertInstanceOf(context::class, $saved);

        $this->expectException(\dml_exception::class);
        $contextrepo->save($context2);
    }

    /**
     * Test existence of a context within the repository.
     */
    public function test_exists() {
        $contextrepo = new context_repository();
        $context = $this->create_test_context();
        $savedcontext = $contextrepo->save($context);

        $this->assertTrue($contextrepo->exists($savedcontext->get_id()));
        $this->assertFalse($contextrepo->exists(0));
    }

    /**
     * Test finding a context in the repository.
     */
    public function test_find() {
        $context = $this->create_test_context();
        $contextrepo = new context_repository();
        $savedcontext = $contextrepo->save($context);

        $foundcontext = $contextrepo->find($savedcontext->get_id());
        $this->assertEquals($savedcontext->get_id(), $foundcontext->get_id());
        $this->assert_same_context_values($savedcontext, $foundcontext);
        $this->assertNull($contextrepo->find(0));
    }

    /**
     * Test deleting a context from the repository.
     */
    public function test_delete() {
        $context = $this->create_test_context();
        $contextrepo = new context_repository();
        $savedcontext = $contextrepo->save($context);
        $this->assertTrue($contextrepo->exists($savedcontext->get_id()));

        $contextrepo->delete($savedcontext->get_id());
        $this->assertFalse($contextrepo->exists($savedcontext->get_id()));

        $this->assertNull($contextrepo->delete($savedcontext->get_id()));
    }
}
