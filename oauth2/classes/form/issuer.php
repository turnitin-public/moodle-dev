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
 * This file contains the form add/update oauth2 issuer.
 *
 * @package   core_oauth2
 * @copyright 2017 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_oauth2\form;
defined('MOODLE_INTERNAL') || die();

use stdClass;
use core\form\persistent;

/**
 * Issuer form.
 *
 * @package   core_oauth2
 * @copyright 2017 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issuer extends persistent {

    /** @var string $persistentclass */
    protected static $persistentclass = 'core\\oauth2\\issuer';

    /** @var array $fieldstoremove */
    protected static $fieldstoremove = array('type', 'submitbutton', 'action');

    /** @var string $type */
    protected $type;

    /** @var bool whether this form relates to a new issuer being created from a template or not. */
    protected $istemplate;

    /**
     * Constructor.
     *
     * The 'persistent' has to be passed as custom data when 'editing'.
     * If a standard issuer is created the type can be passed as custom data, which alters the form according to the
     * type.
     *
     * Note that in order for your persistent to be reloaded after form submission you should
     * either override the URL to include the ID to your resource, or add the ID to the form
     * fields.
     *
     * @param mixed $action
     * @param mixed $customdata
     * @param string $method
     * @param string $target
     * @param mixed $attributes
     * @param bool $editable
     * @param array $ajaxformdata
     */
    public function __construct($action = null, $customdata = null, $method = 'post', $target = '', $attributes = null,
                                $editable = true, array $ajaxformdata = null) {
        // The type variable defines, if we are in the creation process of a standard issuer.
        if (array_key_exists('type', $customdata)) {
            $this->type = $customdata['type'];
        }
        if (array_key_exists('istemplate', $customdata)) {
            $this->istemplate = $customdata['istemplate'];
        }
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $ajaxformdata);
    }

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {
        global $PAGE, $OUTPUT;

        $mform = $this->_form;
        $issuer = $this->get_persistent();

        $docslink = optional_param('docslink', '', PARAM_ALPHAEXT);
        if ($docslink) {
            $name = s($issuer->get('name'));
            $mform->addElement('html', $OUTPUT->doc_link($docslink, get_string('issuersetuptype', 'oauth2', $name)));
        } else {
            $mform->addElement('html', $OUTPUT->page_doc_link(get_string('issuersetup', 'oauth2')));
        }

        // Name.
        $mform->addElement('text', 'name', get_string('issuername', 'oauth2'));
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'issuername', 'oauth2');

        // Client ID.
        $mform->addElement('text', 'clientid', get_string('issuerclientid', 'oauth2'));
        $mform->addRule('clientid', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addRule('clientid', null, 'required', null, 'client');
        $mform->addHelpButton('clientid', 'issuerclientid', 'oauth2');

        // Client Secret.
        $mform->addElement('text', 'clientsecret', get_string('issuerclientsecret', 'oauth2'));
        $mform->addRule('clientsecret', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addRule('clientsecret', null, 'required', null, 'client');
        $mform->addHelpButton('clientsecret', 'issuerclientsecret', 'oauth2');

        // Use basic authentication.
        $mform->addElement('checkbox', 'basicauth', get_string('usebasicauth', 'oauth2'));
        $mform->addHelpButton('basicauth', 'usebasicauth', 'oauth2');

        // Base Url.
        $mform->addElement('text', 'baseurl', get_string('issuerbaseurl', 'oauth2'));
        $mform->addRule('baseurl', get_string('maximumchars', '', 1024), 'maxlength', 1024, 'client');
        $mform->addHelpButton('baseurl', 'issuerbaseurl', 'oauth2');

        // Image.
        $mform->addElement('text', 'image', get_string('issuerimage', 'oauth2'), 'maxlength="1024"');
        $mform->addRule('image', get_string('maximumchars', '', 1024), 'maxlength', 1024, 'client');
        $mform->addHelpButton('image', 'issuername', 'oauth2');

        // Show on login page.
        $options = [
            \core\oauth2\issuer::EVERYWHERE => get_string('issueruseineverywhere', 'oauth2'),
            \core\oauth2\issuer::LOGINONLY => get_string('issueruseinloginonly', 'oauth2'),
            \core\oauth2\issuer::SERVICEONLY => get_string('issueruseininternalonly', 'oauth2'),
        ];
        $mform->addElement('select', 'showonloginpage', get_string('issuerusein', 'oauth2'), $options);
        $mform->addHelpButton('showonloginpage', 'issuerusein', 'oauth2');

        // Name on login page.
        $mform->addElement('text', 'loginpagename', get_string('issuerloginpagename', 'oauth2'));
        $mform->addRule('loginpagename', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('loginpagename', 'issuerloginpagename', 'oauth2');
        $mform->hideIf('loginpagename', 'showonloginpage', 'eq', \core\oauth2\issuer::SERVICEONLY);

        // Login scopes.
        $mform->addElement('text', 'loginscopes', get_string('issuerloginscopes', 'oauth2'));
        $mform->addRule('loginscopes', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('loginscopes', 'issuerloginscopes', 'oauth2');

        // Login scopes offline.
        $mform->addElement('text', 'loginscopesoffline', get_string('issuerloginscopesoffline', 'oauth2'));
        $mform->addRule('loginscopesoffline', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('loginscopesoffline', 'issuerloginscopesoffline', 'oauth2');

        // Login params.
        $mform->addElement('text', 'loginparams', get_string('issuerloginparams', 'oauth2'));
        $mform->addRule('loginparams', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('loginparams', 'issuerloginparams', 'oauth2');

        // Login params offline.
        $mform->addElement('text', 'loginparamsoffline', get_string('issuerloginparamsoffline', 'oauth2'));
        $mform->addRule('loginparamsoffline', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('loginparamsoffline', 'issuerloginparamsoffline', 'oauth2');

        // Allowed Domains.
        $mform->addElement('text', 'alloweddomains', get_string('issueralloweddomains', 'oauth2'));
        $mform->addRule('alloweddomains', get_string('maximumchars', '', 1024), 'maxlength', 1024, 'client');
        $mform->addHelpButton('alloweddomains', 'issueralloweddomains', 'oauth2');
        $mform->hideIf('alloweddomains', 'showonloginpage', 'eq', \core\oauth2\issuer::SERVICEONLY);

        // Require confirmation email for new accounts.
        $mform->addElement('advcheckbox', 'requireconfirmation',
                get_string('issuerrequireconfirmation', 'oauth2'));
        $mform->addHelpButton('requireconfirmation', 'issuerrequireconfirmation', 'oauth2');
        $mform->hideIf('requireconfirmation', 'showonloginpage',
                'eq', \core\oauth2\issuer::SERVICEONLY);

        $mform->addElement('checkbox', 'acceptrisk', get_string('acceptrisk', 'oauth2'));
        $mform->addHelpButton('acceptrisk', 'acceptrisk', 'oauth2');
        $mform->hideIf('acceptrisk', 'showonloginpage',
                'eq', \core\oauth2\issuer::SERVICEONLY);
        $mform->hideIf('acceptrisk', 'requireconfirmation', 'checked');

        $mform->addElement('hidden', 'sortorder');
        $mform->setType('sortorder', PARAM_INT);

        $mform->addElement('hidden', 'servicetype');
        $mform->setType('servicetype', PARAM_ALPHANUM);

        if ($this->istemplate) {
            $mform->addElement('hidden', 'action', 'savetemplate');
            $mform->setType('action', PARAM_ALPHA);

            $mform->addElement('hidden', 'type', $this->type);
            $mform->setType('type', PARAM_ALPHANUM);
        } else {
            $mform->addElement('hidden', 'action', 'edit');
            $mform->setType('action', PARAM_ALPHA);
        }

        $mform->addElement('hidden', 'enabled', $issuer->get('enabled'));
        $mform->setType('enabled', PARAM_BOOL);

        $mform->addElement('hidden', 'id', $issuer->get('id'));
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges', 'oauth2'));
    }

    /**
     * This method implements changes to the form that need to be made once the form data is set.
     */
    public function definition_after_data() {
        $mform = $this->_form;

        if ($this->type) {
            // Set servicetype if it's defined.
            $mform->getElement('servicetype')->setValue($this->type);
        }
    }

    /**
     * Define extra validation mechanims.
     *
     * The data here:
     * - does not include {@see self::$fieldstoremove}.
     * - does include {@see self::$foreignfields}.
     * - was converted to map persistent-like data, e.g. array $description to string $description + int $descriptionformat.
     *
     * You can modify the $errors parameter in order to remove some validation errors should you
     * need to. However, the best practice is to return new or overriden errors. Only modify the
     * errors passed by reference when you have no other option.
     *
     * Do not add any logic here, it is only intended to be used by child classes.
     *
     * @param  stdClass $data Data to validate.
     * @param  array $files Array of files.
     * @param  array $errors Currently reported errors.
     * @return array of additional errors, or overridden errors.
     */
    protected function extra_validation($data, $files, array &$errors) {
        $errors = [];
        if ($data->showonloginpage != \core\oauth2\issuer::SERVICEONLY) {
            if (!strlen(trim($data->loginscopes))) {
                $errors['loginscopes'] = get_string('required');
            }
            if (!strlen(trim($data->loginscopesoffline))) {
                $errors['loginscopesoffline'] = get_string('required');
            }
            if (empty($data->requireconfirmation) && empty($data->acceptrisk)) {
                $errors['acceptrisk'] = get_string('required');
            }
        }
        return $errors;
    }
}
