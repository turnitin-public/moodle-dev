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

namespace oauth2service_openbadges\oauth2\service\discovery;

use core\oauth2\discovery\auth_server_config_reader;

/**
 * Simple reader class, allowing configuration information to be read from an Open Badges 2.1 manifest.
 *
 * As per https://www.imsglobal.org/spec/ob/v2p1#manifest
 *
 * @package    oauth2service_openbadges
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openbadges_config_reader extends auth_server_config_reader {

    /**
     * Parse the endpoints from the last read - badge specific JSON.
     *
     * @return void
     */
    protected function parse_endpoints_from_last_read(): void {
        foreach ($this->metadata->badgeConnectAPI[0] as $key => $value) {
            if (substr_compare($key, 'Url', - strlen('Url')) === 0 && !empty($value)) {
                // Convert key names from xxxxUrl to xxxx_endpoint, in order to make it compliant with the Moodle oAuth API.
                $name = strtolower(substr($key, 0, -strlen('Url'))) . '_endpoint';
                $this->endpoints[$name] = $value;
            }
        }
    }

    /**
     * Get the URL where the badge manifest can be found.
     *
     * Per https://www.imsglobal.org/spec/ob/v2p1#manifest (and per https://www.rfc-editor.org/rfc/rfc5785).
     *
     * @return \moodle_url the well-known badge manifest URL.
     */
    public function get_configuration_url(): \moodle_url {
        // Add slash at the end of the issuer url, if required, and append the OIDC discovery well known string.
        // Per the spec, to allow multiple issuers per host, any path component in the issuer URL is included in the discovery URL.
        $url = $this->issuerurl->out(false);

        // TODO: Per the badge spec and per RFC5785, the well known should not be at directory level,
        //  which is possible given the code below. We need to strip the path to resolve this.
        $url .= (substr($url, -1) == '/' ? '' : '/');
        $url .= '.well-known/badgeconnect.json';

        return new \moodle_url($url);
    }
}
