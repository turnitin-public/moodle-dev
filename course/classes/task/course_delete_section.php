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
 * Adhoc task handling course section deletion.
 *
 * @package    core_course
 * @copyright  2016 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_course\task;

/**
 * Class handling course section deletion.
 *
 * @package core_course
 * @copyright 2016 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_delete_section extends \core\task\adhoc_task {

    /**
     * Run the deletion task.
     *
     * @throws \coding_exception If the section cannot be removed.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot. '/course/lib.php');

        $data = $this->get_custom_data();
        $section = $data->section;
        $forcedeleteifnotempty = $data->forcedeleteifnotempty;
        if (!course_delete_section_now($section, $forcedeleteifnotempty)) {
            throw new \coding_exception("The section {$section->section} could not be removed. This is a bug.");
        }
    }
}
