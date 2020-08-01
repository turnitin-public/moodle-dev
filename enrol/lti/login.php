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
//
// This file is part of BasicLTI4Moodle
//
// BasicLTI4Moodle is an IMS BasicLTI (Basic Learning Tools for Interoperability)
// consumer for Moodle 1.9 and Moodle 2.0. BasicLTI is a IMS Standard that allows web
// based learning tools to be easily integrated in LMS as native ones. The IMS BasicLTI
// specification is part of the IMS standard Common Cartridge 1.1 Sakai and other main LMS
// are already supporting or going to support BasicLTI. This project Implements the consumer
// for Moodle. Moodle is a Free Open source Learning Management System by Martin Dougiamas.
// BasicLTI4Moodle is a project iniciated and leaded by Ludo(Marc Alier) and Jordi Piguillem
// at the GESSI research group at UPC.
// SimpleLTI consumer for Moodle is an implementation of the early specification of LTI
// by Charles Severance (Dr Chuck) htp://dr-chuck.com , developed by Jordi Piguillem in a
// Google Summer of Code 2008 project co-mentored by Charles Severance and Marc Alier.
//
// BasicLTI4Moodle is copyright 2009 by Marc Alier Forment, Jordi Piguillem and Nikolas Galanis
// of the Universitat Politecnica de Catalunya http://www.upc.edu
// Contact info: Marc Alier Forment granludo @ gmail.com or marc.alier @ upc.edu.

/**
 * Login endpoint which LTI 1.3 platforms will call to initiate an OIDC third party initiated login.
 *
 * See: http://www.imsglobal.org/spec/security/v1p0/#step-1-third-party-initiated-login
 *
 * This must support both POST and GET methods, as per the spec.
 *
 * @package mod_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// IMS lti library.
require_once("../../config.php");
use IMSGlobal\LTI13;

// Required fields for OIDC 3rd party initiated login.
// See http://www.imsglobal.org/spec/security/v1p0/#step-1-third-party-initiated-login.
$iss = required_param('iss', PARAM_URL); // Issuer URI of the calling platform.
$loginhint = required_param('login_hint', PARAM_INT); // Platform ID for the person to login.
$targetlinkuri = required_param('target_link_uri', PARAM_URL); // The took launch URL.

// Optional lti_message_hint. See https://www.imsglobal.org/spec/lti/v1p3#additional-login-parameters-0.
// If found, this must be returned unmodified to the platform.
$ltimessagehint = optional_param('lti_message_hint', null, PARAM_RAW);

// See https://github.com/IMSGlobal/lti-1-3-php-library for details on the below class implementation.

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

//print_r($targetlinkuri);

//TODO: Is this redirect location right?

// Now, do the OIDC login.
LTI13\LTI_OIDC_Login::new(new issuer_database())
    ->do_oidc_login_redirect($targetlinkuri)
    ->do_redirect();

