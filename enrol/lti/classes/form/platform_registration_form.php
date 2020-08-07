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
global $CFG;
require_once($CFG->libdir . '/formslib.php');

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
        $strrequired = get_string('required');

        // Id.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Name.
        $mform->addElement('text', 'name', get_string('registerplatform:name', 'enrol_lti'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', $strrequired, 'required', null, 'client');

        // Platform Id.
        $mform->addElement('text', 'platformid', get_string('registerplatform:platformid', 'enrol_lti'));
        $mform->setType('platformid', PARAM_URL);
        $mform->addRule('platformid', $strrequired, 'required', null, 'client');
        $mform->addHelpButton('platformid', 'registerplatform:platformid', 'enrol_lti');

        // Client Id.
        $mform->addElement('text', 'clientid', get_string('registerplatform:clientid', 'enrol_lti'));
        $mform->setType('clientid', PARAM_TEXT);
        $mform->addRule('clientid', $strrequired, 'required', null, 'client');
        $mform->addHelpButton('clientid', 'registerplatform:clientid', 'enrol_lti');

        // Authentication request URL.
        $mform->addElement('text', 'authenticationrequesturl', get_string('registerplatform:authrequesturl', 'enrol_lti'));
        $mform->setType('authenticationrequesturl', PARAM_URL);
        $mform->addRule('authenticationrequesturl', $strrequired, 'required', null, 'client');
        $mform->addHelpButton('authenticationrequesturl', 'registerplatform:authrequesturl', 'enrol_lti');

        // JSON Web Key Set URL.
        $mform->addElement('text', 'jwksurl', get_string('registerplatform:jwksurl', 'enrol_lti'));
        $mform->setType('jwksurl', PARAM_URL);
        $mform->addRule('jwksurl', $strrequired, 'required', null, 'client');
        $mform->addHelpButton('jwksurl', 'registerplatform:jwksurl', 'enrol_lti');

        // Access token URL.
        $mform->addElement('text', 'accesstokenurl', get_string('registerplatform:accesstokenurl', 'enrol_lti'));
        $mform->setType('accesstokenurl', PARAM_URL);
        $mform->addRule('accesstokenurl', $strrequired, 'required', null, 'client');
        $mform->addHelpButton('accesstokenurl', 'registerplatform:accesstokenurl', 'enrol_lti');

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        foreach ($data as $key => $val) {
            if (isset($this->_form->_types[$key]) && $this->_form->_types[$key] == 'url') {
                if (!(bool) preg_match('/^[a-z]+:\/\/([^:@\s]+:[^@\s]+@)?[^ @]+(:[0-9]+)?(\/[^#]*)?(#.*)?$/i', $val)) {
                    $errors[$key] = get_string('registerplatform:invalidurlerror', 'enrol_lti');
                }
            }
        }

        // Validate the uniqueness of the issuer, so we don't hit a db error when trying to insert a duplicate.
        global $DB;
        if ($DB->record_exists('enrol_lti_platform_registry', ['platformid' => $data['platformid']])) {
            $errors['platformid'] = get_string('registerplatform:invalidplatformiderror', 'enrol_lti');
        }

        return $errors;
    }
}
