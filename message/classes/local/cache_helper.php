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
 * Contains the class encapsulating all the cache get set logic.
 *
 * @package    core_message
 * @copyright  2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_message\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Class encapsulating all the cache get set logic.
 *
 * @copyright  2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cache_helper {

    /**
     * Stores a list of conversations in the cached, with categorical keys (types, favourites).
     *
     * @param int $userid the userid to use for the cache key
     * @param array $conversations the output of \core_message\api::get_conversations().
     */
    public static function set_user_conversation_cache(int $userid, array $conversations, int $type = null,
                                                       int $favourites = null) {
        error_log("Setting data in the cache");

        // Get the existing cache values which we'll add to.
        $cache = \cache::make('core', 'message_user_conversations');
        $categorisedconversations = [];
        if ($cacheval = $cache->get($userid)) {
            $categorisedconversations = unserialize($cacheval);
        }

        // Now, assuming we have conversations to cache, update the cacheval, overriding any old entries having the same ids.
        foreach ($conversations as $conversation) {
            if ($conversation->isfavourite) {
                $categorisedconversations['favourites'][$conversation->id] = $conversation;
                continue;
            }
            $categorisedconversations[$conversation->type][$conversation->id] = $conversation;
        }

        // If no conversations were found, update the appropriate categories where possible.
        // We do this because an empty result is still a valuable result if it means we don't hit the DB to confirm it.
        //
        // Combinations we can purge:
        // - unfiltered ($type=null, $fav=null)
        //   - purge all categories.
        // - favourite conversations of ANY type ($type=null, $fav=true)
        //   - purge 'favourites' category.
        // - non-favourite conversations of ANY type ($type=null, $fav=false)
        //   - purge all type categories (1 AND 2).
        // - type filtered conversations which are NOT favourites ($type=1/2, $fav=false).
        //   - purge specific type category (1 OR 2)
        // - type filtered conversations which MIGHT BE favourites ($type=1/2, $fav=null)
        //   - purge specific type category (1 OR 2)
        //
        // Combinations we can't purge:
        // - type filtered conversations which ARE favourites ($type=1/2, $fav=true).
        //  - We might have other conversations of type x (non favourite), and other favourites (not of type x), so don't update.
        if (empty($conversations)) {
            // No restrictions.
            if (is_null($type) && is_null($favourites)) {
                $categorisedconversations = [];
            }

            // Favourites of any type.
            if (is_null($type) && $favourites) {
                error_log('clearing favourite category from cache');
                $categorisedconversations['favourites'] = [];
            }

            // Non-favourites of any type.
            if (is_null($type) && $favourites === false) {
                $categorisedconversations[\core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL] = [];
                $categorisedconversations[\core_message\api::MESSAGE_CONVERSATION_TYPE_GROUP] = [];
            }

            // Any type, favourites either 0 or null.
            if ($type && !$favourites) {
                $categorisedconversations[$type] = [];
            }
        }

        // Finally, serialize and set.
        $cachedconversations = serialize($categorisedconversations);
        $cache->set($userid , $cachedconversations);
    }

    /**
     * @param int $userid
     * @param int $type
     * @param int $favourites
     * @return array|null
     * @throws \coding_exception
     */
    public static function get_cached_user_conversations(int $userid, int $type = null, int $favourites = null) {
        $cache = \cache::make('core', 'message_user_conversations');
        $cachekey = $userid;
        $cachedconversations = $cache->get($cachekey);
        if (is_null($cachedconversations)) {
            error_log('no conversations found, damn son!');
            return null;
        }
        $cachedconversations = unserialize($cachedconversations);

        //print_r($cachedconversations);
        // Check the cache for the data we want.
        // Always return null if the cache subkey (the conversations category) wasn't found. This indicates the cache has not yet
        // received data for this category, allowing calling code to know that it should hit the DB store.
        // This differs to the (valid) empty array return, which is an indication the cache has received data for that category,
        // but that the data is empty. I.e. it's caching the lack of conversations, which is fine.
        if (!is_null($favourites)) {

            if (!is_null($type)) {
                // We want a certain type, and NO favourites.
                if (!$favourites) {
                    error_log('Returning conversations from the cache!');
                    return $cachedconversations[$type] ?? null;
                }

                // We want a certain type, and ONLY favourites.
                if (!isset($cachedconversations['favourites'])) {
                    error_log('Cache category not found');
                    return null;
                }
                $returns = [];
                foreach ($cachedconversations['favourites'] as $conversation) {
                    if ($type == $conversation->type) {
                        $returns[] = $conversation;
                    }
                }
                error_log('Returning conversations from the cache!');
                return $returns;
            } else {
                // If we want to return NO favourites (i.e. everything else).
                if (!$favourites) {
                    $returns = [];
                    foreach ($cachedconversations as $category => $conversations) {
                        if ('favourites' == $category) {
                            continue;
                        }
                        foreach ($conversations as $conversation) {
                            $returns[] = $conversation;
                        }
                    }
                    error_log('Returning conversations from the cache!');
                    return $returns;
                }


                // Only favourites, and don't discriminate based on type.
                error_log('Returning conversations from the cache!');
                return $cachedconversations['favourites'] ?? null;
            }
        }

        // If we want to return a certain type, without favourites.
        if (!is_null($type)) {
            error_log('Returning conversations from the cache!');
            return $cachedconversations[$type] ?? null;
        }
    }
}
