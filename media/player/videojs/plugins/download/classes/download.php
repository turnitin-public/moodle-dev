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
 * VideoJS download plugin class.
 *
 * @package     videojs_download
 * @copyright   2019 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace videojs_download;

use media_videojs\pluginbase\pluginbase;

defined('MOODLE_INTERNAL') || die();

/**
 * VideoJS download plugin class.
 *
 * @package     videojs_download
 * @copyright   2019 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class download extends pluginbase {

    /**
     * This method returns the plugin specific configuration.
     * It must be implemented, but can return an empty \stdClass.
     *
     * @return \stdClass $config The configuration options for the plugin.
     */
    public function get_plugin_config() : \stdClass {
        $config = new \stdClass();
        $config->beforeElement = 'playbackRateMenuButton';
        $config->name = 'downloadButton';
        $config->textControl = get_string('download', 'videojs_download');

        return $config;
    }

}
