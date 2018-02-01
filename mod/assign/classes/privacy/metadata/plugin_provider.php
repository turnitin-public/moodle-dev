<?php
/**
 * Created by PhpStorm.
 * User: jake
 * Date: 5/02/18
 * Time: 2:44 PM
 */

namespace mod_assign\privacy\metadata;


use core_privacy\metadata\items;
use core_privacy\request\context;
use core_privacy\request\exporter;
use core_privacy\request\resultset;

class plugin_provider implements \core_privacy\metadata\provider, \core_privacy\request\plugin_provider, \core_privacy\request\deleter {
    public static function get_metadata(): items
    {
        // TODO: Implement get_metadata() method.
    }

    public static function delete_user_data(int $userid, array $contexts): bool
    {
        // TODO: Implement delete_user_data() method.
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search
     * @return  resultset           The resultset containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): resultset
    {
        // TODO: Implement get_contexts_for_userid() method.
    }

    /**
     * Store all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param   int $userid The user to store information for
     * @param   context[] $contexts The list of contexts to store information for
     * @param   exporter $exporter The exporter plugin used to write the user data
     */
    public static function store_user_data(int $userid, array $contexts, exporter $exporter)
    {
        // TODO: Implement store_user_data() method.
    }
}