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
use core_privacy\metadata\item_collection;
use core_privacy\phpunit\approved_contextlist;
use core_privacy\request\contextlist_collection;

/**
 * Class manager.
 * Provides the mechanisms to get and delete personal information across Moodle.
 * @package core_privacy
 */
class manager {
    /**
     * Checks whether the given component is compliant with the core_privacy API.
     * To be considered compliant, a component must declare whether (and where) it stores personal data.
     *
     * Components which do store personal data must:
     * - Have implemented the core_privacy\metadata\provider interface (to describe the data it stores) and;
     * - Have implemented the core_privacy\request\data_provider interface (to facilitate export of personal data)
     * - Have implemented the core_privacy\request\deleter interface
     *
     * Components which do not store personal data must:
     * - Have implemented the core_privacy\metadata\null_provider interface to signal that they don't store personal data.
     *
     * @param string $component frankenstyle component name, e.g. 'mod_assign'
     * @return bool true if the component is compliant, false otherwise.
     * @throws \coding_exception if the component name is invalid.
     */
    public static function component_is_compliant(string $component) : bool {
        // Components which don't store user data need only implement the null_provider.
        if (self::component_implements_interface($component, 'core_privacy\metadata\null_provider')) {
            return true;
        }
        // Components which store user data must implement the metadata\provider and the request\data_provider.
        if (self::component_stores_user_data($component) && self::component_supports_export($component)) {
            return true;
        }
        return false;
    }

    /**
     * Check whether the component supports data export by implementing a data provider.
     *
     * @param string $component the frankenstyle component name.
     * @return bool true if the component supports data retrieval, false otherwise.
     */
    public static function component_supports_export(string $component) : bool {
        // TODO: Consider refining this to check core providers further - we can check core_data_provider for these.
        return self::component_implements_interface($component, 'core_privacy\request\data_provider');
    }

    /**
     * Check whether the component reports to store user data, based on an implementation of the metadata\provider interface.
     *
     * @param string $component the frankenstyle component name.
     * @return bool true if the component implements a metadata provider, false otherwise.
     * @throws \coding_exception if the component name is invalid.
     */
    public static function component_stores_user_data(string $component) : bool {
        return self::component_implements_interface($component, 'core_privacy\metadata\provider');
    }

    /**
     * Get the privacy metadata for all components or for a subset of components.
     *
     * @param string[] $components an optional array of frankenstyle component names. If provided, only those components will be
     * checked. If not provided, all components will be checked.
     * @return item_collection[] The array of item_collection objects, indexed by frankenstyle component name.
     * @throws \coding_exception if an invalid component name is provided.
     */
    public static function get_metadata_for_components(array $components = []) : array {
        $metadata = [];
        // If $components is empty, get the metadata for all plugins and core subsystems.
        if (empty($components)) {
            // Get all plugins.
            $plugintypes = \core_component::get_plugin_types();
            foreach ($plugintypes as $plugintype => $typedir) {
                $plugins = \core_component::get_plugin_list($plugintype);
                foreach ($plugins as $pluginname => $plugindir) {
                    $components[] = $plugintype . '_' . $pluginname;
                }
            }
            // Get all subsystems.
            foreach (\core_component::get_core_subsystems() as $name => $path) {
                // TODO: MDL-61463 - check whether subsystems without paths need to be considered.
                if (isset($path)) {
                    $components[] = 'core_' . $name;
                }
            }
        } else {
            // Else, provide only for those specified components, but clean up names.
            $cleancomponents = [];
            foreach ($components as $component) {
                $cleancomponent = clean_param($component, PARAM_COMPONENT);
                if (empty($cleancomponent)) {
                    throw new \coding_exception('Invalid component used in core_privacy\manager::get_metadata_for_components():'
                        . $component);
                }
                $cleancomponents[] = $cleancomponent;
            }
            $components = $cleancomponents;
        }

        // Get the metadata, and put into an assoc array indexed by component name.
        foreach ($components as $component) {
            if (self::component_stores_user_data($component)) {
                echo "Getting metadata for $component\n";
                $class = $component . '\privacy\provider';
                $itemcollection = new item_collection($component);
                $metadata[$component] = $class::get_metadata($itemcollection);
            }
        }
        return $metadata;
    }

