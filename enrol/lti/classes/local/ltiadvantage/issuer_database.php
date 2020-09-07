<?php

namespace enrol_lti\local\ltiadvantage;
use \IMSGlobal\LTI13;

class issuer_database implements LTI13\Database {

    private $reginfo = [];

    // Moodle-to-Moodle process:
    // 4 Things the TOOL_CONSUMER (PLATFORM) needs from the tool to begin with:
    // - Launch URL
    // - Keyset URL
    // - OIDC login URL (to initiate the third party OIDC login)
    // - (OIDC) Redirect URL (the URI to post the auth response to during OIDC auth flow)

    // Then, set up the TOOL_CONSUMER on the Moodle site (admin > manage tools)

    // 5 things the TOOL PROVIDER (TOOL) needs from the platform (via 'view configuration details' modal in the
    // manage tools section:
    // - [Set] Platform ID ('issuer' in the above reginfo)
    // - [Set] Client ID ('client_id' in the above reginfo)
    // - [Set] Public keyset URL ('key_set_url' in the above reginfo)
    // - [Set] Access token URL ('auth_token_url' in the above reginfo)
    // - [Set] Auth request URL ('auth_login_url' in the above reginfo)

    public function __construct() {
        $this->populate();
    }

    private function populate() {
        global $DB;

        $records = $DB->get_records('enrol_lti_platform_registry');

        $privatekey = get_config('enrol_lti', 'lti_13_privatekey');
        $kid = get_config('enrol_lti', 'lti_13_kid');

        foreach ($records as $id =>  $reg) {
            $this->reginfo[$reg->platformid] = [
                'issuer' => $reg->platformid,
                'auth_login_url' => $reg->authenticationrequesturl,
                'auth_token_url' => $reg->accesstokenurl,
                'client_id' => $reg->clientid,
                'key_set_url' => $reg->jwksurl,
                'private_key' => $privatekey,
                'kid' => $kid,
            ];
        }
    }

    public function find_registration_by_issuer($iss) {

        foreach ($this->reginfo as $key => $data) {
            if ($iss === $key) {
                $reg = (object) $data;
                return LTI13\LTI_Registration::new()
                    ->set_auth_login_url($reg->auth_login_url)
                    ->set_auth_token_url($reg->auth_token_url)
                    ->set_client_id($reg->client_id)
                    ->set_key_set_url($reg->key_set_url)
                    ->set_kid($reg->kid)
                    ->set_issuer($reg->issuer)
                    ->set_tool_private_key($reg->private_key);
            }
        }
    }

    //  From suite: deploymentid is 'testdeploy'
    public function find_deployment($iss, $deployment_id) {
        return LTI13\LTI_Deployment::new()
            ->set_deployment_id($deployment_id);
    }
}
