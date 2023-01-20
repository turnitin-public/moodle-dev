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

/**
 * Helper for oauth2service plugins.
 */
class helper {

    /**
     * Gets a list of names for all available, enabled oauth2services plugins.
     *
     * @return array the array of names.
     */
    public static function get_service_names(): array {
        $pluginman = \core_plugin_manager::instance();
        $names = [];
        foreach ($pluginman->get_enabled_plugins('oauth2service') as $plugin) {
            $classname = self::get_service_classname($plugin);
            $names[$classname::get_name()] = $classname::get_name();
        }
        return $names;
    }

    /**
     * Get the fully qualified classname of the oauth2service plugin's service class.
     *
     * @param string $pluginname the string name of the oauth2service plugin.
     * @return string the fully qualified classname.
     */
    public static function get_service_classname(string $pluginname): string {
        $serviceclass = "oauth2service\\{$pluginname}\\service";
        if (class_exists($serviceclass) && is_subclass_of($serviceclass, service::class)) {
            return $serviceclass;
        }
        return 'core\\oauth2\\service\\custom\\custom';
    }

    /**
     * Get an instance of the service class for the plugin which matches the given issuer.
     *
     * @param issuer $issuer the issuer
     * @return \core\oauth2\service\service an instance of the service.
     */
    public static function get_service_instance(issuer $issuer): service {
        $issuertype = $issuer->get('servicetype');
        if (!empty($issuertype)) {
            $serviceclass = "oauth2service\\{$issuertype}\\service";
            if (class_exists($serviceclass) && is_subclass_of($serviceclass, service::class)) {
                return $serviceclass::get_instance($issuer);
            }
        }

        $defaultclass = 'core\\oauth2\\service\\custom\\custom';
        return $defaultclass::get_instance($issuer);
    }
}
