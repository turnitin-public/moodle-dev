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

namespace core\oauth2\service\config;

/**
 * Base config class using a template method to merge plugin config with defaults.
 *
 * @package    core
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config {

    // Dynamic registration behaviours.
    const DYNAMIC_CLIENT_REGISTRATION_UNSUPPORTED = 0; // Service does not support registration.
    const DYNAMIC_CLIENT_REGISTRATION_SUPPORTED = 1; // Service does support registration, but doesn't require it.

    // Discovery behaviours
    const DISCOVERY_UNSUPPORTED = 0;
    const DISCOVERY_SUPPORTED = 1;

    /**
     * Plugin config defaults, used when a plugin doesn't provide an override value.
     *
     * @var array array of config defaults.
     */
    private $defaults = [
        'discovery' => self::DISCOVERY_SUPPORTED,
        'dynamic_client_registration' => self::DYNAMIC_CLIENT_REGISTRATION_UNSUPPORTED,
        'baseurl_required' => false, // Note: This is a form control only. It determines whether baseurl is validated as required.
        'service_shortname' => 'pluginname' // Lang string identifier for the service shortname. Defaults to 'pluginname'.
    ];

    /**
     * Get the full config for a plugin, consisting of any plugin-supplied values merged with defaults.
     *
     * @return array the configuration data.
     */
    final public function get_full_config(): array {
        // Note: This doesn't fix bad plugin values.
        // TODO probably need to check keys and void keys which aren't valid / call debugging() etc.
        return array_merge($this->defaults, $this->get_config());
    }

    /**
     * Return the config values for the plugin. Plugins override if using non-default config.
     *
     * @return array
     */
    protected function get_config(): array {
        return [];
    }
}
