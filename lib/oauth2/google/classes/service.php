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

namespace oauth2service_google;

use core\http_client;
use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\discovery\openid_config_reader;
use core\oauth2\user_field_mapping;

/**
 * Google OAuth 2 service plugin class.
 *
 * @package    oauth2service_google
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service extends \core\oauth2\service\service {

    /** @var bool whether the service configuration has already been read. */
    protected bool $configread = false;

    /** @var array the OAuth 2 endpoints found in the OpenID configuration. */
    protected array $endpoints = [];

    /** @var array the array of user field mapping instances. */
    protected array $userfieldmapping = [];

    /**
     * Constructor.
     *
     * @param issuer $issuer the issuer instance this plugin receives after form submission.
     * @param openid_config_reader $configreader an openid configuration reader instance.
     */
    public function __construct(protected issuer $issuer, protected openid_config_reader $configreader) {
    }

    public static function get_instance(issuer $issuer): \core\oauth2\service\service {
        return new self($issuer, new openid_config_reader(new http_client()));
    }

    public static function get_template(): ?issuer {
        $record = (object) [
            'name' => get_string(self::get_config()->get_full_config()['service_shortname'], 'oauth2service_google'),
            'image' => 'https://accounts.google.com/favicon.ico',
            'baseurl' => 'https://accounts.google.com/',
            'loginparamsoffline' => 'access_type=offline&prompt=consent',
            'showonloginpage' => issuer::EVERYWHERE,
        ];
        return new issuer(0, $record);
    }

    public function get_issuer(): issuer {
        $this->read_configuration();
        return $this->issuer;
    }

    public function get_endpoints(): array {
        $this->read_configuration();
        return array_values($this->endpoints);
    }

    public function get_field_mappings(): array {
        // User field mapping only returned when the service supports openid metadata discovery.
        $this->read_configuration();
        if (!$this->configread) {
            return [];
        }

        $mapping = [
            'given_name' => 'firstname',
            'middle_name' => 'middlename',
            'family_name' => 'lastname',
            'email' => 'email',
            'nickname' => 'alternatename',
            'picture' => 'picture',
            'address' => 'address',
            'phone' => 'phone1',
            'locale' => 'lang',
        ];

        foreach ($mapping as $external => $internal) {
            $record = (object) [
                'externalfield' => $external,
                'internalfield' => $internal
            ];
            $this->userfieldmapping[] = new user_field_mapping(0, $record);
        }
        return $this->userfieldmapping;
    }

    public function validation(array $coreerrors): array {
        $errors = [];
        if (!isset($coreerrors['baseurl']) && !empty($this->issuer->get('baseurl'))) {
            // The 'baseurl' field is used to find the openid config. Make sure this URL is suitable for that.
            $base = new \moodle_url($this->issuer->get('baseurl'));
            $querystring = (!empty($base->get_query_string()));
            $badcheme = (strtolower($base->get_scheme()) !== 'https');
            // This last bit catches URL fragments. If the query string is empty, out_omit_querystring(false) returns only
            // fragments.
            $fragments = ($base->out_omit_querystring() != $base->out(false));

            if ($querystring || $badcheme || $fragments) {
                $errors['baseurl'] = 'The base URL is not valid for use with discovery. It must be an HTTPS URL without query '.
                    'strings or parameters.';
            } else {
                // URL is suitable. Make sure the config can be read before allowing form save.
                try {
                    $this->read_configuration();
                } catch (\Exception $e) {
                    $errors['baseurl'] = 'The OpenId configuration could not be read from '
                        . $this->configreader->get_last_read_config_url()->out(false) . '. If the service doesn\'t support '.
                        'configuration discovery, this should be left blank';
                }
            }
        }
        return $errors;
    }

    /**
     * Read the OpenID configuration from the well-known endpoint and store it locally.
     *
     * @return void
     */
    protected function read_configuration(): void {
        $issuerbaseurl = $this->issuer->get('baseurl');

        if ($this->configread || empty($issuerbaseurl)) {
            return;
        }

        // Only read from the remote once per request, which permits checking the configuration endpoint during form validation.
        $cache = \cache::make('oauth2service_custom', 'openidconfiguration');
        if (!$openidconfig = $cache->get($issuerbaseurl)) {
            try {
                $openidconfig = $this->configreader->read_configuration(new \moodle_url($issuerbaseurl));

                // This isn't openid config per se, but it's nice to have included in the list of endpoints.
                $openidconfig->discovery_endpoint = $this->configreader->get_last_read_config_url()->out(false);

                $cache->set($issuerbaseurl, $openidconfig);
            } catch (\Exception $e) {
                throw new \moodle_exception("Server metadata for issuer '{$this->issuer->get('name')}' not found. 
                    The configuration document could not be read.");
            }
        }

        // Process the config.
        foreach ($openidconfig as $key => $value) {
            if (substr_compare($key, '_endpoint', - strlen('_endpoint')) === 0) {
                $record = (object) [
                    'name' => $key,
                    'url' => $value
                ];
                $this->endpoints[$key] = new endpoint(0, $record);
            }
        }

        if (!empty($openidconfig->scopes_supported)) {
            $this->issuer->set('scopessupported', implode(' ', $openidconfig->scopes_supported));
        }

        $this->configread = true;
    }
}
