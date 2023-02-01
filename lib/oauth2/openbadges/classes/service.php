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

namespace oauth2service_openbadges;

use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\service\config\config;
use oauth2service_openbadges\oauth2\service\discovery\openbadges_config_reader;

/**
 * Open Badges OAuth 2 service plugin class.
 *
 * @package    oauth2service_openbadges
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service extends \core\oauth2\service\service {

    /** @var bool whether the service configuration has already been read. */
    protected bool $configread = false;

    /** @var array the OAuth 2 endpoints found in the Badge Metadata file. */
    protected array $endpoints = [];

    /** @var \stdClass the badge configuration details, as read from the manifest. */
    protected \stdClass $badgeconfig;

    /**
     * Constructor.
     *
     * @param issuer $issuer the issuer instance this plugin receives after form submission.
     * @param openbadges_config_reader $configreader an openbadges manifest reader instance.
     * @param \curl $curl a curl instance.
     */
    public function __construct(protected issuer $issuer, protected openbadges_config_reader $configreader, protected \curl $curl) {
    }

    public static function get_instance(issuer $issuer): \core\oauth2\service\service {
        return new self($issuer, new openbadges_config_reader(new \curl()), new \curl());
    }

    public static function get_config(): config {
        return new oauth2\service\config\config();
    }

    public static function get_template(): ?issuer {
        $record = (object) [
            'name' => get_string(self::get_config()->get_full_config()['service_shortname'], 'oauth2service_google'),
            'image' => '',
            'showonloginpage' => issuer::SERVICEONLY,
        ];
        return new issuer(0, $record);
    }

    public function get_issuer(): issuer {
        $this->read_badge_manifest();
        $this->dynamic_client_registration();

        return $this->issuer;
    }

    public function get_endpoints(): array {
        $this->read_badge_manifest();

        return array_values($this->endpoints);
    }

    /**
     * Read the badges manifest from the well-known endpoint and store it locally.
     *
     * @return void
     */
    protected function read_badge_manifest() {
        $issuerbaseurl = $this->issuer->get('baseurl');

        if ($this->configread || empty($issuerbaseurl)) {
            return;
        }

        $this->badgeconfig = $this->configreader->read_json_configuration(new \moodle_url($issuerbaseurl));

        foreach ($this->configreader->get_endpoints() as $name => $url) {
            $record = (object) [
                'name' => $name,
                'url' => $url
            ];
            $this->endpoints[$record->name] = new endpoint(0, $record);
        }

        if (!empty($this->badgeconfig->badgeConnectAPI[0]->scopesOffered)) {
            $this->issuer->set('scopessupported', implode(' ', $this->badgeconfig->badgeConnectAPI[0]->scopesOffered));
        }

        if (!empty($this->badgeconfig->badgeConnectAPI[0]->image) && empty($this->issuer->get('image'))) {
            // Update the image with the value in the manifest file if it's valid and empty in the issuer.
            $url = filter_var($this->badgeconfig->badgeConnectAPI[0]->image, FILTER_SANITIZE_URL);
            // Remove multiple slashes in URL. It will fix the Badgr bug with image URL defined in their manifest.
            $url = preg_replace('/([^:])(\/{2,})/', '$1/', $url);
            if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
                $this->issuer->set('image', $url);
            }
        }

        $this->configread = true;
    }

    /**
     * Self-register the badge issuer if the registration endpoint exists and client id and secret aren't defined.
     *
     * @return void
     */
    protected function dynamic_client_registration(): void {
        global $CFG, $SITE;

        $clientid = $this->issuer->get('clientid');
        $clientsecret = $this->issuer->get('clientsecret');

        // Registration request for getting client id and secret will be done only if they are empty in the issuer.
        if (empty($clientid) && empty($clientsecret)) {
            $registrationurl = $this->endpoints['registration_endpoint']->get('url');

            $scopes = str_replace("\r", " ", implode(' ', $this->badgeconfig->badgeConnectAPI[0]->scopesOffered));

            // Add slash at the end of the site URL.
            $hosturl = $CFG->wwwroot;
            $hosturl .= (substr($CFG->wwwroot, -1) == '/' ? '' : '/');

            // Create the registration request following the format defined in the IMS OBv2.1 specification.
            $request = [
                'client_name' => $SITE->fullname,
                'client_uri' => $hosturl,
                'logo_uri' => $hosturl . 'pix/f/moodle-256.png',
                'tos_uri' => $hosturl,
                'policy_uri' => $hosturl,
                'software_id' => 'moodle',
                'software_version' => $CFG->version,
                'redirect_uris' => [
                    $hosturl . 'admin/oauth2callback.php'
                ],
                'token_endpoint_auth_method' => 'client_secret_basic',
                'grant_types' => [
                    'authorization_code',
                    'refresh_token'
                ],
                'response_types' => [
                    'code'
                ],
                'scope' => $scopes
            ];
            $jsonrequest = json_encode($request);

            $this->curl->setHeader(['Content-type: application/json']);
            $this->curl->setHeader(['Accept: application/json']);

            // Send the registration request.
            if (!$jsonresponse = $this->curl->post($registrationurl, $jsonrequest)) {
                $msg = 'Could not self-register badge issuer: ' . $this->issuer->get('name') .
                    ". Wrong URL or JSON data [URL: $registrationurl]";
                throw new \moodle_exception($msg);
            }

            // Process the response and update client id and secret if they are valid.
            $response = json_decode($jsonresponse);
            if (property_exists($response, 'client_id')) {
                $this->issuer->set('clientid', $response->client_id);
                $this->issuer->set('clientsecret', $response->client_secret);
            } else {
                $msg = 'Could not self-register badge issuer: ' . $this->issuer->get('name') .
                    '. Invalid response ' . $jsonresponse;
                throw new \moodle_exception($msg);
            }
        }
    }
}
