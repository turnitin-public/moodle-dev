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

namespace core\plugininfo;

use admin_settingpage;

/**
 * Plugininfo class for oauth2service plugins.
 */
class oauth2service extends base {

    public function is_uninstall_allowed() {
        return true;
    }

    public function get_settings_section_name() {
        return 'oauth2servicesetting' . $this->name;
    }

    /**
     * Finds all enabled plugins, the result may include missing plugins.
     * @return array|null of enabled plugins $pluginname=>$pluginname, null means unknown
     */
    public static function get_enabled_plugins() {
        global $CFG;

        $order = (!empty($CFG->oauth2service_plugins_sortorder)) ? explode(',', $CFG->oauth2service_plugins_sortorder) : [];
        if ($order) {
            $plugins = \core_plugin_manager::instance()->get_installed_plugins('oauth2service');
            $order = array_intersect($order, array_keys($plugins));
        }

        return array_combine($order, $order);
    }

    public static function enable_plugin(string $pluginname, int $enabled): bool {
        global $CFG;

        $haschanged = false;
        $plugins = [];
        if (!empty($CFG->oauth2service_plugins_sortorder)) {
            $plugins = array_flip(explode(',', $CFG->oauth2service_plugins_sortorder));
        }
        // Only set visibility if it's different from the current value.
        if ($enabled && !array_key_exists($pluginname, $plugins)) {
            $plugins[$pluginname] = $pluginname;
            $haschanged = true;
        } else if (!$enabled && array_key_exists($pluginname, $plugins)) {
            unset($plugins[$pluginname]);
            $haschanged = true;
        }

        if ($haschanged) {
            add_to_config_log('oauth2service_plugins_sortorder', !$enabled, $enabled, $pluginname);
            self::set_enabled_plugins(array_flip($plugins));
        }

        return $haschanged;
    }

    /*public function load_settings(\part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // In case settings.php wants to refer to them.
        $ADMIN = $adminroot; // May be used in settings.php.
        $plugininfo = $this; // Also can be used inside settings.php
        $oauth2service = $this; // Also to be used inside settings.php.

        if (!$this->is_installed_and_upgraded()) {
            return;
        }

        if (!$hassiteconfig) {
            return;
        }

        $section = $this->get_settings_section_name();

        $settings = null;
        if (file_exists($this->full_path('settings.php'))) {
            $settings = new admin_settingpage($section, $this->displayname, 'moodle/site:config', $this->is_enabled() === false);
            include($this->full_path('settings.php')); // This may also set $settings to null.
        }
        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }*/

    /**
     * Return URL used for management of plugins of this type.
     * @return \moodle_url
     */
    public static function get_manage_url() {
        return new \moodle_url('/admin/settings.php', array('section'=>'manageoauth2services'));
    }

    /**
     * Sets the current plugin as enabled or disabled
     * When enabling tries to guess the sortorder based on default rank returned by the plugin.
     * @param bool $newstate
     */
    public function set_enabled($newstate = true) {
        self::enable_plugin($this->name, $newstate);
    }

    /**
     * Set the list of enabled converter players in the specified sort order
     * @param string|array $list list of plugin names without frankenstyle prefix - comma-separated string or an array
     */
    public static function set_enabled_plugins($list) {
        if (empty($list)) {
            $list = [];
        } else if (!is_array($list)) {
            $list = explode(',', $list);
        }
        if ($list) {
            $plugins = \core_plugin_manager::instance()->get_installed_plugins('oauth2service');
            $list = array_intersect($list, array_keys($plugins));
        }
        set_config('oauth2service_plugins_sortorder', join(',', $list));
        \core_plugin_manager::reset_caches();
    }
}
