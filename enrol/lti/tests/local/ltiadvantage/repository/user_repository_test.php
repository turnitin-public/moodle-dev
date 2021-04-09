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
 * Test the user_repository objects.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\local\ltiadvantage\repository;
use enrol_lti\local\ltiadvantage\entity\application_registration;
use enrol_lti\local\ltiadvantage\entity\user;

/**
 * Tests for user_repository objects.
 *
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_repository_testcase extends \advanced_testcase {
    /**
     * Setup run for each test case.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Helper to generate a new user instance.
     *
     * @return user
     */
    protected function generate_user(): user {
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

        $contextrepo = new context_repository();
        $context = $saveddeployment->add_context(
            'CTX123',
            ['http://purl.imsglobal.org/vocab/lis/v2/course#CourseSection']
        );
        $savedcontext = $contextrepo->save($context);

        $resourcelinkrepo = new resource_link_repository();
        $resourcelink = $saveddeployment->add_resource_link('resourcelinkid_123', $savedcontext->get_id());
        $savedresourcelink = $resourcelinkrepo->save($resourcelink);

        return $savedresourcelink->add_user(
            'source-id-123',
            'Simon',
            'McTest',
            'unique-user-name',
            'en',
            'simon@example.com',
            'Perth',
            'AU',
            'An Example Institution',
            '99',
            2,
        );
    }

    /**
     * Helper to assert that all the key elements of two users (i.e. excluding id) are equal.
     *
     * @param user $expected the user whose values are deemed correct.
     * @param user $check the user to check.
     * @param bool $checkresourcelink whether or not to confirm the resource link value matches too.
     */
    protected function assert_same_user_values(user $expected, user $check, bool $checkresourcelink = false): void {
        $this->assertEquals($expected->get_username(), $check->get_username());
        $this->assertEquals($expected->get_firstname(), $check->get_firstname());
        $this->assertEquals($expected->get_lastname(), $check->get_lastname());
        $this->assertEquals($expected->get_email(), $check->get_email());
        $this->assertEquals($expected->get_city(), $check->get_city());
        $this->assertEquals($expected->get_country(), $check->get_country());
        $this->assertEquals($expected->get_institution(), $check->get_institution());
        $this->assertEquals($expected->get_timezone(), $check->get_timezone());
        $this->assertEquals($expected->get_maildisplay(), $check->get_maildisplay());
        $this->assertEquals($expected->get_mnethostid(), $check->get_mnethostid());
        $this->assertEquals($expected->get_confirmed(), $check->get_confirmed());
        $this->assertEquals($expected->get_lang(), $check->get_lang());
        $this->assertEquals($expected->get_auth(), $check->get_auth());
        if ($checkresourcelink) {
            $this->assertEquals($expected->get_resourcelinkid(), $check->get_resourcelinkid());
        }
    }

    /**
     * Helper to assert that all the key elements of a user are present in the DB.
     *
     * @param user $expected the user whose values are deemed correct.
     */
    protected function assert_user_db_values(user $expected) {
        global $DB;
        $sql = "SELECT u.username, u.firstname, u.lastname, u.email, u.city, u.country, u.institution, u.timezone,
                       u.maildisplay, u.mnethostid, u.confirmed, u.lang, u.auth
                  FROM {enrol_lti_users} lu
                  JOIN {user} u
                    ON (lu.userid = u.id)
                 WHERE lu.id = :id";
        $userrecord = $DB->get_record_sql($sql, ['id' => $expected->get_id()]);
        $this->assertEquals($expected->get_username(), $userrecord->username);
        $this->assertEquals($expected->get_firstname(), $userrecord->firstname);
        $this->assertEquals($expected->get_lastname(), $userrecord->lastname);
        $this->assertEquals($expected->get_email(), $userrecord->email);
        $this->assertEquals($expected->get_city(), $userrecord->city);
        $this->assertEquals($expected->get_country(), $userrecord->country);
        $this->assertEquals($expected->get_institution(), $userrecord->institution);
        $this->assertEquals($expected->get_timezone(), $userrecord->timezone);
        $this->assertEquals($expected->get_maildisplay(), $userrecord->maildisplay);
        $this->assertEquals($expected->get_mnethostid(), $userrecord->mnethostid);
        $this->assertEquals($expected->get_confirmed(), $userrecord->confirmed);
        $this->assertEquals($expected->get_lang(), $userrecord->lang);
        $this->assertEquals($expected->get_auth(), $userrecord->auth);

        $ltiuserrecord = $DB->get_record('enrol_lti_users', ['id' => $expected->get_id()]);
        $this->assertEquals($expected->get_id(), $ltiuserrecord->id);
        $this->assertEquals($expected->get_sourceid(), $ltiuserrecord->sourceid);
        $this->assertEquals($expected->get_resourceid(), $ltiuserrecord->toolid);
        $this->assertEquals($expected->get_lastgrade(), $ltiuserrecord->lastgrade);

        if ($expected->get_resourcelinkid()) {
            $sql = "SELECT rl.id
                      FROM {enrol_lti_users} lu
                      JOIN {enrol_lti_user_resource_link} rlj
                        ON (lu.id = rlj.userid)
                      JOIN {enrol_lti_resource_link} rl
                        ON (rl.id = rlj.resourcelinkid)
                     WHERE lu.id = :id";
            $resourcelinkrecord = $DB->get_record_sql($sql, ['id' => $expected->get_id()]);
            $this->assertEquals($expected->get_resourcelinkid(), $resourcelinkrecord->id);
        }
    }

    /**
     * Tests adding a user to the store.
     */
    public function test_save_new() {
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);

        $this->assertIsInt($saveduser->get_id());
        $this->assert_same_user_values($user, $saveduser, true);
        $this->assert_user_db_values($saveduser);
    }

    /**
     * Test saving an existing user instance.
     */
    public function test_save_existing() {
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);

        $saveduser->set_firstname('Martin');
        $saveduser->set_firstname('Fowler');
        $saveduser2 = $userrepo->save($saveduser);

        $this->assertEquals($saveduser->get_id(), $saveduser2->get_id());
        $this->assert_same_user_values($saveduser, $saveduser2, true);
        $this->assert_user_db_values($saveduser2);
    }

    /**
     * Verify that trying to save a stale object results in an exception referring to unique constraint violation.
     */
    public function test_save_uniqueness_constraint() {
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);

        $this->expectException(\coding_exception::class);
        $saveduser2 = $userrepo->save($user);
    }

    /**
     * Test finding a user instance by id.
     */
    public function test_find() {
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);

        $founduser = $userrepo->find($saveduser->get_id());
        $this->assertIsInt($founduser->get_id());
        $this->assert_same_user_values($saveduser, $founduser, false);

        $this->assertNull($userrepo->find(0));
    }

    /**
     * Test checking existence of a user instance, based on id.
     */
    public function test_exists() {
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);

        $this->assertTrue($userrepo->exists($saveduser->get_id()));
        $this->assertFalse($userrepo->exists(0));
    }

    /**
     * Test deleting a user instance, based on id.
     */
    public function test_delete() {
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);
        $this->assertTrue($userrepo->exists($saveduser->get_id()));

        $userrepo->delete($saveduser->get_id());
        $this->assertFalse($userrepo->exists($saveduser->get_id()));

        global $DB;
        $this->assertFalse($DB->record_exists('enrol_lti_users', ['id' => $saveduser->get_id()]));
        $this->assertFalse($DB->record_exists('enrol_lti_user_resource_link', ['userid' => $saveduser->get_id()]));
        $this->assertTrue($DB->record_exists('user', ['id' => $saveduser->get_localid()]));

        $this->assertNull($userrepo->delete($saveduser->get_id()));
    }

    /**
     * Confirms localid is a read only convenience property and that changes to this will not impact state.
     */
    public function test_localid_read_only() {
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);
        $this->assertIsInt($saveduser->get_localid());

        $updateuser = user::create(
            $saveduser->get_resourceid(),
            $saveduser->get_deploymentid(),
            $saveduser->get_sourceid(),
            $saveduser->get_firstname(),
            $saveduser->get_lastname(),
            $saveduser->get_username(),
            $saveduser->get_lang(),
            $saveduser->get_email(),
            $saveduser->get_city(),
            $saveduser->get_country(),
            $saveduser->get_institution(),
            $saveduser->get_timezone(),
            $saveduser->get_maildisplay(),
            $saveduser->get_lastgrade(),
            $saveduser->get_lastaccess(),
            12345,
            $saveduser->get_id()
        );

        $saveduser2 = $userrepo->save($updateuser);
        $this->assertEquals($saveduser->get_localid(), $saveduser2->get_localid());

        $founduser = $userrepo->find($saveduser->get_id());
        $this->assertEquals($saveduser->get_localid(), $founduser->get_localid());
    }

    /**
     * Verify a user who has been deleted can be re-saved to the repository and matched to an existing local user.
     */
    public function test_save_deleted() {
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);

        $userrepo->delete($saveduser->get_id());
        $this->assertFalse($userrepo->exists($saveduser->get_id()));

        $saveduser2 = $userrepo->save($user);
        $this->assertEquals($saveduser->get_localid(), $saveduser2->get_localid());
        $this->assertNotEquals($saveduser->get_id(), $saveduser2->get_id());
    }
}
