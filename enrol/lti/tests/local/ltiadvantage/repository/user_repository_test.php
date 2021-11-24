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

namespace enrol_lti\local\ltiadvantage\repository;
use enrol_lti\local\ltiadvantage\entity\application_registration;
use enrol_lti\local\ltiadvantage\entity\user;

/**
 * Tests for user_repository objects.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_repository_test extends \advanced_testcase {
    /**
     * Helper to generate a new user instance.
     *
     * @param int $mockresourceid used to spoof a published resource, to which this user is associated.
     * @return user a user instance
     */
    protected function generate_user(int $mockresourceid = 1): user {
        $registration = application_registration::create(
            'Test',
            new \moodle_url('http://lms.example.org'),
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
        $resourcelink = $saveddeployment->add_resource_link('resourcelinkid_123', $mockresourceid,
            $savedcontext->get_id());
        $savedresourcelink = $resourcelinkrepo->save($resourcelink);

        $user = $this->getDataGenerator()->create_user();
        $ltiuser = $savedresourcelink->add_user(
            $user->id,
            'source-id-123',
            'en',
            'Perth',
            'AU',
            'An Example Institution',
            '99',
            2,
        );

        $ltiuser->set_lastgrade(67.33333333);

        return $ltiuser;
    }

    /**
     * Helper to assert that all the key elements of two users (i.e. excluding id) are equal.
     *
     * @param user $expected the user whose values are deemed correct.
     * @param user $check the user to check.
     * @param bool $checkresourcelink whether or not to confirm the resource link value matches too.
     */
    protected function assert_same_user_values(user $expected, user $check, bool $checkresourcelink = false): void {
        $this->assertEquals($expected->get_deploymentid(), $check->get_deploymentid());
        $this->assertEquals($expected->get_city(), $check->get_city());
        $this->assertEquals($expected->get_country(), $check->get_country());
        $this->assertEquals($expected->get_institution(), $check->get_institution());
        $this->assertEquals($expected->get_timezone(), $check->get_timezone());
        $this->assertEquals($expected->get_maildisplay(), $check->get_maildisplay());
        $this->assertEquals($expected->get_lang(), $check->get_lang());
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
        $this->assertEquals($expected->get_city(), $userrecord->city);
        $this->assertEquals($expected->get_country(), $userrecord->country);
        $this->assertEquals($expected->get_institution(), $userrecord->institution);
        $this->assertEquals($expected->get_timezone(), $userrecord->timezone);
        $this->assertEquals($expected->get_maildisplay(), $userrecord->maildisplay);
        $this->assertEquals($expected->get_lang(), $userrecord->lang);

        $ltiuserrecord = $DB->get_record('enrol_lti_users', ['id' => $expected->get_id()]);
        $this->assertEquals($expected->get_id(), $ltiuserrecord->id);
        $this->assertEquals($expected->get_sourceid(), $ltiuserrecord->sourceid);
        $this->assertEquals($expected->get_resourceid(), $ltiuserrecord->toolid);
        $this->assertEquals($expected->get_lastgrade(), $ltiuserrecord->lastgrade);

        if ($expected->get_resourcelinkid()) {
            $sql = "SELECT rl.id
                      FROM {enrol_lti_users} lu
                      JOIN {enrol_lti_user_resource_link} rlj
                        ON (lu.id = rlj.ltiuserid)
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
        $this->resetAfterTest();
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
        $this->resetAfterTest();
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);

        $saveduser->set_city('New City');
        $saveduser->set_country('NZ');
        $saveduser->set_lastgrade(99.99999999);
        $saveduser2 = $userrepo->save($saveduser);

        $this->assertEquals($saveduser->get_id(), $saveduser2->get_id());
        $this->assert_same_user_values($saveduser, $saveduser2, true);
        $this->assert_user_db_values($saveduser2);
    }

    /**
     * Test trying to save a user with an id that is invalid.
     */
    public function test_save_stale_id() {
        $this->resetAfterTest();
        $instructoruser = $this->getDataGenerator()->create_user();
        $userrepo = new user_repository();
        $user = user::create(
            4,
            $instructoruser->id,
            5,
            'source-id-123',
            'en',
            '99',
            '',
            '',
            '',
            null,
            null,
            null,
            null,
            999999
        );

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage("Cannot save lti user with id '999999'. The record does not exist.");
        $userrepo->save($user);
    }

    /**
     * Test saving a user instance mapped to a legacy moodle user, facilitating account re-use after tool upgrade.
     * // TODO move this test to the auth tests.
     */
    public function test_save_new_migrating_user() {
        /*$this->resetAfterTest();
        global $DB;

        // Represents a Moodle user from a legacy launch.
        $muser = $this->getDataGenerator()->create_user();
        $totalmusers = $DB->count_records('user');

        // Create an lti user, linking them to a user account derived from a legacy launch.
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $user->set_localid($muser->id);
        $saveduser = $userrepo->save($user);

        $this->assertIsInt($saveduser->get_id());
        $this->assert_same_user_values($user, $saveduser, true);

        // Verify the lti user reused the existing moodle user.
        $this->assertEquals($totalmusers, $DB->count_records('user'));
        $this->assertEquals($muser->id, $saveduser->get_localid());
        */
    }

    /**
     * Test saving a user instance which has been associated with a non-existent local account.
     * TODO: this test uses set_localid() which likely isn't going to be used any more. Localid is ALWAYS known, because
     *  of the upfront-style auth. Once the ltiuser constructor has been rewritten to REQUIRE user, we can fix the repo
     *  and remove this test case.
     */
    public function test_save_new_user_linking_failed() {
        /*$this->resetAfterTest();
        global $DB;
        // Represents a Moodle user from a legacy launch.
        $muser = $this->getDataGenerator()->create_user();
        $totalmusers = $DB->count_records('user');
        $legacyuserids = array_column($DB->get_records('user', null, '', 'id'), 'id');

        // Create an lti user, linking them to a user account derived from a legacy launch.
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $user->set_localid(999999);
        $saveduser = $userrepo->save($user);
        $expecteddebugmsg = "Attempt to associate LTI user '{$user->get_sourceid()}' to local user " .
            "'{$user->get_localid()}' failed. The local user could not be found. A new user " .
            "account will be created.";
        $this->assertDebuggingCalled($expecteddebugmsg);

        // Verify the returned lti user resulted in the creation of a new moodle user.
        $this->assertEquals($totalmusers + 1, $DB->count_records('user'));
        $this->assertNotContains($saveduser->get_localid(), $legacyuserids);
        */
    }

    /**
     * Verify that trying to save a stale object results in an exception referring to unique constraint violation.
     */
    public function test_save_uniqueness_constraint() {
        $this->resetAfterTest();
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $userrepo->save($user);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessageMatches("/Cannot create duplicate LTI user '[a-z0-9_]*' for resource '[0-9]*'/");
        $userrepo->save($user);
    }

    /**
     * Verify that trying to save a stale user instance representing a legacy mapped user results in an exception.
     * // TODO remove this test - legacy user mapping is now an auth concern.
     */
    public function test_save_uniqueness_constraint_legacy_mapped_user() {
        /*$this->resetAfterTest();
        // Represents a Moodle user from a legacy launch.
        $muser = $this->getDataGenerator()->create_user();

        // Create an lti user, linking them to a user account derived from a legacy launch.
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $user->set_localid($muser->id);
        $userrepo->save($user);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessageMatches("/Cannot create duplicate LTI user '[a-z0-9_]*' for resource '[0-9]*'/");
        $userrepo->save($user);*/
    }

    /**
     * Test finding a user instance by id.
     */
    public function test_find() {
        $this->resetAfterTest();
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);

        $founduser = $userrepo->find($saveduser->get_id());
        $this->assertIsInt($founduser->get_id());
        $this->assert_same_user_values($saveduser, $founduser, false);

        $this->assertNull($userrepo->find(0));
    }

    /**
     * Test checking that finding a legacy mapped user returns the appropriate user.
     * TODO: remove this. It now offers little value since we're just checking localid, which is always set, regardless
     *  of whether or not it's a legacy mapped user.
     */
    public function test_find_legacy_mapped_user() {
        /*$this->resetAfterTest();
        // Represents a Moodle user from a legacy launch.
        $muser = $this->getDataGenerator()->create_user();

        // Create an lti user, linking them to a user account derived from a legacy launch.
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $user->set_localid($muser->id);
        $saveduser = $userrepo->save($user);

        // Verify the finding the user, by id, returns a user that is mapped to the legacy moodle user.
        $founduser = $userrepo->find($saveduser->get_id());
        $this->assert_same_user_values($saveduser, $founduser);
        $this->assertEquals($muser->id, $founduser->get_localid());*/
    }

    /**
     * Test finding a user by sub.
     * TODO remove this since the method will no longer exist.
     */
    public function test_find_by_sub() {
        /*$this->resetAfterTest();
        $mockresourceid = 25;
        $user = $this->generate_user($mockresourceid);
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);

        $founduser = $userrepo->find_by_sub($saveduser->get_sourceid(), $saveduser->get_issuer(), $mockresourceid);
        $this->assertIsInt($founduser->get_id());
        $this->assert_same_user_values($saveduser, $founduser);

        $this->assertNull($userrepo->find_by_sub('not_present', new \moodle_url('http://bad.example'), $mockresourceid));
        */
    }

    /**
     * Test confirming that finding a legacy mapped user by sub returns information about the legacy user.
     * TODO remove this since the method will no longer exist.
     */
    public function test_find_by_sub_legacy_mapped_user() {
        /*$this->resetAfterTest();
        // Represents a Moodle user from a legacy launch.
        $muser = $this->getDataGenerator()->create_user();

        // Create an lti user, linking them to a user account derived from a legacy launch.
        $mockresourceid = 33;
        $user = $this->generate_user($mockresourceid);
        $userrepo = new user_repository();
        $user->set_localid($muser->id);
        $saveduser = $userrepo->save($user);

        // Verify the finding the user, by id, returns a user that is mapped to the legacy moodle user.
        $founduser = $userrepo->find_by_sub($saveduser->get_sourceid(), $saveduser->get_issuer(), $mockresourceid);
        $this->assert_same_user_values($saveduser, $founduser);
        $this->assertEquals($muser->id, $founduser->get_localid());
        */
    }

    /**
     * Test finding all of users associated with a given published resource.
     */
    public function test_find_by_resource() {
        $this->resetAfterTest();
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);
        $instructoruser = $this->getDataGenerator()->create_user();

        $user2 = user::create(
            $saveduser->get_resourceid(),
            $instructoruser->id,
            $saveduser->get_deploymentid(),
            'another-user-123',
            'en',
            '99',
            'Perth',
            'AU',
            'An Example Institution',
            2
        );
        $saveduser2 = $userrepo->save($user2);
        $savedusers = [$saveduser->get_id() => $saveduser, $saveduser2->get_id() => $saveduser2];

        $foundusers = $userrepo->find_by_resource($saveduser->get_resourceid());
        $this->assertCount(2, $foundusers);
        foreach ($foundusers as $founduser) {
            $this->assert_same_user_values($savedusers[$founduser->get_id()], $founduser);
        }
    }

    /**
     * Test finding all of users associated with a given published resource, including legacy mapped users.
     * // TODO remove this as legacy mapping is now an auth concern.
     */
    public function test_find_by_resource_legacy_mapped() {
        /*$this->resetAfterTest();
        // Represents a Moodle user from a legacy launch.
        $muser = $this->getDataGenerator()->create_user();

        // Create an lti user, linking them to a user account derived from a legacy launch.
        $mockresourceid = 33;
        $user = $this->generate_user($mockresourceid);
        $userrepo = new user_repository();
        $user->set_localid($muser->id);
        $saveduser = $userrepo->save($user);

        $user2 = user::create(
            $saveduser->get_resourceid(),
            $saveduser->get_issuer(),
            $saveduser->get_deploymentid(),
            'another-user-123',
            'Another',
            'User',
            'en',
            '99',
            'simon@example.com',
            'Perth',
            'AU',
            'An Example Institution',
            2
        );
        $saveduser2 = $userrepo->save($user2);
        $savedusers = [$saveduser->get_id() => $saveduser, $saveduser2->get_id() => $saveduser2];

        $foundusers = $userrepo->find_by_resource($saveduser->get_resourceid());
        $this->assertCount(2, $foundusers);
        foreach ($foundusers as $founduser) {
            $this->assert_same_user_values($savedusers[$founduser->get_id()], $founduser);
            if ($founduser->get_id() == $saveduser->get_id()) {
                // Verify the user is mapped to the legacy moodle user.
                $this->assertEquals($muser->id, $founduser->get_localid());
            }
        }*/
    }

    /**
     * Test that users can be found based on their resource_link association.
     */
    public function test_find_by_resource_link() {
        $this->resetAfterTest();
        $user = $this->generate_user();
        $user->set_resourcelinkid(33);
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);

        $instructoruser = $this->getDataGenerator()->create_user();
        $user2 = user::create(
            $saveduser->get_resourceid(),
            $instructoruser->id,
            $saveduser->get_deploymentid(),
            'another-user-123',
            'en',
            '99',
            'Perth',
            'AU',
            'An Example Institution',
            2,
            null,
            null,
            33
        );
        $saveduser2 = $userrepo->save($user2);
        $savedusers = [$saveduser->get_id() => $saveduser, $saveduser2->get_id() => $saveduser2];

        $foundusers = $userrepo->find_by_resource_link(33);
        $this->assertCount(2, $foundusers);
        foreach ($foundusers as $founduser) {
            $this->assert_same_user_values($savedusers[$founduser->get_id()], $founduser);
        }
    }

    /**
     * Test checking existence of a user instance, based on id.
     */
    public function test_exists() {
        $this->resetAfterTest();
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);

        $this->assertTrue($userrepo->exists($saveduser->get_id()));
        $this->assertFalse($userrepo->exists(-50));
    }

    /**
     * Test deleting a user instance, based on id.
     */
    public function test_delete() {
        $this->resetAfterTest();
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);
        $this->assertTrue($userrepo->exists($saveduser->get_id()));

        $userrepo->delete($saveduser->get_id());
        $this->assertFalse($userrepo->exists($saveduser->get_id()));

        global $DB;
        $this->assertFalse($DB->record_exists('enrol_lti_users', ['id' => $saveduser->get_id()]));
        $this->assertFalse($DB->record_exists('enrol_lti_user_resource_link', ['ltiuserid' => $saveduser->get_id()]));
        $this->assertTrue($DB->record_exists('user', ['id' => $saveduser->get_localid()]));

        $this->assertNull($userrepo->delete($saveduser->get_id()));
    }

    /**
     * Test deleting a collection of lti user instances by deployment.
     */
    public function test_delete_by_deployment() {
        $this->resetAfterTest();
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);
        $instructoruser = $this->getDataGenerator()->create_user();
        $instructor2user = $this->getDataGenerator()->create_user();

        $user2 = user::create(
            $saveduser->get_resourceid(),
            $instructoruser->id,
            $saveduser->get_deploymentid(),
            'another-user-123',
            'en',
            '99',
            'Perth',
            'AU',
            'An Example Institution',
        );
        $saveduser2 = $userrepo->save($user2);

        $user3 = user::create(
            $saveduser->get_resourceid(),
            $instructor2user->id,
            $saveduser->get_deploymentid() + 1,
            'another-user-678',
            'en',
            '99',
            'Melbourne',
            'AU',
            'An Example Institution',
        );
        $saveduser3 = $userrepo->save($user3);
        $this->assertTrue($userrepo->exists($saveduser->get_id()));
        $this->assertTrue($userrepo->exists($saveduser2->get_id()));
        $this->assertTrue($userrepo->exists($saveduser3->get_id()));

        $userrepo->delete_by_deployment($saveduser->get_deploymentid());
        $this->assertFalse($userrepo->exists($saveduser->get_id()));
        $this->assertFalse($userrepo->exists($saveduser2->get_id()));
        $this->assertTrue($userrepo->exists($saveduser3->get_id()));
    }

    /**
     * Confirms localid is can only be used when lti users don't already exist, i.e. a one time use mapping mechanism.
     */
    public function test_localid_insertion_only() {
        $this->resetAfterTest();
        $user = $this->generate_user();
        $userrepo = new user_repository();
        $saveduser = $userrepo->save($user);
        $this->assertIsInt($saveduser->get_localid());

        $saveduser->set_localid(12345);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessageMatches("/Cannot update user mapping. LTI user '[0-9]*' is already mapped /");
        $userrepo->save($saveduser);
    }

    /**
     * Verify a user who has been deleted can be re-saved to the repository and matched to an existing local user.
     */
    public function test_save_deleted() {
        $this->resetAfterTest();
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
