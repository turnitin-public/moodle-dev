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
 * Contains tests for the application_registration_repository.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\local\ltiadvantage\repository;
use enrol_lti\local\ltiadvantage\entity\application_registration;

/**
 * Tests for the application_registration_repository.
 *
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class application_registration_repository_test extends \advanced_testcase {

    /**
     * Setup run for each test case.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Helper to generate a new application_registration object.
     *
     * @return application_registration
     */
    protected function generate_application_registration(): application_registration {
        return application_registration::create(
            'Example LMS application',
            'https://lms.example.org',
            'clientid_123',
            new \moodle_url('https://example.org/authrequesturl'),
            new \moodle_url('https://example.org/jwksurl'),
            new \moodle_url('https://example.org/accesstokenurl')
        );
    }

    /**
     * Helper to assert that all the key elements of two registrations (i.e. excluding id) are equal.
     *
     * @param application_registration $expected the registration whose values are deemed correct.
     * @param application_registration $check the registration to check.
     */
    protected function assert_same_registration_values(application_registration $expected,
            application_registration $check): void {
        $this->assertEquals($expected->get_name(), $check->get_name());
        $this->assertEquals($expected->get_platformid(), $check->get_platformid());
        $this->assertEquals($expected->get_clientid(), $check->get_clientid());
        $this->assertEquals($expected->get_authenticationrequesturl(),
            $check->get_authenticationrequesturl());
        $this->assertEquals($expected->get_jwksurl(), $check->get_jwksurl());
        $this->assertEquals($expected->get_accesstokenurl(), $check->get_accesstokenurl());
    }

    /**
     * Helper to assert that all the key elements of an application_registration are present in the DB.
     *
     * @param application_registration $registration
     */
    protected function assert_registration_db_values(application_registration $registration) {
        global $DB;
        $record = $DB->get_record('enrol_lti_app_registration', ['id' => $registration->get_id()]);
        $this->assertEquals($registration->get_id(), $record->id);
        $this->assertEquals($registration->get_name(), $record->name);
        $this->assertEquals($registration->get_platformid(), $record->platformid);
        $this->assertEquals($registration->get_clientid(), $record->clientid);
        $this->assertEquals($registration->get_authenticationrequesturl(), $record->authenticationrequesturl);
        $this->assertEquals($registration->get_jwksurl(), $record->jwksurl);
        $this->assertEquals($registration->get_accesstokenurl(), $record->accesstokenurl);
        $this->assertNotEmpty($record->timecreated);
        $this->assertNotEmpty($record->timemodified);
    }

    /**
     * Tests adding an application_registration to the repository.
     */
    public function test_save_new() {
        $registration = $this->generate_application_registration();
        $repository = new application_registration_repository();
        $createdregistration = $repository->save($registration);

        $this->assertIsInt($createdregistration->get_id());
        $this->assert_same_registration_values($registration, $createdregistration);
        $this->assert_registration_db_values($createdregistration);
    }

    /**
     * Test saving an application_registration that is already present in the store.
     */
    public function test_save_existing() {
        $testregistration = $this->generate_application_registration();
        $repository = new application_registration_repository();

        $createdregistration = $repository->save($testregistration);
        $createdregistration->set_authenticationrequesturl('https://example.com/NEW_authrequesturl');
        $createdregistration->set_jwksurl('https://example.com/NEW_jwksurl');
        $createdregistration->set_accesstokenurl('https://example.com/NEW_accesstokenurl');
        $updatedregistration = $repository->save($createdregistration);

        $this->assertEquals($createdregistration->get_id(), $updatedregistration->get_id());
        $this->assert_same_registration_values($createdregistration, $updatedregistration);
        $this->assert_registration_db_values($updatedregistration);
    }

    /**
     * Tests trying to persist two as-yet-unpersisted objects having identical makeup.
     */
    public function test_save_duplicate_unique_constraints() {
        $testregistration = $this->generate_application_registration();
        $testregistration2 = $this->generate_application_registration();
        $repository = new application_registration_repository();

        $this->assertInstanceOf(application_registration::class, $repository->save($testregistration));
        $this->expectException(\dml_exception::class);
        $repository->save($testregistration2);
    }

    /**
     * Test finding an application_registration in the repository.
     */
    public function test_find() {
        $testregistration = $this->generate_application_registration();
        $repository = new application_registration_repository();
        $createdregistration = $repository->save($testregistration);
        $foundregistration = $repository->find($createdregistration->get_id());

        $this->assertEquals($createdregistration->get_id(), $foundregistration->get_id());
        $this->assert_same_registration_values($testregistration, $foundregistration);
        $this->assertNull($repository->find(0));
    }

    /**
     * Test checking existence of an application_registration within the repository.
     */
    public function test_exists() {
        $testregistration = $this->generate_application_registration();
        $repository = new application_registration_repository();
        $createdregistration = $repository->save($testregistration);

        $this->assertTrue($repository->exists($createdregistration->get_id()));
        $this->assertFalse($repository->exists(0));
    }
}
