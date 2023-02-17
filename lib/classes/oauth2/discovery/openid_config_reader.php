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

namespace core\oauth2\discovery;

use core\http_client;

/**
 * Config reader allowing OpenID Provider configuration information to be read from the issuer's well-known endpoint.
 *
 * As per https://openid.net/specs/openid-connect-discovery-1_0.html.
 *
 * @package    core
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openid_config_reader extends auth_server_config_reader {

    /**
     * Constructor.
     *
     * @param http_client $httpclient an http_client instance.
     */
    public function __construct(http_client $httpclient) {
        parent::__construct($httpclient, 'openid-configuration');
    }

    /**
     * Get the OpenID Configuration URL.
     *
     * Per https://openid.net/specs/openid-connect-discovery-1_0.html#ProviderConfigurationRequest, if the issuer URL contains a
     * path component, the well known is added AFTER that. This differs from the OAuth 2 Authorization Server Metadata format,
     * where the well known is inserted between the host and path components.
     *
     * @return \moodle_url the full URL to the issuer metadata.
     */
    protected function get_configuration_url(): \moodle_url {
        // Regardless of path, append the well known suffix.
        $uri = $this->issuerurl->out(false);
        $uri .= (substr($uri, -1) == '/' ? '' : '/');
        $uri .= ".well-known/$this->wellknownsuffix";

        return new \moodle_url($uri);
    }
}
