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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/authlib.php');

/**
 * LTI Authentication plugin.
 *
 * @package auth_lti
 * @copyright 2016 Mark Nelson <markn@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_plugin_lti extends \auth_plugin_base {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->authtype = 'lti';
    }

    /**
     * Users can not log in via the traditional login form.
     *
     * @param string $username The username
     * @param string $password The password
     * @return bool Authentication success or failure
     */
    public function user_login($username, $password) {
        return false;
    }

    /**
     * Authenticate the user based on the unique {iss, sub} tuple present in the LTI Advantage launch.
     *
     * TODO Add better docs explaining the path through this.
     *
     * @param array $launchdata the JWT data provided in the link launch.
     * @param moodle_url $returnurl the local URL to return to if authentication workflows are required.
     * @throws moodle_exception if the user account binding could not be found and assuming we don't want to create one.
     */
    public function complete_login(array $launchdata, moodle_url $returnurl): void {
        if ($binduser = $this->get_user_binding($launchdata['iss'], $launchdata['sub'])) {
            complete_user_login(\core_user::get_user((int) $binduser));
        } else {
            // We don't have an account binding but we'd like to create one.
            global $SESSION;
            $SESSION->auth_lti = (object) ['launchdata' => $launchdata, 'returnurl' => $returnurl];
            redirect(new moodle_url('/auth/lti/login.php', [
                'sesskey' => sesskey(),
            ]));
        }
    }


    /**
     * Create a new user account based on the launch data from the id_token JWT.
     *
     * @param array $launchdata the data from the launch
     * @return stdClass a complete user record.
     */
    public function create_new_account(array $launchdata): stdClass {

        global $CFG;
        require_once($CFG->dirroot.'/user/profile/lib.php');
        require_once($CFG->dirroot.'/user/lib.php');

        $user = new stdClass();
        $user->username = 'enrol_lti_13_' . sha1($launchdata['iss'] . '_' . $launchdata['sub']);
        // If the email was stripped/not set then fill it with a default one.
        // This stops the user from being redirected to edit their profile page.
        $email = $launchdata['email'] ?:
            'enrol_lti_13_' . sha1($launchdata['iss'] . '_' . $launchdata['sub']) . "@example.com";
        $email = \core_user::clean_field($email, 'email');
        $user->email = $email;
        $user->auth = 'lti';
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->firstname = $launchdata['given_name'] ?? $launchdata['sub'];
        $user->lastname = $launchdata['family_name'] ?? $launchdata['iss'];
        $user->password = '';
        $user->confirmed = 1;
        $user->id = user_create_user($user, false);

        // Link this user with the LTI credentials for future use.
        $this->create_user_binding($launchdata['iss'], $launchdata['sub'], $user->id);

        return (object) get_complete_user_data('id', $user->id);
    }

    /**
     * Create a user binding between the {issuer, sub} tuple and the user id.
     *
     * @param string $issuer the issuer URL identifying the platform.
     * @param string $sub the sub string identifying the user on the platform.
     * @param int $userid the Moodle user id.
     */
    public function create_user_binding(string $issuer, string $sub, int $userid): void {
        global $DB;

        $timenow = time();
        $issuer256 = hash('sha256', $issuer);
        $sub256 = hash('sha256', $sub);
        if ($DB->record_exists('auth_lti_linked_login', ['issuer256' => $issuer256, 'sub256' => $sub256])) {
            return;
        }
        $rec = [
            'userid' => $userid,
            'issuer256' => $issuer256,
            'sub256' => $sub256,
            'timecreated' => $timenow,
            'timemodified' => $timenow
        ];
        $DB->insert_record('auth_lti_linked_login', $rec);
    }

    protected function get_user_binding(string $issuer, string $sub): ?int {
        global $DB;
        $issuer256 = hash('sha256', $issuer);
        $sub256 = hash('sha256', $sub);
        try {
            $binduser = $DB->get_field('auth_lti_linked_login', 'userid', ['issuer256' => $issuer256, 'sub256' => $sub256], MUST_EXIST);
        } catch (\dml_exception $e) {
            $binduser = null;
        }
        return $binduser;
    }
}
