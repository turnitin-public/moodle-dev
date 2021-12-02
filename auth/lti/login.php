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
 * Page allowing a platform user, identified by their {iss, sub} tuple, to be bound to a new or existing Moodle account.
 *
 * This is an LTI Advantage specific login feature.
 *
 * The auth flow defined in auth_lti\auth::complete_login() redirects here when a launching user does not have an
 * account binding yet. This page prompts the user to select between:
 * a) An auto provisioned account.
 * An account with auth type 'lti' is created for the user. This account is bound to the launch credentials.
 * Or
 * b) Use an existing account
 * The standard Moodle auth flow is leveraged to get an existing user account. This account is then bound to the launch
 * credentials.
 *
 * @package    enrol_lti
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\event\user_login_failed;
use core\output\notification;

require_once(__DIR__ . '/../../config.php');

global $OUTPUT, $PAGE, $SESSION;

// Form fields dealing with the user's choice about account types (new, existing) or the login check.
$newaccount = optional_param('new_account', false, PARAM_BOOL);
$existingaccount = optional_param('existing_account', false, PARAM_BOOL);
$logincheck = optional_param('login_check', false, PARAM_BOOL);

if (empty($SESSION->auth_lti) || empty($SESSION->auth_lti->launchdata)) {
    throw new coding_exception('Account linking not possible. Missing LTI launch credentials.');
}
if (empty($SESSION->auth_lti->returnurl)) {
    throw new coding_exception('Account linking not possible. Missing return URL.');
}

// Handle form submission.
if ($newaccount) {
    require_sesskey();
    $launchdata = $SESSION->auth_lti->launchdata;
    $returnurl = $SESSION->auth_lti->returnurl;
    unset($SESSION->auth_lti);

    if (!empty($CFG->authpreventaccountcreation)) {
        // Trigger login failed event.
        $failurereason = AUTH_LOGIN_UNAUTHORISED;
        $event = user_login_failed::create(['other' => ['reason' => $failurereason]]);
        $event->trigger();

        // The username does not exist and settings prevent creating new accounts.
        $reason = get_string('loginerror_cannotcreateaccounts', 'auth_oauth2');
        $errormsg = get_string('notloggedindebug', 'auth_oauth2', $reason);
        $SESSION->loginerrormsg = $errormsg;
        redirect(new moodle_url('/login/index.php'));
    } else {
        // Create a new confirmed account.
        $auth = get_auth_plugin('lti');
        $newuser = $auth->find_or_create_user_from_launch($launchdata);
        complete_user_login($newuser);

        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new moodle_url('/auth/lti/login.php'));
        $PAGE->set_pagelayout('popup');
        $renderer = $PAGE->get_renderer('auth_lti');
        $message = "An account has successfully been created for you!";
        echo $OUTPUT->header();
        echo $renderer->render_account_binding_complete(
            new notification($message, notification::NOTIFY_SUCCESS, false),
            $returnurl
        );
        echo $OUTPUT->footer();
        exit();
    }
} elseif ($existingaccount) {
    // The user has chosen to use an existing account. They may or may not be logged in.
    // If not, we want to leverage the wantsurl, and take them through the login process.
    // This is done by redirecting to self, with a 'login_check' param set.
    // Only when authenticated, do we display the account binding completion view, allowing return to the launch.
    if (isloggedin() || $logincheck) {
        //require_login(null, false);
        if (!isloggedin()) {
            redirect(new moodle_url(get_login_url(), ['linktoken' => $SESSION->auth_lti->linktoken]));
        }
        global $USER;
        $auth = get_auth_plugin('lti');
        $launchdata = $SESSION->auth_lti->launchdata;
        $returnurl = $SESSION->auth_lti->returnurl;
        unset($SESSION->auth_lti);

        $PAGE->set_url(new moodle_url('/auth/lti/login.php'));
        $PAGE->set_context(context_system::instance());
        $PAGE->set_pagelayout('popup');

        $sent = $auth->send_account_link_confirmation_email($launchdata['iss'], $launchdata['sub'], $USER->id, $returnurl);
        if ($sent) {

        }
        //$auth->create_user_binding($launchdata['iss'], $launchdata['sub'], $USER->id);

        $renderer = $PAGE->get_renderer('auth_lti');

        echo "<h3>You're almost there";
        $message = "We've sent you a verification email. Please click the link in the email to finalise the account linking process.<br><br>
        You'll need to reload this page once the verification is complete.";
        echo $OUTPUT->header();
        //echo $OUTPUT->notification($message, notification::NOTIFY_SUCCESS);
        echo $renderer->render_account_binding_complete(
            new notification($message, notification::NOTIFY_INFO, false),
            $returnurl
        );
        echo $OUTPUT->footer();
        exit();
    } else if (!isloggedin()) {
        require_sesskey();
        // Make a request to self, with same params, triggering a login check.
        $params = [
            'existing_account' => 'yes',
            'login_check' => '1',
            'sesskey' => sesskey(),
            'linktoken' => $SESSION->auth_lti->linktoken
        ];
        $url = new moodle_url('/auth/lti/login.php', $params);
        $SESSION->wantsurl = $url->out(false);
        redirect($url);
    }
}

// TODO consider whether to make the below page aware of CFG->preventaccountcreation, disallowing the 'auto provision'
//  for teachers.

// TODO condsider how to show information about a user who is already logged in to the tool.
//  E.g. "Use an existing account (you are currently logged in as Admin user and this account will be used)"
//       "or __logout__ and use another account (you will be asked to launch the tool again).

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/auth/lti/login.php'));
$PAGE->set_pagelayout('popup');
$renderer = $PAGE->get_renderer('auth_lti');

echo $OUTPUT->header();
echo $renderer->render_account_binding_options_page();
echo $OUTPUT->footer();
