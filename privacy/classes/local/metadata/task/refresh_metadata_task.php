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
 * This file defines an adhoc task to fetch new privacy metadata from components.
 *
 * @package    core_privacy
 * @copyright  2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_privacy\local\metadata\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Fetches metadata for all components and advertises this to plugins implementing the relevant hook.
 *
 * @copyright  2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_metadata_task extends \core\task\adhoc_task {

    protected function class_implements(string $classname, string $interface) {
        if (class_exists($classname)) {
            $rc = new \ReflectionClass($classname);
            return $rc->implementsInterface($interface);
        }
        return false;
    }

    /**
     * Get the current metadata and broadcast to anyone needing to be informed of changes.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        $privacymanager = new \core_privacy\manager();
        $itemcollections = null; // Only get the metadata when it's first required.

        foreach (\core_component::get_plugin_types() as $plugintype => $typedir) {
            foreach (\core_component::get_plugin_list($plugintype) as $pluginname => $plugindir) {
                $plugin = $plugintype . '_' . $pluginname;
                $classname = $plugin . '\privacy\listener';
                if ($this->class_implements($classname, \core_privacy\local\metadata\listener::class)) {
                    $metadata = is_null($itemcollections) ? $privacymanager->get_metadata_for_components() : null;
                    component_class_callback($classname, 'process_metadata', [$metadata]);
                }
            }
        }
    }
}
