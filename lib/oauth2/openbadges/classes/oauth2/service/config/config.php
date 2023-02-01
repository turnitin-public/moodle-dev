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

namespace oauth2service_openbadges\oauth2\service\config;

use \core\oauth2\service\config\config as configbase;

/**
 * Open Badges OAuth 2 service plugin config.
 *
 * @package    oauth2service_openbadges
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config extends configbase {

    /**
     * Get the config for Open Badges issuers.
     *
     * Open Badges 2.1 requires OAuth 2.0 Dynamic Client Registration.
     * See:
     * https://www.imsglobal.org/spec/ob/v2p1#authentication
     * https://www.rfc-editor.org/rfc/rfc7591
     *
     * Service base URL is required to determine the discovery endpoint which in turn provides the registration endpoint.
     *
     * @return array
     */
    protected function get_config(): array {
        return [
            'discovery' => configbase::DISCOVERY_SUPPORTED,
            'dynamic_client_registration' => configbase::DYNAMIC_CLIENT_REGISTRATION_SUPPORTED,
            'baseurl_required' => true,
        ];
    }
}
