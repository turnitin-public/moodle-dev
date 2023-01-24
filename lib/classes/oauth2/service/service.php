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

namespace core\oauth2\service;

use core\oauth2\issuer;
use core\oauth2\user_field_mapping;
use core\oauth2\endpoint;
use core\oauth2\service\config\config;

/**
 * Defines the API which OAuth 2 services must implement.
 *
 * @package    core
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class service {

    /**
     * Return the \core\oauth2\issuer persistent, containing the default configuration for the service.
     *
     * @return issuer|null the issuer containing service-specific data, or null if the service doesn't require any specific data.
     */
    public abstract static function get_template(): ?issuer;

    /**
     * Get the plugin config.
     *
     * Plugins should override this if they have a config which differs from the default provided in {@see config}.
     * They must provide a subclass of {@see \core\oauth2\service\config\config}.
     *
     * @return config the config object.
     */
    public static function get_config(): config {
        return new config();
    }

    /**
     * Static factory method to return an instance of the service.
     *
     * {@see \core\oauth2\service\service} which must be extended by plugins.
     *
     * @param issuer $issuer the issuer record.
     * @return service the service instance.
     */
    public abstract static function get_instance(issuer $issuer): service;

    /**
     * Get the complete issuer data from the plugin.
     *
     * Plugins may wish to generate issuer data using a process such as dynamic client registration, rather than capturing all
     * required data in the form. This method is called to get that issuer data..
     *
     * Plugins which do not need to augment the issuer data post-submission can just return their unmodified issuer instance.
     *
     * @return issuer
     */
    public abstract function get_issuer(): issuer;

    /**
     * Get the OAuth 2 endpoints for the service, whether it be via an
     *
     * @return endpoint[]
     */
    public abstract function get_endpoints(): array;

    /**
     * Return the user_field_mapping instances, if the service supports OIDC or some form of OAuth2 sign in.
     *
     * @return user_field_mapping[]
     */
    public function get_field_mappings(): array {
        return [];
    }
}
