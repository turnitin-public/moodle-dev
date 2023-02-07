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

namespace oauth2service_clever;

use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\service\config\config;
use core\oauth2\user_field_mapping;

/**
 * Clever OAuth 2 service plugin class.
 *
 * @package    oauth2service_clever
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service extends \core\oauth2\service\service {

    /** @var array the OAuth 2 endpoints. */
    protected array $endpoints = [];

    /** @var array the array of user field mappings for this provider. */
    protected array $userfieldmappings = [];

    /**
     * Constructor.
     *
     * @param issuer $issuer the issuer instance this plugin receives after form submission.
     */
    public function __construct(protected issuer $issuer) {
    }

    public static function get_instance(issuer $issuer): \core\oauth2\service\service {
        return new self($issuer);
    }

    public static function get_template(): ?issuer {
        $record = (object) [
            'name' => get_string(self::get_config()->get_full_config()['service_shortname'], 'oauth2service_clever'),
            'image' => 'https://apps.clever.com/favicon.ico',
            'basicauth' => 1,
            'baseurl' => '',
            'showonloginpage' => issuer::LOGINONLY,
        ];

        return new issuer(0, $record);
    }

    public static function get_config(): config {
        return new oauth2\service\config\config();
    }

    public function get_issuer(): issuer {
        return $this->issuer;
    }

    public function get_endpoints(): array {
        // TODO this can use openid configuration per https://clever.com/.well-known/openid-configuration
        //  but the userinfo and userdata endpoints will need to be added on top of that.
        $endpoints = [
            'authorization_endpoint' => 'https://clever.com/oauth/authorize',
            'token_endpoint' => 'https://clever.com/oauth/tokens',
            'userinfo_endpoint' => 'https://api.clever.com/v3.0/me',
            'userdata_endpoint' => 'https://api.clever.com/v3.0/users'
        ];

        foreach ($endpoints as $name => $url) {
            $record = (object) [
                'name' => $name,
                'url' => $url
            ];
            $this->endpoints[$name] = new endpoint(0, $record);
        }

        return array_values($this->endpoints);
    }

    public function get_field_mappings(): array {
        $mapping = [
            'data-id' => 'idnumber',
            'data-name-first' => 'firstname',
            'data-name-last' => 'lastname',
            'data-email' => 'email'
        ];

        foreach ($mapping as $external => $internal) {
            $record = (object) [
                'externalfield' => $external,
                'internalfield' => $internal
            ];
            $this->userfieldmappings[] = new user_field_mapping(0, $record);
        }

        return $this->userfieldmappings;
    }
}
