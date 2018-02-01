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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the core_privacy\manager class.
 *
 * @package core_privacy
 * @copyright 2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_privacy;

/**
 * Class manager.
 * Provides the mechanisms to get and delete personal information across Moodle.
 * @package core_privacy
 */
final class manager {
    /**
     * Checks whether the given component is compliant with the core_privacy API.
     * To be considered compliant, a component must declare whether (and where) it stores personal data.
     *
     * Plugins that do store personal data must:
     * - Have implemented the core_privacy\metadata\provider interface (to describe the data it stores) and;
     * - Have implemented the core_privacy\request\plugin_provider interface (to facilitate export of personal data)
     * - Have implemented the core_privacy\request\plugin_deleter interface
     *
     * Plugins that do not store personal data must:
     * - Have implemented the core_privacy\metadata\null_provider interface to signal that they don't store personal data.
     *
     * @param string $component frankenstyle component name, e.g. 'mod_assign'
     * @return bool true if the component is compliant, false otherwise.
     * @throws \coding_exception if the component name is invalid.
     */
    // TODO: Maybe called compatibility.
    public static function component_is_compliant($component) {
        $cleancomponent = clean_param($component, PARAM_COMPONENT);
        if (empty($cleancomponent)) {
            throw new \coding_exception('Invalid component used in core_privacy\manager::component_is_compliant():' . $component);
        }
        $component = $cleancomponent;

        // Inspect all privacy classes for the component and build up a list of interfaces implemented by each.
        $implementations = []; // Stores what interface has been implemented (key) and by which classes (array of vals).
        $privacyclasses = \core_component::get_component_classes_in_namespace($component, 'privacy');
        foreach ($privacyclasses as $classname => $classpath) {
            foreach (class_implements($classname) as $interface) {
                $implementations[$interface][] = $classname;
            }
        }

        // Does not store personal information.
        if (isset($implementations['core_privacy\metadata\null_provider'])) {
            echo "Stores no data, is compliant\n";
            return true;
        }

        // Stores personal information and provides the implementations needed to return and delete it.
        if (isset($implementations['core_privacy\metadata\provider']) &&
            isset($implementations['core_privacy\request\data_provider']) &&
            isset($implementations['core_privacy\request\deleter'])) {
            echo "Stores data and is compliant\n";
            return true;
        }
        return false;
    }

    /**
     * Get the privacy metadata for all components or for a subset of components.
     * @param array $components
     * @return array
     */
    public static function get_metadata_for_components(array $components = []) {
        // TODO: finalise the metadata format - Zig's working on the Items class (a collection of items internally stored).

        // If $components is empty, get the metadata for:
        // - All plugins (sub plugins are included here, despite being called by their parent in get_contexts_by_userid().
        // - Some select core subsystems (TODO: make these SS implement to denote this)

        if (empty($components)) {
            // Get all components (subsystems + plugins).
            // TODO: why isn't this already a function somewhere?
            $plugintypes = \core_component::get_plugin_types();
            foreach ($plugintypes as $plugintype => $typedir) {
                $plugins = \core_component::get_plugin_list($plugintype);
                foreach ($plugins as $pluginname => $plugindir) {
                    $components[$plugintype . '_' . $pluginname] = $plugindir;
                }
            }
        }
        print_r($components);

        // Else, provide only for those specified.
        foreach ($components as $component) {

        }

        return [];
    }


    protected static function get_core_usage_subsystems() {
        $subsystems = core_component::get_core_subsystems();
        foreach ($subsystems as $name => $path) {
            // Check for the core_ss\usage class and see what it implements
            $implementations = []; // Stores what interface has been implemented (key) and by which classes (array of vals).


            $privacyclasses = \core_component::get_component_classes_in_namespace($component, 'privacy');
            foreach ($privacyclasses as $classname => $classpath) {
            }

        }
        return [];
    }

    public static function get_contexts_for_userid(int $userid) {
        // We want all components, less:
        // - those subsystems which are managed directly by plugins.
        // - those plugins which are sub plugins and therefore managed by other plugins.

        //TODO: Subsystem list audit and inclusion.
        $subsystems = ['badges' => 'badges'];



        // Ask primary plugins for all their contexts containing user information for the user.
        // In turn, they should ask their subplugins and summarise that info.
        $primaryplugins = \core_plugin_manager::instance()->get_primary_plugins();
        $frankennames = [];
        foreach ($primaryplugins as $plugintype => $pluginsarray) {
            foreach ($pluginsarray as $pluginname => $plugininfo) {
                $frankennames[$plugintype . '_' . $pluginname] = $plugintype . '_' . $pluginname;
            }
        }

        print_r($frankennames);
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param int $userid
     * @param array $contexts
     * @param request\exporter $exporter
     */
    public static function export_user_data(int $userid, array $contexts, request\exporter $exporter) {
        $primaryplugins = \core_plugin_manager::instance()->get_primary_plugins();

    }


    public static function delete_user_data(int $userid, array $contexts) {
        return;
    }
}
