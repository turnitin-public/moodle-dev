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
 * Contains the import_info class.
 *
 * @package tool_moodlenet
 * @copyright 2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_moodlenet\local;

/**
 * Class import_info, describing objects which represent a resource being imported by a user.
 *
 * Objects of this class encapsulate both:
 * - information about the resource (remote_resource).
 * - config data pertaining to the import process, such as the destination course and section
 *   and how the resource should be treated (i.e. the type and the name of the module selected as the import handler)
 *
 * @copyright 2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_info {

    /** @var int $userid the user conducting this import. */
    protected $userid;

    /** @var remote_resource $resource the resource being imported. */
    protected $resource;

    /** @var \stdClass $config config data pertaining to the import process, e.g. course, section, type. */
    protected $config;

    /**
     * The import_controller constructor.
     *
     * @param int $userid the id of the user performing the import.
     * @param remote_resource $resourceurl the resource being imported.
     * @param \stdClass $importconfig import config like 'course', 'section', 'type'.
     */
    public function __construct(int $userid, remote_resource $resource, \stdClass $config) {
        $this->userid = $userid;
        $this->resource = $resource;
        $this->config = $config;
    }

    /**
     * Get the remote resource being imported.
     *
     * @return remote_resource the remote resource being imported.
     */
    public function get_resource(): remote_resource {
        return $this->resource;
    }

    /**
     * Get the configuration data pertaining to the import.
     *
     * @return \stdClass the import configuration data.
     */
    public function get_config(): \stdClass {
        return $this->config;
    }

    /**
     * Set the configuration data pertaining to the import.
     *
     * @param \stdClass $config the configuration data to set.
     */
    public function set_config(\stdClass $config): void {
        $this->config  = $config;
    }

    /**
     * Load import info, if present;
     *
     * @return mixed
     */
    public static function load(): ?import_info {
        // This currently lives in the session, so we don't need userid.
        // It might be useful if we ever move to another storage mechanism however, where we would need it.
        global $SESSION;
        return isset($SESSION->moodlenetimport) ? unserialize($SESSION->moodlenetimport) : null;
    }

    /**
     * Save this object to a store which is accessible across requests.
     */
    public function save(): void {
        global $SESSION;
        $SESSION->moodlenetimport = serialize($this);
    }

    /**
     * Remove all information about an import from the store.
     */
    public function purge(): void {
        global $SESSION;
        unset($SESSION->moodlenetimport);
    }
}
