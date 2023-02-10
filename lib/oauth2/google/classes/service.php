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

    /** @var issuer the issuer instance this plugin receives after form submission. */
    protected issuer $issuer;

    /** @var openid_config_reader a config reader for openid connect. */
    protected openid_config_reader $configreader;

    /** @var bool whether or not the configuration has already been read for the service instance. */
    protected bool $configread = false;

    /** @var \stdClass The OpenID configuration for the service. */
    protected \stdClass $openidconfig;

    /** @var array the OAuth 2 endpoints found in the OpenID configuration. */
    protected array $endpoints = [];

    /**
     * Constructor.
     *
     * @param issuer $issuer an issuer instance.
     * @param openid_config_reader $configreader an openid configuration reader instance.
     */
    public function __construct(issuer $issuer, openid_config_reader $configreader) {
        $this->issuer = $issuer;
        $this->configreader = $configreader;
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
        $this->read_openid_configuration();

        return $this->issuer;
    }

    public function get_endpoints(): array {
        $this->read_openid_configuration();

        return array_values($this->endpoints);
    }

    public function get_field_mappings(): array {
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

        $m = [];
        foreach ($mapping as $external => $internal) {
            $record = (object) [
                'externalfield' => $external,
                'internalfield' => $internal
            ];
            $m[] = new user_field_mapping(0, $record);
        }
        return $m;
    }

    /**
     * Read the OpenID configuration from the well-known endpoint and store it locally.
     *
     * @return void
     */
    protected function read_openid_configuration(): void {
        $issuerbaseurl = $this->issuer->get('baseurl');

        if ($this->configread || empty($issuerbaseurl)) {
            return;
        }

        $this->openidconfig = $this->configreader->read_configuration(new \moodle_url($issuerbaseurl));

        foreach ($this->configreader->get_endpoints() as $name => $url) {
            $record = (object) [
                'name' => $name,
                'url' => $url
            ];
            $this->endpoints[$record->name] = new endpoint(0, $record);
        }

        if (!empty($this->openidconfig->scopes_supported)) {
            $this->issuer->set('scopessupported', implode(' ', $this->openidconfig->scopes_supported));
        }

        $this->configread = true;
    }
}
