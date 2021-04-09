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
 * Contains an LTI Advantage-specific task responsible for pulling memberships from tool platforms into the tool.
 *
 * @package    enrol_lti
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_lti\local\ltiadvantage\task;
use core\task\scheduled_task;
use enrol_lti\helper;
use enrol_lti\local\ltiadvantage\entity\application_registration;
use enrol_lti\local\ltiadvantage\entity\nrps_info;
use enrol_lti\local\ltiadvantage\entity\resource_link;
use enrol_lti\local\ltiadvantage\entity\user;
use enrol_lti\local\ltiadvantage\issuer_database;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use IMSGlobal\LTI13\LTI_Names_Roles_Provisioning_Service;
use IMSGlobal\LTI13\LTI_Service_Connector;
use stdClass;

/**
 * LTI Advantage-specific task responsible for syncing memberships from tool platforms with the tool.
 *
 * This task may gather members from a context-level service call, depending on whether a resource-level service call
 * (which is made first) was successful. Because of the context-wide memberships, and because each published resource
 * has per-resource access control (role assignments), this task only enrols user into the course, and does not assign
 * roles to resource/course contexts. Role assignment only takes place during a launch, via the tool_launch_service.
 *
 * @package    enrol_lti
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_members extends scheduled_task {

    /** @var array Array of user photos. */
    protected $userphotos = [];

    /** @var resource_link_repository $resourcelinkrepo for fetching resource_link instances.*/
    protected $resourcelinkrepo;

    /** @var application_registration_repository $appregistrationrepo for fetching application_registration instances.*/
    protected $appregistrationrepo;

    /** @var deployment_repository $deploymentrepo for fetching deployment instances. */
    protected $deploymentrepo;

    /** @var user_repository $userrepo for fetching and saving lti user information.*/
    protected $userrepo;

    /** @var issuer_database $issuerdb library specific registration DB required to create service connectors.*/
    protected $issuerdb;

    /**
     * Get the name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksyncmembers', 'enrol_lti');
    }

    /**
     * Make a resource-link-level memberships call.
     *
     * @param nrps_info $nrps information about names and roles service endpoints and scopes.
     * @param LTI_Service_Connector $sc a service connector object.
     * @param resource_link $resourcelink
     * @return array|false an array of members if found, or false on service failure/non-availability.
     */
    protected function get_resource_link_level_members(nrps_info $nrps, LTI_Service_Connector $sc,
            resource_link $resourcelink) {

        // Try a resource-link-level memberships call first, falling back to context-level if no members are found.
        $reslinkmembershipsurl = $nrps->get_context_memberships_url();
        $reslinkmembershipsurl->param('rlid', $resourcelink->get_resourcelinkid());
        $servicedata = [
            'context_memberships_url' => $reslinkmembershipsurl->out(false)
        ];
        $reslinklevelnrps = new LTI_Names_Roles_Provisioning_Service($sc, $servicedata);

        mtrace('Making resource-link-level memberships request');
        return $reslinklevelnrps->get_members();
    }

    /**
     * Make a context-level memberships call.
     *
     * @param nrps_info $nrps information about names and roles service endpoints and scopes.
     * @param LTI_Service_Connector $sc a service connector object.
     * @return array|false an array of members if found, or false on service failure/non-availability.
     */
    protected function get_context_level_members(nrps_info $nrps, LTI_Service_Connector $sc) {
        $clservicedata = [
            'context_memberships_url' => $nrps->get_context_memberships_url()->out(false)
        ];
        $contextlevelnrps = new LTI_Names_Roles_Provisioning_Service($sc, $clservicedata);
        return $contextlevelnrps->get_members();
    }

    /**
     * Make the NRPS service call and fetch members based on the given resource link.
     *
     * Memberships will be retrieved by first trying the link-level memberships service first, falling back to calling
     * the context-level memberships service only if the link-level call fails.
     *
     * @param application_registration $appregistration
     * @param resource_link $resourcelink
     * @return array|false
     */
    protected function get_members_from_resource_link(application_registration $appregistration,
            resource_link $resourcelink) {

        // Get a service worker for the corresponding application registration.
        $registration = $this->issuerdb->find_registration_by_issuer($appregistration->get_platformid());
        $sc = new LTI_Service_Connector($registration);

        $nrps = $resourcelink->get_names_and_roles_service();
        $members = $this->get_resource_link_level_members($nrps, $sc, $resourcelink);
        if (!$members) {
            mtrace('Link-level memberships request failed. Making context-level memberships request');
            $members = $this->get_context_level_members($nrps, $sc);
        }

        return $members;
    }

    /**
     * Performs the synchronisation of members.
     */
    public function execute() {
        if (!is_enabled_auth('lti')) {
            mtrace('Skipping task - ' . get_string('pluginnotenabled', 'auth', get_string('pluginname', 'auth_lti')));
            return;
        }
        if (!enrol_is_enabled('lti')) {
            mtrace('Skipping task - ' . get_string('enrolisdisabled', 'enrol_lti'));
            return;
        }
        $this->resourcelinkrepo = new resource_link_repository();
        $this->appregistrationrepo = new application_registration_repository();
        $this->deploymentrepo = new deployment_repository();
        $this->userrepo = new user_repository();
        $this->issuerdb = new issuer_database();

        $resources = helper::get_lti_tools(['status' => ENROL_INSTANCE_ENABLED, 'membersync' => 1,
            'ltiversion' => 'LTI-1p3']);

        foreach ($resources as $resource) {
            mtrace("Starting - Member sync for published resource '$resource->id' for course '$resource->courseid'.");
            $usercount = 0;
            $enrolcount = 0;
            $unenrolcount = 0;
            $syncedusers = [];

            // Get all resource_links for this shared resource.
            // This is how context/resource_link memberships calls will be made.
            $resourcelinks = $this->resourcelinkrepo->find_by_resource((int)$resource->id);
            foreach ($resourcelinks as $resourcelink) {
                mtrace("Requesting names and roles for the resource link '{$resourcelink->get_id()}' for the resource" .
                    "'{$resource->id}'");

                if (!$resourcelink->get_names_and_roles_service()) {
                    mtrace("Skipping - No names and roles service found.");
                    continue;
                }

                $appregistration = $this->appregistrationrepo->find_by_deployment(
                    $resourcelink->get_deploymentid()
                );
                if (!$appregistration) {
                    mtrace("Skipping - no corresponding application registration found.");
                    continue;
                }

                $members = $this->get_members_from_resource_link($appregistration, $resourcelink);

                if ($members === false) {
                    mtrace("Skipping - Names and Roles service request failed.\n");
                    continue;
                }

                // Fetched members count.
                $membercount = count($members);
                mtrace("$membercount members received.\n");

                // Process member information.
                [$usercount, $enrolcount, $userids] = $this->sync_member_information($appregistration, $resource,
                    $resourcelink, $members);

                // Update the list of users synced for this shared resource or its context.
                $syncedusers = array_unique(array_merge($syncedusers, $userids));
            }
            // Now we check if we have to unenrol users who were not listed.
            if ($this->should_sync_unenrol($resource->membersyncmode)) {
                $unenrolcount = $this->sync_unenrol($resource, $syncedusers);
            }

            mtrace("Completed - Synced members for tool '$resource->id' in the course '$resource->courseid'. " .
                "Processed $usercount users; enrolled $enrolcount members; unenrolled $unenrolcount members.\n");
        }

        if (!empty($resources) && !empty($this->userphotos)) {
            // Sync the user profile photos.
            mtrace("Started - Syncing user profile images.");
            $countsyncedimages = $this->sync_profile_images();
            mtrace("Completed - Synced $countsyncedimages profile images.");
        }
    }

    /**
     * Method to determine whether to sync unenrolments or not.
     *
     * @param int $syncmode The shared resource's membersyncmode.
     * @return bool true if unenrolment should be synced, false if not.
     */
    protected function should_sync_unenrol($syncmode) {
        return $syncmode == helper::MEMBER_SYNC_ENROL_AND_UNENROL || $syncmode == helper::MEMBER_SYNC_UNENROL_MISSING;
    }

    /**
     * Method to determine whether to sync enrolments or not.
     *
     * @param int $syncmode The shared resource's membersyncmode.
     * @return bool true if enrolment should be synced, false if not.
     */
    protected function should_sync_enrol($syncmode) {
        return $syncmode == helper::MEMBER_SYNC_ENROL_AND_UNENROL || $syncmode == helper::MEMBER_SYNC_ENROL_NEW;
    }

    /**
     * Creates a user object from a member entry.
     *
     * @param application_registration $appregistration application_registration to which the resourcelink belongs.
     * @param stdClass $resource the locally published resource record, used for setting user defaults.
     * @param resource_link $resourcelink the resource_link instance.
     * @param array $member the member information from the NRPS service call.
     * @return user the lti user instance.
     */
    protected function user_from_member(application_registration $appregistration, stdClass $resource,
            resource_link $resourcelink, array $member): user {
        $deployment = $this->deploymentrepo->find($resourcelink->get_deploymentid());
        $deploymentidentifiers = [
            $appregistration->get_platformid(),
            $appregistration->get_clientid(),
            $deployment->get_deploymentid(),
            $member['user_id']
        ];
        $identifierstr = implode('_', $deploymentidentifiers);
        $username = 'enrol_lti_' . sha1($identifierstr);

        if ($founduser = $this->userrepo->find_by_username($username, $resource->id)) {
            $user = user::create(
                $resourcelink->get_resourceid(),
                $founduser->get_deploymentid(),
                $founduser->get_sourceid(),
                $member['given_name'] ?? $member['user_id'],
                $member['family_name'] ?? $resource->contextid,
                $founduser->get_username(),
                $founduser->get_lang(),
                $member['email'] ?? '',
                $founduser->get_city(),
                $founduser->get_country(),
                $founduser->get_institution(),
                $founduser->get_timezone(),
                $founduser->get_maildisplay(),
                $founduser->get_lastgrade(),
                null,
                $founduser->get_localid(),
                $founduser->get_id()
            );
        } else {
            // New user, so create them.
            $user = user::create(
                $resourcelink->get_resourceid(),
                $resourcelink->get_deploymentid(),
                $member['user_id'],
                $member['given_name'] ?? $member['user_id'],
                $member['family_name'] ?? $resource->contextid,
                $username,
                $resource->lang,
                $member['email'] ?? '',
                $resource->city ?? '',
                $resource->country ?? '',
                $resource->institution ?? '',
                $resource->timezone ?? '',
                $resource->maildisplay ?? null
            );
        }
        $user->set_lastaccess(time());
        return $user;
    }

    /**
     * Performs synchronisation of member information and enrolments.
     *
     * @param application_registration $appregistration the application_registration instance.
     * @param stdClass $resource the enrol_lti_tools resource information.
     * @param resource_link $resourcelink the resource_link instance.
     * @param user[] $members an array of members to sync.
     * @return array An array containing the counts of added users, enrolled users and a list of userids.
     */
    protected function sync_member_information(application_registration $appregistration, stdClass $resource,
            resource_link $resourcelink, array $members) {

        $usercount = 0;
        $enrolcount = 0;
        $userids = [];

        foreach ($members as $member) {

            $user = $this->user_from_member($appregistration, $resource, $resourcelink, $member);
            $usercount++;

            if ($this->should_sync_enrol($resource->membersyncmode)) {

                $user = $this->userrepo->save($user);
                if (isset($member['picture'])) {
                    $this->userphotos[$user->get_localid()] = $member['picture'];
                }

                // Enrol the user in the course.
                if (helper::enrol_user($resource, $user->get_localid()) === helper::ENROLMENT_SUCCESSFUL) {
                    $enrolcount++;
                }
            }

            // If the member has been created, or exists locally already, mark them as valid so as to not unenrol them
            // when syncing memberships for shared resources configured as either MEMBER_SYNC_ENROL_AND_UNENROL or
            // MEMBER_SYNC_UNENROL_MISSING.
            if ($user->get_localid()) {
                $userids[] = $user->get_localid();
            }
        }

        return [$usercount, $enrolcount, $userids];
    }

    /**
     * Unenrols users for a published resource, based on their absence in last link-level or context-level sync.
     *
     * @param stdClass $resource The published resource record object.
     * @param array $userids list of ids of users considered valid based on the current sync request.
     * @return int The number of users that have been unenrolled.
     */
    protected function sync_unenrol(stdClass $resource, array $userids) {
        global $DB;

        $ltiplugin = enrol_get_plugin('lti');

        if (!$this->should_sync_unenrol($resource->membersyncmode)) {
            return 0;
        }

        $unenrolcount = 0;
        $ltiusersrs = $DB->get_recordset('enrol_lti_users', array('toolid' => $resource->id), 'lastaccess DESC',
            'userid');
        // Go through the users and check if any weren't present in the latest sync, removing if so.
        foreach ($ltiusersrs as $ltiuser) {
            if (!in_array($ltiuser->userid, $userids)) {
                $instance = new stdClass();
                $instance->id = $resource->enrolid;
                $instance->courseid = $resource->courseid;
                $instance->enrol = 'lti';
                $ltiplugin->unenrol_user($instance, $ltiuser->userid);
                $unenrolcount++;
            }
        }
        $ltiusersrs->close();

        return $unenrolcount;
    }

    /**
     * Performs synchronisation of user profile images.
     */
    protected function sync_profile_images() {
        $counter = 0;
        foreach ($this->userphotos as $userid => $url) {
            if ($url) {
                $result = helper::update_user_profile_image($userid, $url);
                if ($result === helper::PROFILE_IMAGE_UPDATE_SUCCESSFUL) {
                    $counter++;
                    mtrace("Profile image successfully downloaded and created for user '$userid' from $url.");
                } else {
                    mtrace($result);
                }
            }
        }
        return $counter;
    }
}
