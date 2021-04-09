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
 * Contains the issuer_database class.
 *
 * @package    enrol_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\local\ltiadvantage;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use \IMSGlobal\LTI13;

/**
 * The issuer_database class, providing a read-only store of issuer details.
 *
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issuer_database implements LTI13\Database {

    /** @var array $reginfo the array representing the raw registration information.*/
    private $reginfo = [];

    /**
     * The issuer_database constructor.
     */
    public function __construct() {
        $this->populate();
    }

    /**
     * Populate this issuer store by fetching the issuers from the database.
     */
    private function populate() {
        global $DB;

        $records = $DB->get_records('enrol_lti_app_registration');

        $privatekey = get_config('enrol_lti', 'lti_13_privatekey');
        $kid = get_config('enrol_lti', 'lti_13_kid');

        foreach ($records as $id => $reg) {
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

    /**
     * Find and return an LTI registration based on its unique issuer id.
     *
     * @param string $iss the issuer id.
     * @return LTI13\LTI_Registration The registration object.
     */
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

    /**
     * Returns an LTI deployment based on issuer id and deployment id.
     *
     * @param string $iss the issuer id.
     * @param string $deployment_id the deployment id.
     * @return LTI13\LTI_Deployment|null The deployment object or null if not found.
     */
    public function find_deployment($iss, $deployment_id) {
        $appregistrationrepo = new application_registration_repository();
        $appregistration = $appregistrationrepo->find_by_platform($iss);
        if (!$appregistration) {
            return null;
        }
        $deploymentrepo = new deployment_repository();
        $deployment = $deploymentrepo->find_by_registration($appregistration->get_id(), $deployment_id);
        if (!$deployment) {
            return null;
        }
        return LTI13\LTI_Deployment::new()
            ->set_deployment_id($deployment->get_deploymentid());
    }
}
