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
 * Contains the platform registration form class.
 *
 * @package    enrol_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_lti\form;
require_once(__DIR__ . '/../../../../config.php');
require_once("$CFG->libdir/formslib.php");

/**
 * The platform_registration_form class, for registering a platform as a consumer of a published tool.
 *
 * @package    enrol_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class platform_registration_form extends \moodleform {

    function definition() {
        $mform = $this->_form;

        // TODO: add help buttons for the below elements.

        // Id.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Toolid.
        $mform->addElement('hidden', 'toolid');
        $mform->setType('toolid', PARAM_INT);

        // Platform Id.
        $mform->addElement('text', 'platformid', 'Platform Id (iss)');
        $mform->setType('platformid', PARAM_URL);
        $mform->addRule('platformid', "This field is required", 'required', null, 'client');

        // Client Id.
        $mform->addElement('text', 'clientid', 'Client Id');
        $mform->setType('clientid', PARAM_TEXT);
        $mform->addRule('clientid', "This field is required", 'required', null, 'client');


        // Authentication request URL.
        $mform->addElement('text', 'authenticationrequesturl', 'Authentication request URL');
        $mform->setType('authenticationrequesturl', PARAM_URL);
        $mform->addRule('authenticationrequesturl', "This field is required", 'required', null, 'client');


        // JSON Web Key Set URL.
        $mform->addElement('text', 'jwksurl', 'JWKS URL');
        $mform->setType('jwksurl', PARAM_URL);
        $mform->addRule('jwksurl', "This field is required", 'required', null, 'client');


        // Access token URL.
        $mform->addElement('text', 'accesstokenurl', 'Access token URL');
        $mform->setType('accesstokenurl', PARAM_URL);
        $mform->addRule('accesstokenurl', "This field is required", 'required', null, 'client');

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        $buttonarray[] = $mform->createElement('cancel', 'cancelbutton', get_string('cancel'));
        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        foreach ($data as $key => $val) {
            if (isset($this->_form->_types[$key]) && $this->_form->_types[$key] == 'url') {
                if (!(bool) preg_match('/^[a-z]+:\/\/([^:@\s]+:[^@\s]+@)?[^ @]+(:[0-9]+)?(\/[^#]*)?(#.*)?$/i', $val)) {
                    $errors[$key] = "Invalid URL. Have you included http:// or https://?";
                }
            }
        }

        // TODO: Validate the uniqueness of the issuer so we don't hit a db error when trying to insert a duplicate.

        return $errors;
    }
}
