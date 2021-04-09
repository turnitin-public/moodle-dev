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
 * Contains the launch_cache_session class.
 *
 * @package    enrol_lti
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\local\ltiadvantage;

use IMSGlobal\LTI13\Cache;

/**
 * The launch_cache_session, providing a temporary session store for launch information.
 *
 * This is used to store the launch information while the user is transitioned through the Moodle authentication flows
 * and back to the deep linking launch handler (launch_deeplink.php).
 *
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class launch_cache_session implements Cache {

    /**
     * Get the launch data from the cache.
     *
     * @param string $key the launch id.
     * @return array the launch data.
     */
    public function get_launch_data($key) {
        global $SESSION;
        if (isset($SESSION->enrol_lti_launch[$key])) {
            return unserialize($SESSION->enrol_lti_launch[$key]);
        }
        return null;
    }

    /**
     * Add launch data to the cache.
     *
     * @param string $key the launch id.
     * @param array $jwt_body the launch data.
     * @return $this this object instance.
     */
    public function cache_launch_data($key, $jwt_body) {
        global $SESSION;
        $SESSION->enrol_lti_launch[$key] = serialize($jwt_body);
        return $this;
    }

    /**
     * Cache the nonce.
     *
     * @param string $nonce the nonce.
     * @return $this this object instance.
     */
    public function cache_nonce($nonce) {
        global $SESSION;
        $SESSION->enrol_lti_launch_nonce[$nonce] = true;
        return $this;
    }

    /**
     * Check whether the cache contains the nonce.
     *
     * @param string $nonce the nonce
     * @return bool true if found, false otherwise.
     */
    public function check_nonce($nonce) {
        global $SESSION;
        return isset($SESSION->enrol_lti_launch_nonce[$nonce]);
    }

    /**
     * Delete all data from the session cache.
     */
    public function purge() {
        global $SESSION;
        unset($SESSION->enrol_lti_launch);
    }
}
