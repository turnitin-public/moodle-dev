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
 * Class containing the external API functions functions for the LTI Creator tool.
 *
 * @package    tool_lti_creator
 * @copyright  2020 Jake Dallimore
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lti_creator;
defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/externallib.php");

/**
 * Class external.
 *
 * The external API for the LTI Creator tool.
 *
 * @copyright  2020 Jake Dallimore
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends \external_api {

    /**
     * Parameter description for get_tool_instance().
     *
     * @since Moodle 4.0
     * @return external_function_parameters
     */
    public static function get_tool_instance_parameters() {
        return new \external_function_parameters([
            'modulename' => new \external_value(PARAM_TEXT, 'The name of the module to return', VALUE_REQUIRED)
        ]);
    }

    /**
     * @param string $modname
     */
    public static function get_tool_instance(string $modname) {
        global $DB, $CFG;

        $manager = new api();
        $course = $manager->get_course();

        // TODO: Create the activity instance.
        $module = $manager->create_assignment_activity($course->id, 2);
        $modulecontext = $module->gradingman->get_context();
        // TODO: Create the lti_enrol instance, using the activity created above.
        $instanceid = $manager->create_lti_enrolment_instance($course, $modulecontext);

        $record = $DB->get_record('enrol_lti_tools', ['enrolid' => $instanceid]);

        // TODO: return url, secret. I've noted that consumerkey could be generated and used, but that's out of scope right now.
        $url = new \moodle_url('enrol/lti/tool.php', ['id' => $record->id]);
        return [
            'url' => $CFG->wwwroot . '/' . $url->out(false),
            'secret' => $record->secret
        ];
    }

    /**
     * Returns description for get_tool_instance().
     *
     * @since Moodle 4.0
     * @return external_description
     */
    public static function get_tool_instance_returns() {
        return new \external_single_structure([
            'url' => new \external_value(PARAM_URL, 'The launch URL of the tool'),
            'secret' => new \external_value(PARAM_TEXT, 'The shared secret, needed to consume the created tool'),
        ]);
    }
}
