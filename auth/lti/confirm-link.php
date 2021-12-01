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
 * Verification endpoint, confirming the intent to link an LTI user on the platform (as identified by {iss, sub} tuple) with an
 * existing local Moodle account. Upon being asked, via email, to confirm account linking the existing Moodle user will click a
 * link to this endpoint to confirm.
 *
 * This is an LTI Advantage specific login feature.
 *
 * This confirmation step is required to prevent the following situations, both relating to shared machines:
 * - A launching platform user begins the account linking process but isn't authenticated with Moodle yet. They click 'Log in' and
 * then walk away, leaving the process waiting for any valid login to make the binding. I.e. the 'wantsurl' will be pointing to the
 * auth/lti/login page and the OIDC token will be cached. If a different user then comes along and logs into Moodle on the same
 * machine, their first login may result in an account binding - were there not to be a verification step.
 * - A launching platform user begins the account linking process, but sees that a different user is authenticated with Moodle.
 * That is, someone has left their session open and walked away. Without confirmation, it would be possible for this platform user
 * to hijack that user's account via linking because they have access to the existing session.
 *
 * The verification step prevents both of these issues by asking the Moodle user to confirm their intent to make the account link.
 * If the above do occur, the Moodle user would simply ignore the email verification step and the process would time out, resulting
 * in no link of accounts.
 *
 * @package    enrol_lti
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $OUTPUT, $PAGE, $SESSION;

$token = required_param('token', PARAM_RAW);
$iss = required_param('iss', PARAM_URL);
$sub = required_param('sub', PARAM_RAW);
$userid = required_param('userid', PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$auth = get_auth_plugin('lti');
if ($auth->confirm_user_binding($iss, $sub, $userid, $token)) {
    redirect(new moodle_url($returnurl));
} else {
    // TODO: throw some sort of exception, etc.
    echo "Failed";
}
