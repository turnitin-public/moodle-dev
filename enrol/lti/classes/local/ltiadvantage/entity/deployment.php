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
 * Contains the deployment class.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\local\ltiadvantage\entity;

/**
 * Class deployment.
 *
 * This class represents an LTI Advantage Tool Deployment (http://www.imsglobal.org/spec/lti/v1p3/#tool-deployment).
 *
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deployment {
    /** @var int|null the id of this object instance, or null if it has not been saved yet. */
    private $id;

    /** @var string the name of this deployment. */
    private $deploymentname;

    /** @var string The platform-issued deployment id. */
    private $deploymentid;

    /** @var int the local ID of the application registration to which this deployment belongs. */
    private $registrationid;

    /**
     * The private deployment constructor.
     *
     * @param string $deploymentname the name of this deployment.
     * @param string $deploymentid the platform-issued deployment id.
     * @param int $registrationid the local ID of the application registration.
     * @param int|null $id the id of this object instance, or null if it is a new instance which has not yet been saved.
     */
    private function __construct(string $deploymentname, string $deploymentid, int $registrationid, int $id = null) {
        $this->deploymentname = $deploymentname;
        $this->deploymentid = $deploymentid;
        $this->registrationid = $registrationid;
        $this->id = $id;
    }

    /**
     * Factory method to create a new instance of a deployment.
     *
     * @param int $registrationid the local ID of the application registration.
     * @param string $deploymentid the platform-issued deployment id.
     * @param string $deploymentname the name of this deployment.
     * @return deployment the deployment instance.
     */
    public static function create(int $registrationid, string $deploymentid, string $deploymentname) {
        return new self($deploymentname, $deploymentid, $registrationid);
    }

    /**
     * Factory method to create a deployment instance from the store.
     *
     * @param int $id the object instance id.
     * @param string $deploymentname the name of this deployment.
     * @param string $deploymentid the platform-issued deployment id.
     * @param int $registrationid the local ID of the application registration.
     * @return deployment the deployment instance.
     */
    public static function create_from_store(int $id, string $deploymentname, string $deploymentid,
            int $registrationid) {

        return new self($deploymentname, $deploymentid, $registrationid, $id);
    }

    /**
     * Return the object id.
     *
     * @return int|null the id.
     */
    public function get_id(): ?int {
        return $this->id;
    }

    /**
     * Return the short name of this tool deployment.
     *
     * @return string the short name.
     */
    public function get_deploymentname(): string {
        return $this->deploymentname;
    }

    /**
     * Get the deployment id string.
     *
     * @return string deploymentid
     */
    public function get_deploymentid(): string {
        return $this->deploymentid;
    }

    /**
     * Get the id of the application_registration.
     *
     * @return int the id of the application_registration instance to which this deployment belongs.
     */
    public function get_registrationid(): int {
        return $this->registrationid;
    }

    /**
     * Factory method to add a platform-specific context to the deployment.
     *
     * @param string $contextid the contextid, as supplied by the platform during launch.
     * @param array $types the context types the context represents, as supplied by the platform during launch.
     * @return context the context instance.
     * @throws \coding_exception if the context could not be created.
     */
    public function add_context(string $contextid, array $types): context {
        if (!$this->get_id()) {
            throw new \coding_exception('Can\'t add context to a deployment that hasn\'t first been saved');
        }

        return context::create($this->get_id(), $contextid, $types);
    }

    /**
     * Factory method to create a resource link from this deployment instance.
     *
     * @param string $resourcelinkid the platform-issued string id of the resource link.
     * @param int $resourceid the local published resource to which this link points.
     * @param string|null $contextid the platform context in which the resource link resides, if available.
     * @return resource_link the resource_link instance.
     * @throws \coding_exception if the resource_link can't be created.
     */
    public function add_resource_link(string $resourcelinkid, int $resourceid,
            string $contextid = null): resource_link {

        if (!$this->get_id()) {
            throw new \coding_exception('Can\'t add resource_link to a deployment that hasn\'t first been saved');
        }
        return resource_link::create($resourcelinkid, $this->get_id(), $resourceid, $contextid);
    }
}
