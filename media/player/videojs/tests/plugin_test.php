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
 * Test class for videojs plugin.
 *
 * @package media_videojs
 * @copyright  2019 Matt Porritt <mattp@catalyst-au.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Test plugin methods.
 *
 * @package media_videojs
 * @copyright  2019 Matt Porritt <mattp@catalyst-au.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_videojs_plugin_testcase extends advanced_testcase {

    /**
     * Test that we can get the sub plugin info.
     */
    public function test_get_videojs_plugins() {
        $this->resetAfterTest();
        set_config('enabled_plugins', 'download', 'media_videojs');

        $videojs = new \media_videojs_plugin();

        // We're testing a private method, so we need to setup reflector magic.
        $method = new ReflectionMethod('media_videojs_plugin', 'get_videojs_plugins');
        $method->setAccessible(true); // Allow accessing of private method.
        list($pluginamdlist, $pluginamdconfig) = $method->invoke($videojs); // Get result of invoked method.

        $this->assertEquals('videojs_download/download', $pluginamdlist[0]);
        $this->assertObjectHasAttribute('download', $pluginamdconfig);
    }
}
