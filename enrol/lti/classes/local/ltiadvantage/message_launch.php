<?php


namespace enrol_lti\local\ltiadvantage;
use enrol_lti\helper;
use IMSGlobal\LTI13\LTI_Message_Launch;

class message_launch {

    private $launchdata;
    private $contentitem;

    public function __construct(LTI_Message_Launch $launchdata, \stdClass $contentitem) {
        $this->launchdata = $launchdata;
        // TODO change this to resource and make changes across the class.
        $this->contentitem = $contentitem;
    }

    private function generate_user_from_launch_data(): \stdClass {
        global $CFG;
        $user = new \stdClass();

        // Username
        // TODO confirm whether we want to include deployment id in the uniqueness string below.
        $data = $this->launchdata->get_launch_data();
        $identifierstr = $data['aud'] . $data['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] . $data['sub'];
        $user->username = 'enrol_lti_' . sha1($identifierstr);

        // User given names, surname and email.
        $user->firstname = $data['given_name'] ?? $data['sub'];
        $user->lastname = $data['family_name'] ?? $this->contentitem->contextid;
        $user->email = $data['email'] ?? '';
        $user->email = \core_user::clean_field($user->email, 'email');

        // Now assign some other user defaults, based on the content item's User Default Values.
        // This data can be set when publishing and include things like country, timezone, maildisplay and language.
        $user->city = $this->contentitem->city ?? '';
        $user->country = $this->contentitem->country ?? '';
        $user->institution = $this->contentitem->institution ?? '';
        $user->timezone = $this->contentitem->timezone ?? '';
        if (isset($this->contentitem->maildisplay)) {
            $user->maildisplay = $this->contentitem->maildisplay;
        } else if (isset($CFG->defaultpreference_maildisplay)) {
            $user->maildisplay = $CFG->defaultpreference_maildisplay;
        } else {
            $user->maildisplay = 2;
        }
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->confirmed = 1;
        $user->lang = $this->contentitem->lang;

        return $user;
    }

    private function user_is_admin() {
        $data = $this->launchdata->get_launch_data();
        if ($data['https://purl.imsglobal.org/spec/lti/claim/roles']) {
            $roles = $data['https://purl.imsglobal.org/spec/lti/claim/roles'];
            // TODO Map these properly - check!
            //return $this->hasRole('Administrator') || $this->hasRole('urn:lti:sysrole:ims/lis/SysAdmin') ||
            //$this->hasRole('urn:lti:sysrole:ims/lis/Administrator') || $this->hasRole('urn:lti:instrole:ims/lis/Administrator');
            if (in_array('http://purl.imsglobal.org/vocab/lis/v2/institution/person#Administrator', $roles)) {
                return true;
            }
            if (in_array('http://purl.imsglobal.org/vocab/lis/v2/system/person#Administrator', $roles)) {
                return true;
            }
        }
        return false;
    }

    private function user_is_staff() {
        $data = $this->launchdata->get_launch_data();
        if ($data['https://purl.imsglobal.org/spec/lti/claim/roles']) {
            $roles = $data['https://purl.imsglobal.org/spec/lti/claim/roles'];
            // TODO Map these properly - check!
            //return ($this->hasRole('Instructor') || $this->hasRole('ContentDeveloper') || $this->hasRole('TeachingAssistant'));
            if (in_array('http://purl.imsglobal.org/vocab/lis/v2/membership#ContentDeveloper', $roles)) {
                return true;
            }
            if (in_array('http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor', $roles)) {
                return true;
            }
            if (in_array('http://purl.imsglobal.org/vocab/lis/v2/membership/Instructor#TeachingAssistant', $roles)) {
                return true;
            }
        }
        return false;
    }

    private function user_is_learner() {
        $data = $this->launchdata->get_launch_data();
        if ($data['https://purl.imsglobal.org/spec/lti/claim/roles']) {
            $roles = $data['https://purl.imsglobal.org/spec/lti/claim/roles'];
            // TODO Map these properly - check!
            //return $this->hasRole('Learner');
            if (in_array('http://purl.imsglobal.org/vocab/lis/v2/membership#Learner', $roles)) {
                return true;
            }
        }
        return false;
    }

    public function launch() {
        global $DB, $CFG, $SESSION;
        require_once($CFG->dirroot . '/user/lib.php');

        // Set the user data.
        $user = $this->generate_user_from_launch_data();

        // The user has either already been created and enrolled, or hasn't.
        if (true) {

        }

        $context = \context::instance_by_id($this->contentitem->contextid);


        if (!$dbuser = $DB->get_record('user', ['username' => $user->username, 'deleted' => 0])) {
            // If the email was stripped/not set then fill it with a default one. This
            // stops the user from being redirected to edit their profile page.
            if (empty($user->email)) {
                $user->email = $user->username .  "@example.com";
            }

            $user->auth = 'lti';
            $user->id = \user_create_user($user);

            // Get the updated user record.
            $user = $DB->get_record('user', ['id' => $user->id]);
        } else {
            if (helper::user_match($user, $dbuser)) {
                $user = $dbuser;
            } else {
                // If email is empty remove it, so we don't update the user with an empty email.
                if (empty($user->email)) {
                    unset($user->email);
                }

                $user->id = $dbuser->id;
                \user_update_user($user);

                // Get the updated user record.
                $user = $DB->get_record('user', ['id' => $user->id]);
            }
        }

        // TODO update user image.
        /*
        // Update user image.
        if (isset($this->user) && isset($this->user->image) && !empty($this->user->image)) {
            $image = $this->user->image;
        } else {
            // Use custom_user_image parameter as a fallback.
            $image = $this->resourceLink->getSetting('custom_user_image');
        }

        // Check if there is an image to process.
        if ($image) {
            helper::update_user_profile_image($user->id, $image);
        }*/

        // TODO: page layout depending on which resource is being launched.
        // Check if we need to force the page layout to embedded.
        $isforceembed = true;//$this->resourceLink->getSetting('custom_force_embed') == 1;

        // TODO role mapping.
        // Check if the user is an instructor.
        $isinstructor = $this->user_is_staff() || $this->user_is_admin();

        if ($context->contextlevel == CONTEXT_MODULE) {
            //$cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
            //$urltogo = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);

            // If we are a student in the course module context we do not want to display blocks.
            if (!$isforceembed && !$isinstructor) {
                $isforceembed = true;
            }
        }

        // Force page layout to embedded if necessary.
        if ($isforceembed) {
            $SESSION->forcepagelayout = 'embedded';
        } else {
            // May still be set from previous session, so unset it.
            unset($SESSION->forcepagelayout);
        }


        // Enrol the user in the course with no role.
        $result = helper::enrol_user($this->contentitem, $user->id);

        // Display an error, if there is one.
        if ($result !== helper::ENROLMENT_SUCCESSFUL) {
            print_error($result, 'enrol_lti');
            exit();
        }


        // Give the user the role in the given context.
        $roleid = $isinstructor ? $this->contentitem->roleinstructor : $this->contentitem->rolelearner;
        role_assign($roleid, $user->id, $this->contentitem->contextid);

        // Login user.

        // TODO Replace basic outcomes vars with AGS specific vars and update code below.
        //$sourceid = $this->user->ltiResultSourcedId;
        //$serviceurl = $this->resourceLink->getSetting('lis_outcome_service_url');

        // Check if we have recorded this user before.
        if ($userlog = $DB->get_record('enrol_lti_users', ['toolid' => $this->contentitem->id, 'userid' => $user->id])) {
            /*if ($userlog->sourceid != $sourceid) {
                $userlog->sourceid = $sourceid;
            }
            if ($userlog->serviceurl != $serviceurl) {
                $userlog->serviceurl = $serviceurl;
            }*/
            $userlog->lastaccess = time();
            $DB->update_record('enrol_lti_users', $userlog);
        } else {
            // Add the user details so we can use it later when syncing grades and members.
            $userlog = new \stdClass();
            $userlog->userid = $user->id;
            $userlog->toolid = $this->contentitem->id;
            $userlog->serviceurl = null;//$serviceurl;
            $userlog->sourceid = null;//$sourceid;
            $userlog->consumerkey = null;//$this->consumer->getKey();
            $userlog->consumersecret = null;//$tool->secret;
            $userlog->lastgrade = 0;
            $userlog->lastaccess = time();
            $userlog->timecreated = time();
            $userlog->membershipsurl = null;//$this->resourceLink->getSetting('ext_ims_lis_memberships_url');
            $userlog->membershipsid = null;//$this->resourceLink->getSetting('ext_ims_lis_memberships_id');

            $DB->insert_record('enrol_lti_users', $userlog);
        }

        // Finalise the user log in.
        complete_user_login($user);

        // Everything's good. Set appropriate OK flag and message values.
        //$this->ok = true;
        //$this->message = get_string('success');
    }
}
