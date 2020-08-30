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
 * Contains the admin_setting_registeredplatforms class for rendering a table of platforms which have been registered.
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
 * Class for rendering a table of registered platforms.
 *
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_registeredplatforms extends \admin_setting {
    /**
     * Calls parent::__construct with specific arguments
     */
    public function __construct() {
        $this->nosave = true;
        // TODO: fix hard coded string below.
        parent::__construct('enrol_lti_tool_registered_platforms', "Tool registered platforms string", '', '');
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
        return false;
    }

    /**
     * Builds the HTML to display the table.
     *
     * @param string $data Unused
     * @param string $query
     * @return string
     */
    public function output_html($data, $query='') {
        global $PAGE, $DB;

        // TODO: fetch registrations properly.
        $registrations = [
            'registrations' => [],
            'addurl' => (new \moodle_url('/enrol/lti/register_platform.php', ['action' => 'add']))->out(false),
        ];

        foreach ($DB->get_records('enrol_lti_platform_registry') as $reg) {
            $registrations['registrations'][] = [
                'name' => $reg->platformid,
                'id' => $reg->id,
                'editurl' => (new \moodle_url('/enrol/lti/register_platform.php',
                    ['action' => 'edit', 'regid' => $reg->id]))->out(false),
                'deleteurl' => (new \moodle_url('/enrol/lti/register_platform.php',
                    ['action' => 'delete', 'regid' => $reg->id]))->out(false)
            ];
        }

        $renderer = $PAGE->get_renderer('core');
        $versionwarning = new notification(
            'The platforms listed below are registered for LTI 1.3 communication. For earlier versions, consumer registration is not required.',
            notification::NOTIFY_INFO
        );
        $versionwarning->set_show_closebutton(false);
        $return = $renderer->render($versionwarning);
        $return .= $renderer->render_from_template('enrol_lti/registered_platforms', $registrations);

        return highlight($query, $return);
    }
}
