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
 * Prints an instance of mod_tooltest.
 *
 * @package     mod_tooltest
 * @copyright   2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
global $DB, $OUTPUT, $USER, $PAGE;
use \IMSGlobal\LTI13;

$launchid = required_param('launchid', PARAM_TEXT);
$type = required_param('type', PARAM_ALPHANUM);
$toolid = required_param('toolid', PARAM_INT);

class issuer_database implements LTI13\Database {

    // TODO: Fix this hard coding.
    //  How is the record of the issuer created? Do we need to register a consumer with the tool?
    //  The spec doesn't dictate how it's stored, but the question of workflow still remains.
    // Note: [R] denotes required by the IMS tool test suite, as part of setting up an LTI1.3 test tool.
    private $reginfo = [
        'auth_login_url' => 'https://7b4337d9d893.au.ngrok.io/master/mod/lti/auth.php', // [R] Platform OIDC login endpoint.
        'auth_token_url' => 'https://7b4337d9d893.au.ngrok.io/master/mod/lti/token.php', // [R] Platform service authorisation endpoint.
        'client_id' => 'EGD6ZpQOq3nx6T4', // [R] the client_id of the platform or platform instance.
        'key_set_url' => 'https://7b4337d9d893.au.ngrok.io/master/mod/lti/certs.php', // [R] The platform's JWKS endpoint.
        'kid' => '5dcb29bf1703024b2ee1', // key used to identify the key in the jwks file.  E.g. ['key' => file_get_contents(private.key)]
        'issuer' => 'https://7b4337d9d893.au.ngrok.io/master', // [R] Registered platform URL, which will be checked.
        'private_key' => '', // Tool private key.
    ];
    private $toolid; // The int id of this tool. Each tool has its own security contract with consumers.

    public function __construct($toolid) {
        $this->toolid = $toolid;
        $this->reginfo['private_key'] = get_config('enrol_lti', 'privatekey_'.$toolid);
    }

    // 5 things the TOOL PROVIDER needs from the 'view configuration details'
    // modal in the manage tools section of the consumer:
    // - [Set] Platform ID ('issuer' in the above reginfo)
    // - [Set] Client ID ('client_id' in the above reginfo)
    // - [Set] Public keyset URL ('key_set_url' in the above reginfo)
    // - [Set] Access token URL ('auth_token_url' in the above reginfo)
    // - [Set] Auth request URL ('auth_login_url' in the above reginfo)
    // TODO: Hard code these for now. This is the tool, so we must have these pre-configured.

    // Things the TOOL_CONSUMER needs to have set from the tool, once setup.

    public function find_registration_by_issuer($iss) {

        $this->reginfo = (object) $this->reginfo;

        return LTI13\LTI_Registration::new()
            ->set_auth_login_url($this->reginfo->auth_login_url)
            ->set_auth_token_url($this->reginfo->auth_token_url)
            ->set_client_id($this->reginfo->client_id)
            ->set_key_set_url($this->reginfo->key_set_url)
            ->set_kid($this->reginfo->kid)
            ->set_issuer($this->reginfo->issuer)
            ->set_tool_private_key($this->reginfo->private_key);
    }

    public function find_deployment($iss, $deployment_id) {
        return LTI13\LTI_Deployment::new()
            ->set_deployment_id($deployment_id);
    }
}

$launch = LTI13\LTI_Message_Launch::from_cache($launchid, new issuer_database($toolid));

// TODO: Should probably verify that the toolid passed in matches the signed toolid in the launch jwt.

if (!$launch->is_deep_link_launch()) {
    throw new coding_exception("Not deep link launch");
}
global $CFG;
// TODO: this should redirect to a registered ODIC deep link redirect URL.
$resource = LTI13\LTI_Deep_Link_Resource::new()
//    ->set_url($CFG->wwwroot . '/mod/tooltest/view.php?id=92')
    ->set_url($CFG->wwwroot . '/enrol/lti/tool.php?id=19')
    ->set_custom_params(['type' =>  $type])
    ->set_title('Tooltest instance [difficulty: '.$type.']');

$dl = $launch->get_deep_link();

$dl->output_response_form([$resource]);
