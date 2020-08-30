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
 * Contains the admin_setting_toolendpoints class for rendering a table of tool endpoints.
 *
 * This setting is useful for LTI 1.3 only.
 *
 * @package    enrol_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti;
use core\output\notification;

defined('MOODLE_INTERNAL') || die;

/**
 * Class for rendering a table of tool endpoints.
 *
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_toolendpoints extends \admin_setting {
    /**
     * Calls parent::__construct with specific arguments
     */
    public function __construct() {
        $this->nosave = true;
        // TODO: fix hard coded string below.
        parent::__construct('enrol_lti_tool_endpoints', "Tool endpoints string", '', '');
    }

    /**
     * Always returns true, does nothing.
     *
     * @return bool true.
     */
    public function get_setting() {
        return true;
    }

    /**
     * Always returns true, does nothing.
     *
     * @return bool true.
     */
    public function get_defaultsetting() {
        return true;
    }

    /**
     * Always returns '', does not write anything.
     *
     * @return string Always returns ''.
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Checks if $query is one of the available external services
     *
     * @param string $query The string to search for
     * @return bool Returns true if found, false if not
     */
    public function is_related($query) {
        if (parent::is_related($query)) {
            return true;
        }

        // TODO: just grab all the strings used in the render and see if the search term matches any of them.
        //  remove this hack.
        $string = 'tool url oidc initiate login url jwks url';

        return strpos($string, $query) !== false;
    }

    /**
     * Builds the HTML to display the table.
     *
     * @param string $data Unused
     * @param string $query
     * @return string
     */
    public function output_html($data, $query='') {
        global $PAGE;

        // TODO: fetch details properly.

        $tooldetails = [
            'urls' => [
                [
                    'name' => 'Tool URL',
                    'url' => 'http://example.com/example.php',
                    'id' => uniqid()
                ],
                [
                    'name' => 'OIDC Initiate Login URL',
                    'url' => 'http://example.com/example.php',
                    'id' => uniqid()
                ],
                [
                    'name' => 'JWKS URL',
                    'url' => 'http://example.com/example.php',
                    'id' => uniqid()
                ],
                [
                    'name' => 'Redirection URL',
                    'url' => 'http://example.com/example.php',
                    'id' => uniqid()
                ],
                [
                    'name' => 'Deep Linking URL',
                    'url' => 'http://example.com/example.php',
                    'id' => uniqid()
                ],
            ],
        ];

        $renderer = $PAGE->get_renderer('core');
        $versionwarning = new notification(
            'The tool endpoints below are for LTI 1.3 setup only. For earlier versions, details for consumers can be found on the \'Published as LTI tools\' page in the course administration.',
            notification::NOTIFY_INFO
        );
        $versionwarning->set_show_closebutton(false);
        $return = $renderer->render($versionwarning);
        $return .= $renderer->render_from_template('enrol_lti/tool_endpoints', $tooldetails);

        return highlight($query, $return);
    }
}
