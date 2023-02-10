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

namespace oauth2service_linkedin;

use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\user_field_mapping;

/**
 * LinkedIn OAuth 2 service plugin class.
 *
 * @package    oauth2service_linkedin
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
     * @param issuer $issuer the issuer instance this plugin receives after form submission.
     */
    public function __construct(protected issuer $issuer) {
    }

    public static function get_instance(issuer $issuer): \core\oauth2\service\service {
        return new self($issuer);
    }

    public static function get_template(): ?issuer {
        $record = (object) [
            'name' => get_string(self::get_config()->get_full_config()['service_shortname'], 'oauth2service_linkedin'),
            'image' => 'https://static.licdn.com/scds/common/u/images/logos/favicons/v1/favicon.ico',
            'baseurl' => 'https://api.linkedin.com/v2',
            'loginscopes' => 'r_liteprofile r_emailaddress',
            'loginscopesoffline' => 'r_liteprofile r_emailaddress',
            'showonloginpage' => issuer::EVERYWHERE,
        ];

        return new issuer(0, $record);
    }

    public function get_issuer(): issuer {
        return $this->issuer;
    }

    public function get_endpoints(): array {
        $endpoints = [
            'authorization_endpoint' => 'https://www.linkedin.com/oauth/v2/authorization',
            'token_endpoint' => 'https://www.linkedin.com/oauth/v2/accessToken',
            'email_endpoint' => 'https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))',
            'userinfo_endpoint' => "https://api.linkedin.com/v2/me?projection=(localizedFirstName,localizedLastName,"
                . "profilePicture(displayImage~digitalmediaAsset:playableStreams))",
        ];
        $this->endpoints = [];
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
            'localizedFirstName' => 'firstname',
            'localizedLastName' => 'lastname',
            'elements[0]-handle~-emailAddress' => 'email',
            'profilePicture-displayImage~-elements[0]-identifiers[0]-identifier' => 'picture'
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
