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
 * Contains the class encapsulating all the cache get/set logic.
 *
 * @package    core_message
 * @copyright  2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_message\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Class encapsulating all the cache get/set logic.
 *
 * @copyright  2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversation_cache {

    /** @var int CACHED_RESULTS_LIMIT the max number of items which can be stored per cache key.*/
    const CACHED_RESULTS_LIMIT = 51;

    /** @var cache the instance of the conversation cache*/
    var $cache;

    /**
     * conversation_cache constructor.
     */
    public function __construct() {
        $this->cache = \cache::make('core', 'message_user_conversations');
    }

    /**
     * Parse the search params into the relevant category key, if suitable.
     *
     * Only 3 'categories' are supported by this class - 'favourites', 'individual' and 'group'.
     * Each of these categories corresponds to a specific set of params only.
     * Other combinations will not be supported by the cache.
     *
     * @param int $userid the userid param used when generating the list of conversations.
     * @param int|null $type the type param used when generating the list of conversations.
     * @param bool|null $favourites the favourites param used when generating the list of conversations.
     * @return string the string key, or an empty string if the parameter combination is unsupported by the cache.
     */
    protected function get_key_from_params(int $userid, $type, $favourites) : string {
        // Favourites.
        if (is_null($type) && $favourites == true) {
            return $userid . '_favourites';
        }

        // Individual.
        if ($type == \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL && $favourites == false) {
            return $userid . '_individual';
        }

        // Group.
        if ($type == \core_message\api::MESSAGE_CONVERSATION_TYPE_GROUP && $favourites == false) {
            return $userid . '_group';
        }
        return '';
    }

    /**
     * Returns all possible conversations cache keys for a given user.
     *
     * @param int $userid the user id.
     * @return array the array of string keys.
     */
    protected function get_all_cache_keys_for_user(int $userid) : array {
        return [
            $userid . '_favourites',
            $userid . '_individual',
            $userid . '_group'
        ];
    }

    /**
     * Validates the metadata relating to a given list of conversations, returning true if it can be cached and false otherwise.
     *
     * We use this helper, because only certain types of conversation lists are cached currently:
     * - Lists containing favourites of ANY kind ($type=null, $favourites=true)
     * - Lists containing individual conversations, none of which are favourites ($type=IND, $favourites=false)
     * - Lists containing group conversations, none of which are favourites ($type=GRP, $favourites=false).
     *
     * These correspond to those lists of conversations commonly requested by the front end.
     *
     * Whilst it's possible to generate a list of conversations which is say, 'individual conversations which have been favourited',
     * these are not cached at present.
     *
     * @param int $type
     * @param bool $favourites
     * @param int $limitfrom
     * @param int $limitnum
     * @return bool true if the combination is supported by the cache, false otherwise.
     */
    protected function conversations_params_valid($type, $favourites, int $limitfrom, int $limitnum) {
        // Paging - only the first page is cached here, which covers most use cases in the application.
        if ($limitfrom != 0 || $limitnum > self::CACHED_RESULTS_LIMIT) {
            return false;
        }

        // Favourites.
        if (is_null($type) && $favourites == true) {
            return true;
        }

        // Individual.
        if ($type == \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL && $favourites == false) {
            return true;
        }

        // Group.
        if ($type == \core_message\api::MESSAGE_CONVERSATION_TYPE_GROUP && $favourites == false) {
            return true;
        }

        return false;
    }

    /**
     * Add the conversation to the list of conversations cached against destinationcachekey.
     *
     * This will splice the value into the correct location, based on the id of the recent message, with higher ids occupying lower
     * indexes in the array.
     *
     * @param \stdClass $conversation must be a prior-cached conversation structure, NOT a conversation record.
     * @param string $cachekey the key corresponding to the conversation category for a specific user.
     */
    protected function cache_insert_conversation_for_key(\stdClass $conversation, string $cachekey) : void {
        if (($destinationconversations = unserialize($this->cache->get($cachekey))) === false) {
            error_log("cache_insert_conversation_for_key: couldn't find cached values, returning");
            return;
        }
        // Insert the conversation into the appropriate spot - ordering based on the id of the recent message.
        $insertindex = count($destinationconversations);
        foreach ($destinationconversations as $destinationindex => $destinationconversation) {
            if ($conversation->messages[0]->id > $destinationconversation->messages[0]->id) {
                $insertindex = $destinationindex;
                break;
            }
        }
        error_log("cache_insert_conversation_for_key: inserting conversation into $cachekey at index: $insertindex");
        array_splice($destinationconversations, $insertindex, 0, [$conversation]);
        $this->cache->set($cachekey, serialize($destinationconversations));
    }

    /**
     * Returns the item matched on id from the list of conversations cached at index $categorykey.
     *
     * This is a helper used to grab a conversations, by id, from a known category cache, and can optionally remove the item too.
     *
     * @param string $categorykey the key representing the category to search within.
     * @param int $id the id of the item.
     * @param bool $delete whether or not the item should also be removed from the list.
     * @return \stdClass the item.
     * @throws \moodle_exception if the cache contains no values or doesn't contain an item identified by the specified id.
     */
    protected function category_cache_get_by_id(string $categorykey, int $id, bool $delete = false) : \stdClass{
        if (($values = unserialize($this->cache->get($categorykey))) === false) {
            error_log("cache_values_contain_id: no cached values found for key: $categorykey");
            throw new \moodle_exception('no cached values found for the specified key');
        }
        if (($index = array_search($id, array_column($values, 'id'))) === false) {
            throw new \moodle_exception('item not found in cached values');
        }
        $item = $values[$index];

        if ($delete) {
            unset($values[$index]);
            $values = array_values($values);
            $this->cache->set($categorykey, serialize($values));
        }

        return $item;
    }

    /**
     * Update the recent message in a cached conversation for a single user.
     *
     * @param int $userid the user whose cache is to be updated.
     * @param int $conversationid the conversation we're looking to update the recent message for.
     * @param \stdClass $message the message which will become the recent message.
     */
    public function set_conversation_recent_message(int $userid, int $conversationid, \stdClass $message) {
        // Search through all categories of cached conversations.
        foreach ($this->get_all_cache_keys_for_user($userid) as $key) {
            // Get the cached conversations, if any, and unserialise.
            $categoryconversations = [];
            if ($cacheval = $this->cache->get($key)) {
                $categoryconversations = unserialize($cacheval);
                error_log('send_message: fetched cached values for user:'.$userid.', category: '.$key);
            }

            // Find the relevant conversation, and replace the recent message.
            foreach ($categoryconversations as $index => $conversation) {
                if ($conversation->id == $conversationid) {
                    // Conversation was found, so replace the recent message.
                    $msg = new \stdClass();
                    $msg->id = $message->id;
                    $msg->text = message_format_message_text($message);
                    $msg->useridfrom = $message->useridfrom;
                    $msg->timecreated = $message->timecreated;
                    $categoryconversations[$index]->messages[0] = $msg;

                    // Depending on the type of the conversation, we may also need to update the member information.
                    // Only group conversations need this member information to be updated.
                    // Individual conversations always return the 'other' member, which doesn't change per message.
                    if ($categoryconversations[$index]->type == \core_message\api::MESSAGE_CONVERSATION_TYPE_GROUP) {
                        $memberidfrom = $message->useridfrom;

                        // Temporary solution to update member info  - until we can fetch this from a cache.
                        // Called just like we do in get_conversations()
                        $memberinfo = \core_message\helper::get_member_info($userid, [$memberidfrom]);
                        $categoryconversations[$index]->members[0] = $memberinfo[$memberidfrom];

                        // TODO: add cache for conversation members.
                        // Once it does, we can get the member info directly from there rather than hitting the DB.
                    }

                    // Update the cache.
                    $this->cache->set($key, serialize($categoryconversations));
                    break;
                }
            }
        }
    }

    /**
     * Stores a list of conversations in the cache, with categorical keys (types, favourites).
     *
     * @param int $userid the userid to use for the cache key
     * @param array $conversations a list of conversations, format matching the output of \core_message\api::get_conversations().
     * @param int $type the type of conversations in the list, null if no type restriction was used.
     * @param bool $favourites true if list contains only favourites, false if no favourites, or null if it includes a mix.
     */
    public function set_user_conversation_cache(int $userid, array $conversations, int $type = null,
                                                       bool $favourites = null, int $limitfrom, int $limitnum) {

        // Only certain data is cached.
        if (!$this->conversations_params_valid($type, $favourites, $limitfrom, $limitnum)) {
            error_log('set: cache does not support that parameter combination.');
            return;
        }

        // Get the existing cache values which we'll add to.
        $key = $this->get_key_from_params($userid, $type, $favourites);
        error_log("setting cache data for key '$key'.");
        $this->cache->set($key, serialize($conversations));
        return;
    }

    /**
     * Gets a user's cached conversations.
     *
     * @param int $userid The id of the user whose conversations we want.
     * @param int $type
     * @param int $favourites
     * @param int $limitfrom
     * @param int $limitnum
     * @return array|null
     */
    public function get_cached_user_conversations(int $userid, int $type = null, int $favourites = null, int $limitfrom,
            int $limitnum) {

        // Validate the params used to determine the category of conversation. Only certain categories are cached.
        if (!$this->conversations_params_valid($type, $favourites, $limitfrom, $limitnum)) {
            error_log('get: cache does not support that parameter combination.');
            return;
        }

        $cachekey = $this->get_key_from_params($userid, $type, $favourites);
        if (($cachedconversations = $this->cache->get($cachekey)) === false) {
            error_log('get: no cached conversations found');
            return;
        }
        error_log("get: returning conversations from the cache for key: '$cachekey'");

        return unserialize($cachedconversations);
    }

    /**
     * Updates the cache when a conversation is marked as a favourite by a user.
     *
     * This will remove the conversation from its respective category cache (individual, group), if present, and place it in the
     * favourite category, if present.
     *
     * @param \stdClass $conversation a full conversation record.
     * @param int $userid the id of the user whose cache we wish to update.
     */
    public function conversation_favourited_by_user(\stdClass $conversation, int $userid) {

        $originkey = $this->get_key_from_params($userid, $conversation->type, false);
        $destinationkey = $this->get_key_from_params($userid, null, true);

        try {
            // Get the conversation from the origin list, removing it in the process.
            $cachedconversation = $this->category_cache_get_by_id($originkey, $conversation->id, true);

            // Update the favourite status and store in the destination key (the favourites category).
            $cachedconversation->isfavourite = true;
            $this->cache_insert_conversation_for_key($cachedconversation, $destinationkey);
        } catch (\moodle_exception $m) {
            // No conversation was found in the origin cache, meaning both that key, and now the x_favourites keys are stale.
            // We must delete them and force a reload of the data on next request.
            $this->cache->delete_many([$originkey, $destinationkey]);
            return;
        }
    }

    /**
     * Updates the cache when a conversation is unset as a favourite by a user.
     *
     * This will remove the conversation from the favourite category cache, if present, and place it in the appropriate type
     * (individual, favourite) category cache, if present.
     *
     * @param \stdClass $conversation a full conversation record.
     * @param int $userid the id of the user whose cache we wish to update.
     */
    public function conversation_unfavourited_by_user(\stdClass $conversation, int $userid) {

        $originkey = $this->get_key_from_params($userid, null, true);
        $destinationkey = $this->get_key_from_params($userid, $conversation->type, false);

        try {
            // Get the conversation from the origin list, removing it in the process.
            $cachedconversation = $this->category_cache_get_by_id($originkey, $conversation->id, true);

            // Update the favourite status and store in the destination key (the type-specific category).
            $cachedconversation->isfavourite = false;
            $this->cache_insert_conversation_for_key($cachedconversation, $destinationkey);
        } catch (\moodle_exception $m) {
            // No conversation was found in the origin cache, meaning both that key, and now the x_type keys are stale.
            // We must delete them and force a reload of the data on next request.
            $this->cache->delete_many([$originkey, $destinationkey]);
            return;
        }
    }
}
