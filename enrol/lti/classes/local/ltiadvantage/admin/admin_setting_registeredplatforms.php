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
namespace enrol_lti\local\ltiadvantage\admin;
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
        parent::__construct('enrol_lti_tool_registered_platforms', get_string('registeredplatforms', 'enrol_lti'), '',
            '');
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
        global $DB;
        if (parent::is_related($query)) {
            return true;
        }

        $regs = $DB->get_records('enrol_lti_platform_registry');
        foreach ($regs as $reg) {
            if (strpos($reg->name, $query) !== false) {
                return true;
            }
        }
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

        $registrations = [
            'registrations' => [],
            'addurl' => (new \moodle_url('/enrol/lti/register_platform.php', ['action' => 'add']))->out(false),
        ];
        $regs = $DB->get_records('enrol_lti_platform_registry');
        $registrations['hasregs'] = count($regs) > 0;

        foreach ($regs as $reg) {
            $registrations['registrations'][] = [
                'name' => get_string('registeredplatformname', 'enrol_lti',
                    (object)['name' => $reg->name, 'platformid' => $reg->platformid]),
                'id' => $reg->id,
                'editurl' => (new \moodle_url('/enrol/lti/register_platform.php',
                    ['action' => 'edit', 'regid' => $reg->id]))->out(false),
                'deleteurl' => (new \moodle_url('/enrol/lti/register_platform.php',
                    ['action' => 'delete', 'regid' => $reg->id]))->out(false)
            ];
        }

        $renderer = $PAGE->get_renderer('core');
        $versionnotice = new notification(
            get_string('registeredplatformsltiversionnotice', 'enrol_lti'),
            notification::NOTIFY_INFO
        );
        $versionnotice->set_show_closebutton(false);
        $return = $renderer->render($versionnotice);
        $return .= $renderer->render_from_template('enrol_lti/registered_platforms', $registrations);

        return highlight($query, $return);
    }
}
