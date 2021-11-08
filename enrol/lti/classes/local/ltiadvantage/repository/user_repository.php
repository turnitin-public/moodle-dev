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
use enrol_lti\local\ltiadvantage\entity\user;

/**
 * Class user_repository.
 *
 * This class encapsulates persistence logic for \enrol_lti\local\entity\user type objects.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_repository {

    /** @var string $ltiuserstable the name of the table to which the entity will be persisted.*/
    private $ltiuserstable = 'enrol_lti_users';

    /** @var string $userresourcelinkidtable the name of the join table mapping users to resource links.*/
    private $userresourcelinkidtable = 'enrol_lti_user_resource_link';

    /** @var string name of the table which stores additional information about lti advantage users. */
    private $ltiadvantageusertable = 'enrol_lti_adv_user';

    /**
     * Convert a record into a user object and return it.
     *
     * @param \stdClass $userrecord the raw data from relevant tables required to instantiate a user.
     * @return user a user object.
     */
    private function user_from_record(\stdClass $userrecord): user {
        return user::create(
            $userrecord->toolid,
            new \moodle_url($userrecord->issuer),
            $userrecord->ltideploymentid,
            $userrecord->sourceid,
            $userrecord->firstname,
            $userrecord->lastname,
            $userrecord->lang,
            $userrecord->timezone,
            $userrecord->email,
            $userrecord->city,
            $userrecord->country,
            $userrecord->institution,
            $userrecord->maildisplay,
            $userrecord->lastgrade,
            $userrecord->lastaccess,
            $userrecord->resourcelinkid ?? null,
            (int) $userrecord->localid,
            (int) $userrecord->id
        );
    }

    /**
     * Create a list of user instances from a list of records.
     *
     * @param array $records the array of records.
     * @return array of user instances.
     */
    private function users_from_records(array $records): array {
        $users = [];
        foreach ($records as $record) {
            $users[] = $this->user_from_record($record);
        }
        return $users;
    }

    /**
     * Get a stdClass object ready for persisting, based on the supplied user object.
     *
     * @param user $user the user instance.
     * @return \stdClass the record.
     */
    private function user_record_from_user(user $user): \stdClass {
        $record = [
            'firstname' => $user->get_firstname(),
            'lastname' => $user->get_lastname(),
            'email' => $user->get_email(),
            'city' => $user->get_city(),
            'country' => $user->get_country(),
            'institution' => $user->get_institution(),
            'timezone' => $user->get_timezone(),
            'maildisplay' => $user->get_maildisplay(),
            'mnethostid' => $user->get_mnethostid(),
            'confirmed' => $user->get_confirmed(),
            'lang' => $user->get_lang(),
            'auth' => $user->get_auth(),
        ];

        if (!is_null($localid = $user->get_localid())) {
            $record['id'] = $localid;
        }

        return (object) $record;
    }

    /**
     * Create the corresponding enrol_lti_user record from a user instance.
     *
     * @param user $user the user instance.
     * @return \stdClass the record.
     */
    private function lti_user_record_from_user(user $user): \stdClass {
        $record = [
            'toolid' => $user->get_resourceid(),
            'ltideploymentid' => $user->get_deploymentid(),
            'sourceid' => $user->get_sourceid(),
            'lastgrade' => $user->get_lastgrade(),
            'lastaccess' => $user->get_lastaccess(),
        ];
        if ($user->get_id()) {
            $record['id'] = $user->get_id();
        }

        return (object) $record;
    }

    /**
     * Get the lti advantage user specific information from the user object.
     *
     * @param user $user the user instance.
     * @return \stdClass the lti advantage specific user info record.
     */
    private function lti_advantage_user_record_from_user(user $user): \stdClass {
        return (object) [
            'issuer' => $user->get_issuer()->out(false),
            'issuer256' => hash('sha256', $user->get_issuer()),
            'sub' => $user->get_sourceid(),
            'sub256' => hash('sha256', $user->get_sourceid()),
            'legacymigrated' => (bool)$user->get_localid(),
        ];
    }

    /**
     * Helper to validate user:tool uniqueness across a deployment.
     *
     * The DB cannot be relied on to do this uniqueness check, since the table is shared by LTI 1.1/2.0 data.
     *
     * @param user $user the user instance.
     * @return bool true if found, false otherwise.
     */
    private function user_exists_for_tool(user $user): bool {
        // Lack of an id doesn't preclude the object from existence in the store. It may be stale, without an id.
        // The user can still be found by checking their lti advantage user creds and correlating that to the relevant
        // lti_user entry (where tool matches the user object's resource).
        global $DB;
        $isskey = hash('sha256', $user->get_issuer());
        $subkey = hash('sha256', $user->get_sourceid());
        $uniquesql = "SELECT lu.id
                        FROM {{$this->ltiuserstable}} lu
                        JOIN {{$this->ltiadvantageusertable}} lau
                          ON (lau.userid = lu.userid)
                       WHERE lau.issuer256 = :issuer256
                         AND lau.sub256 = :sub256
                         AND lu.toolid = :toolid";
        $params = ['issuer256' => $isskey, 'sub256' => $subkey, 'toolid' => $user->get_resourceid()];
        return $DB->record_exists_sql($uniquesql, $params);
    }

    /**
     * Save a user instance in the store.
     *
     * @param user $user the object to save.
     * @return user the saved object.
     */
    public function save(user $user): user {
        global $DB;
        $id = $user->get_id();
        $exists = !is_null($id) && $this->exists($id);
        if ($id && !$exists) {
            throw new \coding_exception("Cannot save lti user with id '{$id}'. The record does not exist.");
        }

        $userrecord = $this->user_record_from_user($user);
        $ltiuserrecord = $this->lti_user_record_from_user($user);
        $ltiadvantageuserrecord = $this->lti_advantage_user_record_from_user($user);
        $timenow = time();
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        if ($exists) {
            $ltiuser = $DB->get_record($this->ltiuserstable, ['id' => $ltiuserrecord->id]);
            $userid = $ltiuser->userid;
            // Warn about localid vs ltiuser->userid mismatches here. Callers shouldn't be able to force updates using
            // localid. Only new user associations can be created that way.
            if (!empty($userrecord->id) && $userid != $userrecord->id) {
                throw new \coding_exception("Cannot update user mapping. Lti user '{$ltiuser->id}' is already mapped " .
                    "to user '{$ltiuser->userid}' and can't be associated with another user '{$userrecord->id}'.");
            }

            $userrecord->timemodified = $timenow;
            $ltiuserrecord->timemodified = $timenow;

            \user_update_user($userrecord);
            unset($userrecord->id);

            $DB->update_record($this->ltiuserstable, $ltiuserrecord);
        } else {
            // This is a new LTI advantage user. Make a username for LTI Advantage.
            $userrecord->username = 'enrol_lti_13_' . sha1($user->get_issuer() . '_' . $user->get_sourceid());

            // Validate uniqueness of the lti user, in the case of a stale object coming in to be saved.
            if ($this->user_exists_for_tool($user)) {
                throw new \coding_exception("Cannot create duplicate lti user '{$userrecord->username}' for resource " .
                    "'{$user->get_resourceid()}'.");
            }

            // The user record may already exist based on prior user saves, despite the lti user not being present.
            // Attempt to find this user, based on the unique lti advantage user identifier.
            $advantageuserrecordexists = false;
            if (!isset($userrecord->id)) {
                $userid = $DB->get_field($this->ltiadvantageusertable, 'userid',
                    ['issuer' => $user->get_issuer()->out(false), 'sub' => $user->get_sourceid()]);
                if ($userid !== false) {
                    $advantageuserrecordexists = true;
                    $userrecord->id = $userid;
                }
            }

            // Create/modify the user record holding lti user details.
            if (isset($userrecord->id)) {
                // A local user record id is defined. Make sure it exists before updating.
                if (!$DB->record_exists('user', ['id' => $userrecord->id])) {
                    \debugging("Attempt to associate LTI user '{$user->get_sourceid()}' to local user " .
                        "'{$userrecord->id}' failed. The local user could not be found. A new user " .
                        "account will be created.");
                    unset($userrecord->id);
                    $userid = \user_create_user($userrecord);
                } else {
                    $userid = $userrecord->id; // Never update username, only insert.
                    unset($userrecord->username);
                    \user_update_user($userrecord);
                    unset($userrecord->id);
                }
            } else {
                $userid = \user_create_user($userrecord);
            }

            // Create the lti_user record, holding details that have a lifespan equal to that of the enrolment instance.
            $ltiuserrecord->timecreated = $ltiuserrecord->timemodified = $timenow;
            $ltiuserrecord->userid = $userid;
            $ltiuserrecord->id = $DB->insert_record($this->ltiuserstable, $ltiuserrecord);

            // Create the lti_adv_user record holding platform details of the user and having a lifespan equal to that
            // of the user record itself.
            if (!$advantageuserrecordexists && !$DB->record_exists($this->ltiadvantageusertable,
                    ['issuer' => $user->get_issuer()->out(false), 'sub' => $user->get_sourceid()])) {
                $ltiadvantageuserrecord->timecreated = $ltiadvantageuserrecord->timemodified = $timenow;
                $ltiadvantageuserrecord->userid = $userid;
                $DB->insert_record($this->ltiadvantageusertable, $ltiadvantageuserrecord);
            }
        }

        // If the user was created via a resource_link, create that association.
        if ($reslinkid = $user->get_resourcelinkid()) {
            $resourcelinkmap = ['ltiuserid' => $ltiuserrecord->id, 'resourcelinkid' => $reslinkid];
            if (!$DB->record_exists($this->userresourcelinkidtable, $resourcelinkmap)) {
                $DB->insert_record($this->userresourcelinkidtable, $resourcelinkmap);
            }
        }
        $resourcelinkmap = $resourcelinkmap ?? [];

        // Transform the data into something that looks like a read and can be processed by user_from_record.
        $record = (object) array_merge(
            (array) $userrecord,
            (array) $ltiuserrecord,
            (array) $ltiadvantageuserrecord,
            $resourcelinkmap,
            ['localid' => $userid]
        );

        return $this->user_from_record($record);
    }

    /**
     * Find and return a user by id.
     *
     * @param int $id the id of the user object.
     * @return user|null the user object, or null if the object cannot be found.
     */
    public function find(int $id): ?user {
        global $DB;
        try {
            $sql = "SELECT lu.id, u.id as localid, u.username, u.firstname, u.lastname, u.email, u.city, u.country,
                           u.institution, u.timezone, u.maildisplay, u.lang, lu.sourceid, lu.toolid, lu.lastgrade,
                           lu.lastaccess, lu.ltideploymentid, lau.sub, lau.issuer, lau.legacymigrated
                      FROM {enrol_lti_users} lu
                      JOIN {user} u
                        ON (u.id = lu.userid)
                      JOIN {{$this->ltiadvantageusertable}} lau
                        ON (lau.userid = lu.userid)
                     WHERE lu.id = :id";

            $record = $DB->get_record_sql($sql, ['id' => $id], MUST_EXIST);
            return $this->user_from_record($record);
        } catch (\dml_missing_record_exception $ex) {
            return null;
        }
    }

    /**
     * Find an lti user by the unique identifier they have been issued on the issuer/platform and for a given resource.
     *
     * @param string $sub the user id on the issuer/platform.
     * @param \moodle_url $issuer the issuer domain in which the sub id is unique.
     * @param int $resourceid the id of the published resource to which the lti user is associated.
     * @return user|null the user instance or null if not found.
     */
    public function find_by_sub(string $sub, \moodle_url $issuer, int $resourceid): ?user {
        global $DB;
        try {
            // Find the lti advantage user record.
            $sql = "SELECT lu.id, u.id as localid, u.username, u.firstname, u.lastname, u.email, u.city, u.country,
                           u.institution, u.timezone, u.maildisplay, u.lang, lu.sourceid, lu.toolid, lu.lastgrade,
                           lu.lastaccess, lu.ltideploymentid, lau.sub, lau.issuer, lau.legacymigrated
                      FROM {{$this->ltiadvantageusertable}} lau
                      JOIN {enrol_lti_users} lu
                        ON (lau.userid = lu.userid)
                      JOIN {user} u
                        ON (u.id = lau.userid)
                     WHERE lau.sub = :sub
                       AND lau.issuer = :issuer
                       AND lu.toolid = :resourceid";

            $params = ['sub' => $sub, 'issuer' => $issuer->out(false), 'resourceid' => $resourceid];
            $record = $DB->get_record_sql($sql, $params, MUST_EXIST);
            return $this->user_from_record($record);
        } catch (\dml_missing_record_exception $ex) {
            return null;
        }
    }

    /**
     * Find all users for a particular shared resource.
     *
     * @param int $resourceid the id of the shared resource.
     * @return array the array of users, empty if none were found.
     */
    public function find_by_resource(int $resourceid): array {
        global $DB;
        $sql = "SELECT lu.id, u.id as localid, u.username, u.firstname, u.lastname, u.email, u.city, u.country,
                       u.institution, u.timezone, u.maildisplay, u.lang, lu.sourceid, lu.toolid, lu.lastgrade,
                       lu.lastaccess, lu.ltideploymentid, lau.sub, lau.issuer, lau.legacymigrated
                  FROM {enrol_lti_users} lu
                  JOIN {{$this->ltiadvantageusertable}} lau
                    ON (lau.userid = lu.userid)
                  JOIN {user} u
                    ON (u.id = lu.userid)
                 WHERE lu.toolid = :resourceid
              ORDER BY lu.lastaccess DESC";

        $records = $DB->get_records_sql($sql, ['resourceid' => $resourceid]);
        return $this->users_from_records($records);
    }

    /**
     * Get a list of users associated with the given resource link.
     *
     * @param int $resourcelinkid the id of the resource_link instance with which the users are associated.
     * @return array the array of users, empty if none were found.
     */
    public function find_by_resource_link(int $resourcelinkid) {
        global $DB;
        $sql = "SELECT lu.id, u.id as localid, u.username, u.firstname, u.lastname, u.email, u.city, u.country,
                       u.institution, u.timezone, u.maildisplay, u.lang, lu.sourceid, lu.toolid, lu.lastgrade,
                       lu.lastaccess, lu.ltideploymentid, lau.sub, lau.issuer, lau.legacymigrated
                  FROM {enrol_lti_users} lu
                  JOIN {{$this->ltiadvantageusertable}} lau
                    ON (lau.userid = lu.userid)
                  JOIN {user} u
                    ON (u.id = lu.userid)
                  JOIN {{$this->userresourcelinkidtable}} url
                    ON (url.ltiuserid = lu.id)
                 WHERE url.resourcelinkid = :resourcelinkid
              ORDER BY lu.lastaccess DESC";

        $records = $DB->get_records_sql($sql, ['resourcelinkid' => $resourcelinkid]);
        return $this->users_from_records($records);
    }

    /**
     * Check whether or not the given user object exists.
     *
     * @param int $id the unique id the user.
     * @return bool true if found, false otherwise.
     */
    public function exists(int $id): bool {
        global $DB;
        return $DB->record_exists($this->ltiuserstable, ['id' => $id]);
    }

    /**
     * Delete a user based on id.
     *
     * @param int $id the id of the user to remove.
     */
    public function delete(int $id) {
        global $DB;
        $DB->delete_records($this->ltiuserstable, ['id' => $id]);
        $DB->delete_records($this->userresourcelinkidtable, ['ltiuserid' => $id]);
    }

    /**
     * Delete all lti user instances based on a given local deployment instance id.
     *
     * @param int $deploymentid the local id of the deployment instance to which the users belong.
     */
    public function delete_by_deployment(int $deploymentid): void {
        global $DB;
        $DB->delete_records($this->ltiuserstable, ['ltideploymentid' => $deploymentid]);
    }
}
