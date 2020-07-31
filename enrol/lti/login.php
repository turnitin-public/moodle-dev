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

defined('MOODLE_INTERNAL') || die;

// IMS lti library.
require_once(__DIR__ . '/lib/lti/lti.php');
use \IMSGlobal\LTI;

// Required fields for OIDC 3rd party initiated login.
// See http://www.imsglobal.org/spec/security/v1p0/#step-1-third-party-initiated-login.
$iss = required_param('iss', PARAM_URL); // Issuer URI of the calling platform.
$loginhint = required_param('login_hint', PARAM_INT); // Platform ID for the person to login.
$targetlinkuri = required_param('target_link_uri', PARAM_URL); // The took launch URL.

// Optional lti_message_hint. See https://www.imsglobal.org/spec/lti/v1p3#additional-login-parameters-0.
// If found, this must be returned unmodified to the platform.
$ltimessagehint = optional_param('lti_message_hint', PARAM_RAW);


// See https://github.com/IMSGlobal/lti-1-3-php-library for details on the below class implementation.

class issuer_database implements LTI\Database {

    // TODO: Fix this hard coding.
    //  How is the record of the issuer created? Do we need to register a consumer with the tool?
    //  The spec doesn't dictate how it's stored, but the question of workflow still remains.
    // Note: [R] denotes required by the IMS tool test suite, as part of setting up an LTI1.3 test tool.
    private $reginfo = (object) [
        'auth_login_url' => 'https://7b4337d9d893.au.ngrok.io/master/mod/lti/auth.php', // [R] Platform OIDC login endpoint.
        'auth_token_url' => 'https://7b4337d9d893.au.ngrok.io/master/mod/lti/token.php', // [R] Platform service authorisation endpoint.
        'client_id' => 'd42df408-70f5-4b60-8274-6c98d3b9468d', // TODO: if the platform generates this when consuming the tool, how is it set in the tool?
        'key_set_url' => 'https://7b4337d9d893.au.ngrok.io/master/mod/lti/certs.php', // [R] The platform's JWKS endpoint.
        'kid' => '', // key used to identify the private key in the tool's jwks file.  E.g. ['key' => file_get_contents(private.key)]
        'issuer' => 'https://7b4337d9d893.au.ngrok.io/master', // [R] Registered platform URL, which will be checked.
        'private_key' => '', // Tool private key.
    ];

    public function find_registration_by_issuer($iss) {
        return LTI\LTI_Registration::new()
            ->set_auth_login_url($this->reginfo->auth_login_url)
            ->set_auth_token_url($this->reginfo->auth_token_url)
            ->set_client_id($this->reginfo->client_id)
            ->set_key_set_url($this->reginfo->key_set_url)
            ->set_kid($this->reginfo->kid)
            ->set_issuer($this->reginfo->issuer)
            ->set_tool_private_key($this->reginfo->private_key);
    }

    public function find_deployment($iss, $deployment_id) {
        return LTI\LTI_Deployment::new()
            ->set_deployment_id($deployment_id);
    }
}

// Now, do the OIDC login.
define(TOOL_HOST, $iss); // TODO: can we assume iss is the tool host?

LTI\LTI_OIDC_Login::new(new issuer_database())
    ->do_oidc_login_redirect(TOOL_HOST . "/game.php")
    ->do_redirect();


