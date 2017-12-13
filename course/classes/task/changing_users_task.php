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
 * Task confirming stale admin cache when switching users.
 *
 * @package    core_course
 * @copyright  2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_course\task;

defined('MOODLE_INTERNAL') || die();
/**
 * Task confirming stale admin cache when switching users.
 *
 * @package core_course
 * @copyright 2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class changing_users_task extends \core\task\scheduled_task {

    public function get_name() {
        return 'changing users task';
    }

    protected function section_debug($section) {
        if (empty($section)) {
            echo "No debugging node found." . PHP_EOL . PHP_EOL;
        } else {
            echo "Debugging node was found." . PHP_EOL . PHP_EOL;
        }
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot. '/course/lib.php');
        require_once($CFG->dirroot. '/lib/adminlib.php');

        echo "\n\n\n======== ADMIN ROOT CACHE TEST ======== \n\n\n";

        // Manager at site level.
        $user = \core_user::get_user(16, '*', MUST_EXIST);
        cron_setup_user($user);
        $adminroot = admin_get_root();
        $section = $adminroot->locate('debugging');
        echo "We're not expecting the debugging node for a manager at site context\n";
        $this->section_debug($section);

        // Now, change user to admin user.
        $user2 = \core_user::get_user(2, '*', MUST_EXIST);
        cron_setup_user($user2);
        $adminroot = admin_get_root();
        $section = $adminroot->locate('debugging');
        echo "After changing to an admin user though, we do expect to see the debugging node. Enter the caching problem..\n";
        $this->section_debug($section);

        // Once more, with a forced reloading.
        $adminroot = admin_get_root(true);
        $section = $adminroot->locate('debugging');
        echo "But now via a forcereload, still as the admin user, we can see the node:\n";
        $this->section_debug($section);
    }
}
