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

namespace oauth2service_nextcloud\oauth2\service\config;

use \core\oauth2\service\config\config as configbase;

/**
 * Nextcloud OAuth 2 service plugin config.
 *
 * @package    oauth2service_nextcloud
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config extends configbase {

    /**
     * Get the config for Nextcloud.
     *
     * Because Nextcloud can be self-hosted, service base URL is required to determine the endpoints
     *
     * @return array
     */
    protected function get_config(): array {
        return [
            'discovery' => configbase::DISCOVERY_UNSUPPORTED,
            'dynamic_client_registration' => configbase::DYNAMIC_CLIENT_REGISTRATION_UNSUPPORTED,
            'baseurl_required' => true,
        ];
    }
}
