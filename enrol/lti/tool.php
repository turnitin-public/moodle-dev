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
 * The main entry point for the external system.
 *
 * @package    enrol_lti
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use \IMSGlobal\LTI13;

require_once(__DIR__ . '/../../config.php');

$toolid = required_param('id', PARAM_INT);
$id_token = optional_param('id_token', null, PARAM_RAW);

$PAGE->set_context(context_system::instance());
$url = new moodle_url('/enrol/lti/tool.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('opentool', 'enrol_lti'));

// Get the tool.
$tool = \enrol_lti\helper::get_lti_tool($toolid);

// Check if the authentication plugin is disabled.
if (!is_enabled_auth('lti')) {
    print_error('pluginnotenabled', 'auth', '', get_string('pluginname', 'auth_lti'));
    exit();
}

// Check if the enrolment plugin is disabled.
if (!enrol_is_enabled('lti')) {
    print_error('enrolisdisabled', 'enrol_lti');
    exit();
}

// Check if the enrolment instance is disabled.
if ($tool->status != ENROL_INSTANCE_ENABLED) {
    print_error('enrolisdisabled', 'enrol_lti');
    exit();
}

// LTI 1.3 launch - hacked for now.
if ($id_token) {
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
            'kid' => '', // key used to identify the key in the jwks file.  E.g. ['key' => file_get_contents(private.key)]
            'issuer' => 'https://7b4337d9d893.au.ngrok.io/master', // [R] Registered platform URL, which will be checked.
            'private_key' => '', // Tool private key.
        ];

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

    // As per the OIDC spec, the auth response must contain state, and it must match the value sent in the auth request.
    // The IMS library takes care of this for us.
    $state = required_param('state', PARAM_ALPHANUMEXT);
    // This next bit just confirms the IMS library is doing so.
    // TODO: remove this code eventually - it's test code only.
    //$_COOKIE['lti1p3_' . $state] = 'abc'; // Hacking this will cause the IMS lib validation to fail - confirmed.

    $launch = LTI13\LTI_Message_Launch::new(new issuer_database())
        ->validate();
    if ($launch->is_deep_link_launch()) {
        // Present a configuration page.
        // TODO: create a way for plugins to specify a page that contains the below content.
        global $CFG;
        echo '
        <div>
        <h1>Configure the tool:</h1>
            <ul>
                <li><a href="'.$CFG->wwwroot.'/mod/tooltest/configure.php?launchid='.urlencode($launch->get_launch_id()).'&toolid='.$toolid.'&type=easy">Easy</a></li>
                <li><a href="'.$CFG->wwwroot.'/mod/tooltest/configure.php?launchid='.urlencode($launch->get_launch_id()).'&toolid='.$toolid.'&type=hard">Hard</a></li>
            </ul>
        </div>';
        die;

    } else {
        echo "<pre>";
        echo "Welcome, ". $launch->get_launch_data()['name'] . " (generated on the tool.php page, from OIDC data, before redirect)<br>";
        echo "</pre>";

        // TODO: this is where the LTi1.1 code does all the enrolment checks, etc and redirects the logged in user to the course.
        // TODO: the below assumes module context for now - see enrol/classes/tooL_provider.php for complete source.
        $context = context::instance_by_id($tool->contextid);

        // If we've got custom data, from a deep launch, use it.
        $data = $launch->get_launch_data();
        $type = $data['https://purl.imsglobal.org/spec/lti/claim/custom']['type'] ?? 'easy';

        redirect(new moodle_url('/mod/tooltest/view.php', ['id' => $context->instanceid, 'type' => $type]));
    }
}

$consumerkey = required_param('oauth_consumer_key', PARAM_TEXT);
$ltiversion = optional_param('lti_version', null, PARAM_TEXT);
$messagetype = required_param('lti_message_type', PARAM_TEXT);

// Only accept launch requests from this endpoint.
if ($messagetype != "basic-lti-launch-request") {
    print_error('invalidrequest', 'enrol_lti');
    exit();
}

// Initialise tool provider.
$toolprovider = new \enrol_lti\tool_provider($toolid);

// Special handling for LTIv1 launch requests.
if ($ltiversion === \IMSGlobal\LTI\ToolProvider\ToolProvider::LTI_VERSION1) {
    $dataconnector = new \enrol_lti\data_connector();
    $consumer = new \IMSGlobal\LTI\ToolProvider\ToolConsumer($consumerkey, $dataconnector);
    // Check if the consumer has already been registered to the enrol_lti_lti2_consumer table. Register if necessary.
    $consumer->ltiVersion = \IMSGlobal\LTI\ToolProvider\ToolProvider::LTI_VERSION1;
    // For LTIv1, set the tool secret as the consumer secret.
    $consumer->secret = $tool->secret;
    $consumer->name = optional_param('tool_consumer_instance_name', '', PARAM_TEXT);
    $consumer->consumerName = $consumer->name;
    $consumer->consumerGuid = optional_param('tool_consumer_instance_guid', null, PARAM_TEXT);
    $consumer->consumerVersion = optional_param('tool_consumer_info_version', null, PARAM_TEXT);
    $consumer->enabled = true;
    $consumer->protected = true;
    $consumer->save();

    // Set consumer to tool provider.
    $toolprovider->consumer = $consumer;
    // Map tool consumer and published tool, if necessary.
    $toolprovider->map_tool_to_consumer();
}

// Handle the request.
$toolprovider->handleRequest();

echo $OUTPUT->header();
echo $OUTPUT->footer();
