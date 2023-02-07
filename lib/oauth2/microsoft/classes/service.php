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

namespace oauth2service_microsoft;

use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\user_field_mapping;

/**
 * Microsoft OAuth 2 service plugin class.
 *
 * @package    oauth2service_microsoft
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
            'name' => get_string(self::get_config()->get_full_config()['service_shortname'], 'oauth2service_microsoft'),
            'image' => 'https://www.microsoft.com/favicon.ico',
            'baseurl' => '',
            'loginscopes' => 'openid profile email user.read',
            'loginscopesoffline' => 'openid profile email user.read offline_access',
            'showonloginpage' => issuer::EVERYWHERE,
        ];

        return new issuer(0, $record);
    }

    public function get_issuer(): issuer {
        return $this->issuer;
    }

    public function get_endpoints(): array {
        // TODO this can definitely be done with oidc, via the https://login.microsoftonline.com/common/v2.0/.well-known/openid-configuration
        //  see: https://learn.microsoft.com/en-us/azure/active-directory/develop/v2-protocols-oidc#fetch-the-openid-configuration-document
        //  set the baseURL and let this happen...
        $endpoints = [
            'authorization_endpoint' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token_endpoint' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'userinfo_endpoint' => 'https://graph.microsoft.com/v1.0/me/',
            'userpicture_endpoint' => 'https://graph.microsoft.com/v1.0/me/photo/$value',
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
            'givenName' => 'firstname',
            'surname' => 'lastname',
            'userPrincipalName' => 'email',
            'displayName' => 'alternatename',
            'officeLocation' => 'address',
            'mobilePhone' => 'phone1',
            'preferredLanguage' => 'lang'
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
