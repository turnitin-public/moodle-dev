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
 * LTI 1.3 login endpoint.
 *
 * See: http://www.imsglobal.org/spec/security/v1p0/#step-1-third-party-initiated-login
 *
 * This must support both POST and GET methods, as per the spec.
 *
 * @package    enrol_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// IMS lti library.
require_once("../../config.php");
use IMSGlobal\LTI13;
use enrol_lti\local\ltiadvantage\issuer_database;

// Required fields for OIDC 3rd party initiated login.
// See http://www.imsglobal.org/spec/security/v1p0/#step-1-third-party-initiated-login.
$iss = required_param('iss', PARAM_URL); // Issuer URI of the calling platform.
$loginhint = required_param('login_hint', PARAM_INT); // Platform ID for the person to login.
$targetlinkuri = required_param('target_link_uri', PARAM_URL); // The took launch URL.

// We also need the tool id inside the targetlinkuri, as this lets us find the correct registration.
//$toolid = (new moodle_url($targetlinkuri))->get_param('id');
//$toolid = validate_param($toolid, PARAM_INT);

// Optional lti_message_hint. See https://www.imsglobal.org/spec/lti/v1p3#additional-login-parameters-0.
// If found, this must be returned unmodified to the platform.
$ltimessagehint = optional_param('lti_message_hint', null, PARAM_RAW);

// See https://github.com/IMSGlobal/lti-1-3-php-library for details on the below class implementation.

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
LTI13\LTI_OIDC_Login::new(new issuer_database())
    ->do_oidc_login_redirect($redirecturi)
    ->do_redirect();

