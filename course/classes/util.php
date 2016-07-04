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
 * Provides a util class for core_course.
 *
 * @package    core_course
 * @copyright  2016 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_course;

/**
 * Static helper class providing course-related functions.
 *
 * @since 3.2.0
 * @package    core_course
 * @copyright  2016 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class util {

    /**
     * This function will handle the whole deletion process of a module. This includes calling
     * the modules delete_instance function, deleting files, events, grades, conditional data,
     * the data in the course_module and course_sections table and adding a module deletion
     * event to the DB.
     *
     * @param int $cmid the course module id.
     * @return bool whether the module was successfully deleted.
     * @throws \moodle_exception
     */
    private static function course_delete_module_sync($cmid) {
        global $CFG, $DB;

        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->libdir.'/questionlib.php');
        require_once($CFG->dirroot.'/blog/lib.php');
        require_once($CFG->dirroot.'/calendar/lib.php');

        // Get the course module.
        if (!$cm = $DB->get_record('course_modules', array('id' => $cmid))) {
            return true;
        }

        // Get the module context.
        $modcontext = \context_module::instance($cm->id);

        // Get the course module name.
        $modulename = $DB->get_field('modules', 'name', array('id' => $cm->module), MUST_EXIST);

        // Get the file location of the delete_instance function for this module.
        $modlib = "$CFG->dirroot/mod/$modulename/lib.php";

        // Include the file required to call the delete_instance function for this module.
        if (file_exists($modlib)) {
            require_once($modlib);
        } else {
            throw new \moodle_exception('cannotdeletemodulemissinglib', '', '', null,
                "Cannot delete this module as the file mod/$modulename/lib.php is missing.");
        }

        $deleteinstancefunction = $modulename . '_delete_instance';

        // Ensure the delete_instance function exists for this module.
        if (!function_exists($deleteinstancefunction)) {
            throw new \moodle_exception('cannotdeletemodulemissingfunc', '', '', null,
                "Cannot delete this module as the function {$modulename}_delete_instance is missing in mod/$modulename/lib.php.");
        }

        // Allow plugins to use this course module before we completely delete it.
        if ($pluginsfunction = get_plugins_with_function('pre_course_module_delete')) {
            foreach ($pluginsfunction as $plugintype => $plugins) {
                foreach ($plugins as $pluginfunction) {
                    $pluginfunction($cm);
                }
            }
        }

        // Delete activity context questions and question categories.
        question_delete_activity($cm);

        // Call the delete_instance function, if it returns false throw an exception.
        if (!$deleteinstancefunction($cm->instance)) {
            throw new \moodle_exception('cannotdeletemoduleinstance', '', '', null,
                "Cannot delete the module $modulename (instance).");
        }

        // Remove all module files in case modules forget to do that.
        $fs = get_file_storage();
        $fs->delete_area_files($modcontext->id);

        // Delete events from calendar.
        if ($events = $DB->get_records('event', array('instance' => $cm->instance, 'modulename' => $modulename))) {
            foreach ($events as $event) {
                $calendarevent = \calendar_event::load($event->id);
                $calendarevent->delete();
            }
        }

        // Delete grade items, outcome items and grades attached to modules.
        if ($gradeitems = \grade_item::fetch_all(array('itemtype' => 'mod', 'itemmodule' => $modulename,
            'iteminstance' => $cm->instance, 'courseid' => $cm->course))) {
            foreach ($gradeitems as $gradeitem) {
                $gradeitem->delete('moddelete');
            }
        }

        // Delete completion and availability data; it is better to do this even if the
        // features are not turned on, in case they were turned on previously (these will be
        // very quick on an empty table).
        $DB->delete_records('course_modules_completion', array('coursemoduleid' => $cm->id));
        $DB->delete_records('course_completion_criteria', array('moduleinstance' => $cm->id,
            'course' => $cm->course,
            'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY));

        // Delete all tag instances associated with the instance of this module.
        \core_tag_tag::delete_instances('mod_' . $modulename, null, $modcontext->id);
        \core_tag_tag::remove_all_item_tags('core', 'course_modules', $cm->id);

        // Notify the competency subsystem.
        \core_competency\api::hook_course_module_deleted($cm);

        // Delete the context.
        \context_helper::delete_instance(CONTEXT_MODULE, $cm->id);

        // Delete the module from the course_modules table.
        $DB->delete_records('course_modules', array('id' => $cm->id));

        // Delete module from that section.
        if (!delete_mod_from_section($cm->id, $cm->section)) {
            throw new \moodle_exception('cannotdeletemodulefromsection', '', '', null,
                "Cannot delete the module $modulename (instance) from section.");
        }

        // Trigger event for course module delete action.
        $event = \core\event\course_module_deleted::create(array(
            'courseid' => $cm->course,
            'context'  => $modcontext,
            'objectid' => $cm->id,
            'other'    => array(
                'modulename' => $modulename,
                'instanceid'   => $cm->instance,
            )
        ));
        $event->add_record_snapshot('course_modules', $cm);
        $event->trigger();
        rebuild_course_cache($cm->course, true);
    }

    /**
     * Schedule a course module for deletion in the background using an adhoc task.
     * The final deletion of the module should be handled by the task, and by calling 'course_delete_module($cmid, false);
     *
     * @param int $cmid the course module id.
     * @return bool whether the module was successfully scheduled for deletion.
     * @throws \moodle_exception
     */
    private static function course_delete_module_async($cmid) {
        global $CFG, $DB;
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->libdir.'/questionlib.php');
        require_once($CFG->dirroot.'/blog/lib.php');
        require_once($CFG->dirroot.'/calendar/lib.php');

        // Get the course module.
        if (!$cm = $DB->get_record('course_modules', array('id' => $cmid))) {
            return true;
        }

        // We need to be reasonably certain the deletion is going to succeed before we background the process.
        // Make the necessary delete_instance checks, etc. before proceeding further. Throw exceptions if required.

        // Get the course module name.
        $modulename = $DB->get_field('modules', 'name', array('id' => $cm->module), MUST_EXIST);

        // Get the file location of the delete_instance function for this module.
        $modlib = "$CFG->dirroot/mod/$modulename/lib.php";

        // Include the file required to call the delete_instance function for this module.
        if (file_exists($modlib)) {
            require_once($modlib);
        } else {
            throw new \moodle_exception('cannotdeletemodulemissinglib', '', '', null,
                "Cannot delete this module as the file mod/$modulename/lib.php is missing.");
        }

        $deleteinstancefunction = $modulename . '_delete_instance';

        // Ensure the delete_instance function exists for this module.
        if (!function_exists($deleteinstancefunction)) {
            throw new \moodle_exception('cannotdeletemodulemissingfunc', '', '', null,
                "Cannot delete this module as the function {$modulename}_delete_instance is missing in mod/$modulename/lib.php.");
        }

        // We are going to defer the deletion as we can't be sure how long the module's pre_delete code will run for.
        $cm->deletioninprogress = '1';
        $DB->update_record('course_modules', $cm);

        // Create an adhoc task for the deletion of the course module. The task takes an array of course modules for removal.
        $removaltask = new \core_course\task\course_delete_modules();
        $removaltask->set_custom_data(array('cms' => array($cm)));

        // Queue the task for the next run.
        \core\task\manager::queue_adhoc_task($removaltask);

        // Reset the course cache to hide the module.
        rebuild_course_cache($cm->course, true);
    }

    /**
     * Delete a course section and all of its modules.
     *
     * @param \stdClass $section the section to delete.
     * @param bool $forcedeleteifnotempty whether to force section deletion if it contains modules.
     * @return bool true if the section was deleted, false otherwise.
     */
    private static function course_delete_section_sync($section, $forcedeleteifnotempty = true) {
        global $DB;

        // Objects only, and only valid ones.
        if (!is_object($section) || empty($section->id)) {
            return false;
        }

        // Does the object currently exist in the DB for removal (check for stale objects).
        $section = $DB->get_record('course_sections', array('id' => $section->id));
        if (!$section || !$section->section) {
            // No section exists, or the section is 0. Can't proceed.
            return false;
        }

        // Check whether the section can be removed.
        if (!$forcedeleteifnotempty && (!empty($section->sequence) || !empty($section->summary))) {
            return false;
        }

        $format = course_get_format($section->course);
        $sectionname = $format->get_section_name($section);

        // Delete section.
        $result = $format->delete_section($section, $forcedeleteifnotempty);

        // Trigger an event for course section deletion.
        if ($result) {
            $context = \context_course::instance($section->course);
            $event = \core\event\course_section_deleted::create(
                array(
                    'objectid' => $section->id,
                    'courseid' => $section->course,
                    'context' => $context,
                    'other' => array(
                        'sectionnum' => $section->section,
                        'sectionname' => $sectionname,
                    )
                )
            );
            $event->add_record_snapshot('course_sections', $section);
            $event->trigger();
        }
        return $result;
    }

    /**
     * Schedules a course section and all of its modules for deletion in the background using an adhoc task.
     * The final deletion of the section should be handled by the task, by calling 'course_delete_section($course, $section, false).
     *
     * @param \stdClass $section the section to schedule for deletion.
     * @param bool $forcedeleteifnotempty whether to force section deletion if it contains modules.
     * @return bool true if the section was scheduled for deletion, false otherwise.
     */
    private static function course_delete_section_async($section, $forcedeleteifnotempty = true) {
        global $DB;

        // Objects only, and only valid ones.
        if (!is_object($section) || empty($section->id)) {
            return false;
        }

        // Does the object currently exist in the DB for removal (check for stale objects).
        $section = $DB->get_record('course_sections', array('id' => $section->id));
        if (!$section || !$section->section) {
            // No section exists, or the section is 0. Can't proceed.
            return false;
        }

        // Check whether the section can be removed.
        if (!$forcedeleteifnotempty && (!empty($section->sequence) || !empty($section->summary))) {
            return false;
        }

        // Get format and course object, which we'll need below.
        $format = course_get_format($section->course);
        $course = $format->get_course(); // This is required by 'move_section_to', below.

        // Move the section to the end of the other sections and update the ordering.
        // The section will be hidden by 'uservisible' and 'available' properties of the course_section object
        // after the course cache is rebuilt.
        $lastsection = $DB->get_field_sql('SELECT max(section) from {course_sections}
                            WHERE course = ?', array($course->id));

        // Before we move the section, mark all the course_modules as 'deletioninprogress' for consistency in course_modinfo.
        $DB->set_field('course_modules', 'deletioninprogress', '1', array('course' => $section->course, 'section' => $section->id));

        // Move the section.
        move_section_to($course, $section->section, $lastsection, true);

        // Flag the section for removal.
        $DB->set_field('course_sections', 'deletioninprogress', '1', array('id' => $section->id));

        // Decrease the 'numsections' format option if required.
        $courseformathasnumsections = array_key_exists('numsections', $format->get_format_options());
        $decreasenumsections = $courseformathasnumsections && ($section->section <= $course->numsections);
        if ($decreasenumsections) {
            $format->update_course_format_options(array('numsections' => $course->numsections - 1));
        }

        // Create an adhoc task for the deletion of the course section.
        $removaltask = new \core_course\task\course_delete_section();
        $data = array(
            'section' => $section,
            'forcedeleteifnotempty' => $forcedeleteifnotempty
        );
        $removaltask->set_custom_data($data);

        // Queue the task for the next run.
        \core\task\manager::queue_adhoc_task($removaltask);

        // Reset the course cache to hide the section.
        rebuild_course_cache($section->course, true);

        return true;
    }

    /**
     * Delete a course section or schedule for background deletion, depending on the async flag.
     *
     * @param \stdClass $section the course section.
     * @param bool $forcedeleteifnotempty whether to force section deletion if it contains modules.
     * @param bool $async whether or not to delete the module using an adhoc task.
     * @return bool true if the course_section was deleted/scheduled for deletion, false otherwise.
     */
    public static function course_delete_section(\stdClass $section, $forcedeleteifnotempty = true, $async = true) {
        if ($async === true) {
            return self::course_delete_section_async($section, $forcedeleteifnotempty);
        }
        return self::course_delete_section_sync($section, $forcedeleteifnotempty);
    }

    /**
     * Delete a course module or schedule for background deletion, depending on the async flag.
     *
     * @param int $cmid the course module id.
     * @param bool $async whether or not to delete the module using an adhoc task.
     */
    public static function course_delete_module($cmid, $async = true) {
        if ($async === true) {
            self::course_delete_module_async($cmid);
        } else {
            self::course_delete_module_sync($cmid);
        }
    }
}
