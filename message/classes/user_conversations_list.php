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
 * Cache the list of current conversations for a user.
 *
 * @package    core_message
 * @category   cache
 * @copyright  2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_message;

use cache_definition;

defined('MOODLE_INTERNAL') || die();

/**
 * Cache the list of current conversations for a user.
 *
 * @package    core_message
 * @category   cache
 * @copyright  2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_conversations_list implements \cache_data_source {

    /** @var user_conversations_list the singleton instance of this class. */
    protected static $cacheinstance;

    /**
     * Returns an instance of the data source class that the cache can use for loading data using the other methods
     * specified by this interface.
     *
     * @param cache_definition $definition
     * @return object
     */
    public static function get_instance_for_cache(cache_definition $definition) {
        if (is_null(self::$cacheinstance)) {
            self::$cacheinstance = new user_conversations_list();
        }
        return self::$cacheinstance;
    }

    /**
     * Loads the data for the key provided ready formatted for caching.
     *
     * @param string|int $key The key to load.
     * @return mixed What ever data should be returned, or false if it can't be loaded.
     */
    public function load_for_cache($key) {
        error_log('IN LOAD FOR CACHE');
        return null;
        $conversations = api::get_conversations($key);

        if ($conversations) {
            return $conversations;
        }
        return null;
    }

    /**
     * Loads several keys for the cache.
     *
     * @param array $keys An array of keys each of which will be string|int.
     * @return array An array of matching data items.
     */
    public function load_many_for_cache(array $keys) {
        // TODO: Implement load_many_for_cache() method.
    }
}
