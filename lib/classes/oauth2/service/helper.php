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

use core\oauth2\client;
use core\oauth2\issuer;
use core_oauth2\form\issuer as issuerform;

/**
 * Helper for interacting with oauth2service plugins.
 */
final class helper {

    private static function check_plugin_enabled($pluginname): void {
        if (!in_array($pluginname, \core_plugin_manager::instance()->get_enabled_plugins('oauth2service'))) {
            throw new \coding_exception("Error: '$pluginname' is not a valid OAuth 2 service plugin or is disabled.");
        }
    }

    public static function get_service_issuer_form(string $pluginname, ?issuer $issuer = null, array $customdata): issuerform {
        self::check_plugin_enabled($pluginname);

        $classname = self::get_service_classname($pluginname);
        $serviceconfig = $classname::get_config();
        $data = array_merge($customdata, ['persistent' => $issuer, 'type' => $pluginname, 'serviceconfig' => $serviceconfig]);
        return new issuerform(null, $data);
    }

    /**
     * Get the short name of the service plugin.
     *
     * @param string $pluginname
     * @return string
     * @throws \coding_exception
     */
    public static function get_service_shortname(string $pluginname): string {
        self::check_plugin_enabled($pluginname);

        $classname = self::get_service_classname($pluginname);
        return get_string($classname::get_config()->get_full_config()['service_shortname'], "oauth2service_$pluginname");
    }

    /**
     * Gets a list of names for all available, enabled oauth2services plugins.
     *
     * @return array the array containing [pluginname => shortname] for each plugin type.
     */
    public static function get_service_names(): array {
        $pluginman = \core_plugin_manager::instance();
        $names = [];
        foreach ($pluginman->get_enabled_plugins('oauth2service') as $plugin) {
            $names[$plugin] = self::get_service_shortname($plugin);
        }
        return $names;
    }

    /**
     * Get the fully qualified classname of the oauth2service plugin's service class.
     *
     * @param string $pluginname the string name of the oauth2service plugin.
     * @return string the fully qualified classname.
     * @throws \coding_exception if the pluginname is invalid.
     */
    public static function get_service_classname(string $pluginname): string {
        self::check_plugin_enabled($pluginname);

        $serviceclass = "oauth2service_$pluginname\\service";
        if (class_exists($serviceclass) && is_subclass_of($serviceclass, service::class)) {
            return $serviceclass;
        }
        throw new \coding_exception("Error: '$pluginname' is not a valid OAuth 2 service plugin.");
    }

    /**
     * Get an instance of the service class for the plugin which matches the given issuer.
     *
     * @param issuer $issuer the issuer record.
     * @return \core\oauth2\service\service an instance of the service.
     */
    public static function get_service_instance(issuer $issuer): service {
        self::check_plugin_enabled($issuer->get('servicetype'));

        $classname = self::get_service_classname($issuer->get('servicetype'));
        return $classname::get_instance($issuer);
    }

    /**
     * Get the fully qualified classname of the oauth2service plugin's client class.
     *
     * @param string $pluginname
     * @return string the fully qualified classname.
     * @throws \coding_exception if the pluginname is invalid.
     */
    public static function get_client_classname(string $pluginname): string {
        self::check_plugin_enabled($pluginname);

        $clientclass = "oauth2service_$pluginname\\client";
        if (class_exists($clientclass) && is_subclass_of($clientclass, client::class)) {
            return $clientclass;
        }
        return "core\\oauth2\\client";
    }
}
