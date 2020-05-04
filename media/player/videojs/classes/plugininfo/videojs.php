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

/**
 * Subplugin info class.
 *
 * @package   media_videojs
 * @copyright  2019 Matt Porritt <mattp@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace media_videojs\plugininfo;

use core\plugininfo\base;

defined('MOODLE_INTERNAL') || die();

/**
 * Subplugin info class.
 *
 * @package   media_videojs
 * @copyright  2019 Matt Porritt <mattp@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class videojs extends base {

    /**
     * Yes you can uninstall these plugins if you want.
     * @return bool
     */
    public function is_uninstall_allowed() : bool {
        return true;
    }

    /**
     * {@inheritDoc}
     * @see \core\plugininfo\base::get_settings_section_name()
     */
    public function get_settings_section_name() {
        return 'videojs' . $this->name . 'settings';
    }

    /**
     * Get list of currently enabled VideoJS plugins.
     *
     * @return array|null List of enabled plugins
     */
    public static function get_enabled_plugins() {
        $enabledlist = get_config('media_videojs', 'enabled_plugins');
        $enabled = explode(',', $enabledlist);

        // Make sure enabled plugins are all currently installed plugins.
        $installedplugins = \core_plugin_manager::instance()->get_installed_plugins('videojs');
        $pluginnames = array_keys($installedplugins);
        $enabledplugins = array_intersect($enabled, $pluginnames);

        if (empty($enabledplugins)) {
            return false;
        }

        return array_combine($enabledplugins, $enabledplugins);
    }

    /**
     * Set the list of enabled VideoJS plugins.
     *
     * @param array $list List of plugin names without frankenstyle prefix.
     */
    public static function set_enabled_plugins(array $list) : void {
        $plugins = \core_plugin_manager::instance()->get_installed_plugins('videojs');
        $listtoenable = array_intersect(array_keys($list), array_keys($plugins));
        $enablestring = implode(',', $listtoenable);

        $oldconfig = get_config('media_videojs', 'enabled_plugins');
        set_config('enabled_plugins', $enablestring, 'media_videojs');
        add_to_config_log('enabled_plugins', $oldconfig, $enablestring, 'media_videojs');

        // Copied from other parts of the codebase.
        // UI changes are not always reflected if omitted.
        \core_plugin_manager::reset_caches();
        \core_media_manager::reset_caches();
    }

    /**
     * Loads plugin settings to the settings tree
     *
     * This function usually includes settings.php file in plugins folder.
     * Alternatively it can create a link to some settings page (instance of admin_externalpage)
     *
     * @param \part_of_admin_tree $adminroot
     * @param string $parentnodename
     * @param bool $hassiteconfig whether the current user has moodle/site:config capability
     * @return void
     */
    public function load_settings(\part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) : void {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // In case settings.php wants to refer to them.
        $ADMIN = $adminroot; // May be used in settings.php.
        $plugininfo = $this; // Also can be used inside settings.php.

        if (!$this->is_installed_and_upgraded()) {
            return;
        }

        if (!$hassiteconfig or !file_exists($this->full_path('settings.php'))) {
            return;
        }

        $section = $this->get_settings_section_name();
        $settings = new \admin_settingpage($section, $this->displayname, 'moodle/site:config', $this->is_enabled() === false);
        include($this->full_path('settings.php')); // This may also set $settings to null.

        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }

    /**
     * Sets the current plugin as enabled or disabled.
     * @param bool $newstate The state to update the plugin to. True equals enabled, false equals disabled.
     */
    public function set_enabled($newstate = true) : void {
        $enabled = self::get_enabled_plugins();
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('videojs');

        if ($enabled && (array_key_exists($this->name, $enabled) == $newstate)) {
            // Nothing to do.
            return;
        }
        if ($newstate) { // Enable plugin.
            if (!array_key_exists($this->name, $plugins)) {
                return; // Can not be enabled.
            } else {
                $enabled[$this->name] = true;
            }
        } else { // Disable media plugin.
            unset($enabled[$this->name]);
        }

        self::set_enabled_plugins($enabled);
    }
}
