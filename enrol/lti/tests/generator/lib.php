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

use enrol_lti\local\ltiadvantage\entity\application_registration;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;

/**
 * LTI Enrolment test data generator class.
 *
 * @package enrol_lti
 * @category test
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_lti_generator extends component_generator_base {

    /**
     * Test method to generate an application registration (and optionally a deployment) for a platform.
     *
     * @param array $data the application registration data, with optional deployment data.
     * @return application_registration
     */
    public function create_application_registration(array $data): application_registration {
        $registration = application_registration::create(
            $data['name'],
            $data['platformid'],
            $data['clientid'],
            new moodle_url($data['authrequesturl']),
            new moodle_url($data['jwksurl']),
            new moodle_url($data['accesstokenurl'])
        );

        $appregrepo = new application_registration_repository();
        $createdregistration = $appregrepo->save($registration);

        if (isset($data['deploymentname']) && isset($data['deploymentid'])) {
            $deployment = $createdregistration->add_tool_deployment($data['deploymentname'], $data['deploymentid']);
            $deploymentrepo = new deployment_repository();
            $deploymentrepo->save($deployment);
        }

        return $createdregistration;
    }
}
