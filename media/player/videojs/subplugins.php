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
 * VideoJS subplugin management.
 *
 * @package   media_videojs
 * @copyright 2019 Matt Porritt <mattp@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$action  = required_param('action', PARAM_ALPHANUMEXT);
$plugin   = required_param('plugin', PARAM_PLUGIN);

require_admin();
require_sesskey();

$plugins = core_plugin_manager::instance()->get_plugins_of_type('videojs');
$returnurl = new moodle_url('/admin/settings.php', array('section' => 'videojssubsettings'));

if (!array_key_exists($plugin, $plugins)) {
    redirect($returnurl);
}

switch ($action) {
    case 'disable':
        $plugins[$plugin]->set_enabled(false);
        break;

    case 'enable':
        $plugins[$plugin]->set_enabled(true);
        break;
}

redirect($returnurl);
