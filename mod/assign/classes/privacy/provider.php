<?php
/**
 * Created by PhpStorm.
 * User: jake
 * Date: 8/02/18
 * Time: 4:50 PM
 */

namespace mod_assign\privacy;

class provider implements \core_privacy\local\metadata\null_provider{
    /**
     * Get the language string identifier with the component's language
     * file to explain why this plugin stores no data.
     *
     * @return  String
     */
    public static function get_reason(): String
    {
        return 'a reason here';
    }
}