    /**
     * Gets a collection of resultset objects for all components.
     *
     * @param int $userid the id of the user we're fetching contexts for.
     * @return contextlist_collection the collection of resultsets for the respective components.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist_collection {
        // We want core_provider subsystems and plugins only.
        $components = array_merge(self::get_core_provider_subsystems(), self::get_core_provider_plugins());
        $clcollection = new contextlist_collection();
        foreach ($components as $component) {
            if (self::component_stores_user_data($component) && self::component_supports_export($component)) {
                $class = $component . '\privacy\provider';
                $clcollection->add_contextlist($component, $class::get_contexts_for_userid($userid));
            }
        }
        return $clcollection;
    }

    /**
     * Export all user data for the specified user, for the specified contexts.
     *
     * @param contextlist_collection $clcollection
     */
    public static function store_user_data(contextlist_collection $clcollection) {
        // We want core_provider subsystems and plugins only.
        $components = array_merge(self::get_core_provider_subsystems(), self::get_core_provider_plugins());
        foreach ($components as $component) {
            if (self::component_stores_user_data($component) && self::component_supports_export($component)) {
                $class = $component . '\privacy\provider';
                $class::store_user_data($clcollection->get_contextlists()[$component]);
            }
        }
    }

    // TODO: Implementation pending.
    public static function delete_user_data(int $userid, array $contexts) {
        return true;
    }

    /**
     * Returns an array of those plugins which are core providers.
     *
     * @return string[] An array of frankenstyle plugin names. E.g. mod_assign.
     */
    protected static function get_core_provider_plugins() : array {
        $cpplugins = [];
        $plugintypes = \core_component::get_plugin_types();
        foreach ($plugintypes as $plugintype => $typedir) {
            $plugins = \core_component::get_plugin_list($plugintype);
            foreach ($plugins as $pluginname => $plugindir) {
                $name = $plugintype . '_' . $pluginname;
                if (self::component_implements_interface($name, 'core_privacy\request\core_user_data_provider')) {
                    $cpplugins[] = $name;
                }
            }
        }
        return $cpplugins;
    }

    /**
     * Returns an array of those subsystems which are core providers.
     *
     * @return string[] An array of frankenstyle subsystem names. E.g. core_grading.
     */
    protected static function get_core_provider_subsystems() : array {
        $cpsubsystems = [];
        $subsystems = \core_component::get_core_subsystems();
        foreach ($subsystems as $name => $path) {
            // TODO: MDL-61463 - check whether subsystems without paths need to be considered.
            if (!empty($path)) {
                $name = 'core_' . $name;
                if (self::component_implements_interface($name, 'core_privacy\request\core_user_data_provider')) {
                    $cpsubsystems[] = $name;
                }
            }
        }
        return $cpsubsystems;
    }

    /**
     * Checks whether the component's provider class implements the specified interface.
     * This can either be implemented directly, or by implementing a descendant (extension) of the specified interface.
     *
     * @param string $component the frankenstyle component name.
     * @param string $interface the name of the interface we want to check.
     * @return bool True if an implementation was found, false otherwise.
     */
    protected static function component_implements_interface(string $component, string $interface) : bool {
        $implementations = self::get_component_implementations($component);
        if (isset($implementations[$interface])) {
            // Only return true if the provider class has implemented the interface, ignoring other classes.
            return in_array($component . '\privacy\provider', $implementations[$interface]);
        }
        return false;
    }

    /**
     * Returns a list of privacy interfaces which the given component implements, and which classes implement them.
     *
     * This method only checks a KNOWN LIST of privacy classnames and so will not scan all classes in the privacy namespace for a
     * component. The results can be used to classify a component, such as determining whether it stores user data, or whether it
     * implements all the required interfaces to be considered compliant.
     *
     * @param string $component the frankenstyle component name.
     * @return string[] an assoc array where keys are fq interfaces, and values are an array of classes implementing them.
     * E.g. 'core_privacy\request\data_provider' => ['core_grading\privacy\provider','core_comment\privacy\provider'].
     * @throws \coding_exception if the component name is invalid.
     */
    protected static function get_component_implementations(string $component) : array {
        $cleancomponent = clean_param($component, PARAM_COMPONENT);
        if (empty($cleancomponent)) {
            throw new \coding_exception('Invalid component used in core_privacy\manager::get_component_implementations():' . $component);
        }
        $component = $cleancomponent;

        // Check the known privacy classes and store which interfaces they implement.
        $privacyclasses = [$component . '\privacy\provider'];
        $implementations = []; // Stores the implemented interface (key) and implementing class name (array of vals).
        foreach ($privacyclasses as $index => $classname) {
            if (class_exists($classname)) {
                foreach (class_implements($classname) as $interface) {
                    $implementations[$interface][] = $classname;
                }
            }
        }
        return $implementations;
    }
}
