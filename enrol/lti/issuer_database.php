<?php
use \IMSGlobal\LTI13;

class issuer_database implements LTI13\Database {

    // TODO: Fix this hard coding.
    //  How is the record of the issuer created? Do we need to register a consumer with the tool?
    //  The spec doesn't dictate how it's stored, but the question of workflow still remains.
    // Note: [R] denotes required by the IMS tool test suite, as part of setting up an LTI1.3 test tool.
    private $reginfo = [
        'https://7b4337d9d893.au.ngrok.io/master' => [
            'auth_login_url' => 'https://7b4337d9d893.au.ngrok.io/master/mod/lti/auth.php', // [R] Platform OIDC login endpoint.
            'auth_token_url' => 'https://7b4337d9d893.au.ngrok.io/master/mod/lti/token.php', // [R] Platform service authorisation endpoint.
            'client_id' => 'EGD6ZpQOq3nx6T4', // [R] the client_id of the platform or platform instance.
            'key_set_url' => 'https://7b4337d9d893.au.ngrok.io/master/mod/lti/certs.php', // [R] The platform's JWKS endpoint.
            'kid' => '', // key used to identify the key in the jwks file.  E.g. ['key' => file_get_contents(private.key)]
            'issuer' => 'https://7b4337d9d893.au.ngrok.io/master', // [R] Registered platform URL, which will be checked.
            'private_key' => '', // Tool private key.
        ],
        'https://ltiadvantagevalidator.imsglobal.org' => [
            'auth_login_url' => 'https://ltiadvantagevalidator.imsglobal.org/ltitool/oidcauthurl.html', // [R] Platform OIDC login endpoint.
            'auth_token_url' => 'https://ltiadvantagevalidator.imsglobal.org/ltitool/authcodejwt.html', // [R] Platform service authorisation endpoint.
            'client_id' => 'imstester_fc3731e', // [R] the client_id of the platform or platform instance.
            'key_set_url' => 'https://oauth2server.imsglobal.org/jwks', // [R] The platform's JWKS endpoint.
            'kid' => '', // key used to identify the key in the jwks file.  E.g. ['key' => file_get_contents(private.key)]
            'issuer' => 'https://ltiadvantagevalidator.imsglobal.org', // [R] Registered platform URL, which will be checked.
            'private_key' => '', // Tool private key.
        ]
    ];

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
    // TODO: Hard code these for now. This is the tool, so we must have these pre-configured.

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
