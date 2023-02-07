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

namespace oauth2service_facebook;

use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\user_field_mapping;

/**
 * Facebook OAuth 2 service plugin class.
 *
 * @package    oauth2service_facebook
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service extends \core\oauth2\service\service {

    /** @var array the OAuth 2 endpoints found in the OpenID configuration. */
    protected array $endpoints = [];

    /** @var array the array of user field mappings for this provider. */
    protected array $userfieldmappings = [];

    /**
     * Constructor.
     *
     * @param issuer $issuer the issuer instance this plugin receives after form submission
     */
    public function __construct(protected issuer $issuer) {
    }

    public static function get_instance(issuer $issuer): \core\oauth2\service\service {
        return new self($issuer);
    }

    public static function get_template(): ?issuer {
        $record = (object) [
            'name' => get_string(self::get_config()->get_full_config()['service_shortname'], 'oauth2service_facebook'),
            'image' => 'https://facebookbrand.com/wp-content/uploads/2016/05/flogo_rgb_hex-brc-site-250.png',
            'baseurl' => '',
            'loginscopes' => 'public_profile email',
            'loginscopesoffline' => 'public_profile email',
            'showonloginpage' => issuer::EVERYWHERE,
        ];

        return new issuer(0, $record);
    }

    public function get_issuer(): issuer {
        return $this->issuer;
    }

    public function get_endpoints(): array {
        // The Facebook API version.
        $apiversion = '2.12';
        // The Graph API URL.
        $graphurl = 'https://graph.facebook.com/v' . $apiversion;
        // User information fields that we want to fetch.
        $infofields = [
            'id',
            'first_name',
            'last_name',
            'picture.type(large)',
            'name',
            'email',
        ];
        $endpoints = [
            'authorization_endpoint' => sprintf('https://www.facebook.com/v%s/dialog/oauth', $apiversion),
            'token_endpoint' => $graphurl . '/oauth/access_token',
            'userinfo_endpoint' => $graphurl . '/me?fields=' . implode(',', $infofields)
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
            'name' => 'alternatename',
            'last_name' => 'lastname',
            'email' => 'email',
            'first_name' => 'firstname',
            'picture-data-url' => 'picture',
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
