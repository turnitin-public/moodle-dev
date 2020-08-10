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

// We also need the tool id inside the targetlinkuri, as this lets us find the correct registration.
$toolid = (new moodle_url($targetlinkuri))->get_param('id');
$toolid = validate_param($toolid, PARAM_INT);

// Optional lti_message_hint. See https://www.imsglobal.org/spec/lti/v1p3#additional-login-parameters-0.
// If found, this must be returned unmodified to the platform.
$ltimessagehint = optional_param('lti_message_hint', null, PARAM_RAW);

// See https://github.com/IMSGlobal/lti-1-3-php-library for details on the below class implementation.
require_once('issuer_database.php');

//print_r($targetlinkuri);

//TODO: Is this redirect location right? (previously $targetlinkuri).
//global $CFG;
//$toolid = (new moodle_url($targetlinkuri))->get_param('id');
//$redirecturi = $CFG->wwwroot . '/enrol/lti/oidcredirect.php?id=' . $toolid;

// Here, we're acknowledging that 'target_link_uri' (where the tool should go after OIDC flow completion)
// is THE SAME URI our 'redirect_uri' in the auth response. These can be different, and are in the IMS validation suite.
// This way, we don't need that extra redirect like:
// auth.php (platform) -> oidcredirect.php (tool, registered oidc login endpoint) -> tool.php (tool - and the target_link_uri) -> view.php (to render).
// Instead, we have:
// auth.php (platform) -> tool.php (tool, registered oidc login endpoint) -> view.php (to render).
// TODO: validate the targetlinkuri to stem open redirects.
$redirecturi = $targetlinkuri;
// Now, do the OIDC login.
LTI13\LTI_OIDC_Login::new(new issuer_database($toolid))
    ->do_oidc_login_redirect($redirecturi)
    ->do_redirect();

