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

namespace core_oauth2\local\settings;

/**
 * OAuth 2.0 Services admin setting.
 *
 * @package    core_oauth2
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_oauth2service_plugins extends \admin_setting_manage_plugins {
    /**
     * Get the admin settings section title (use get_string).
     *
     * @return string
     */
    public function get_section_title() {
        return get_string('type_oauth2service_plural', 'plugin');
    }

    /**
     * Get the type of plugin to manage.
     *
     * @return string
     */
    public function get_plugin_type() {
        return 'oauth2service';
    }

    /**
     * Get the name of the second column.
     *
     * @return string
     */
    public function get_info_column_name() {
        return '';
    }

    /**
     * Get the type of plugin to manage.
     *
     * @param plugininfo The plugin info class.
     * @return string
     */
    public function get_info_column($plugininfo) {
        return '';
    }
}
