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

namespace enrol_lti\local\ltiadvantage\lib;

use enrol_lti\local\ltiadvantage\entity\application_registration;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use Packback\Lti1p3\LtiDeployment;
use Packback\Lti1p3\LtiRegistration;

/**
 * Tests for the issuer_database class.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \enrol_lti\local\ltiadvantage\lib\issuer_database
 */
class issuer_database_test extends \advanced_testcase {

    /**
     * Test the Moodle implementation of the library database method test_find_registration_by_issuer().
     *
     * @covers ::findRegistrationByIssuer
     */
    public function test_find_registration_by_issuer() {
        $this->resetAfterTest();
        $appregrepo = new application_registration_repository();

        // Create a registration for an issuer that supports multiple clients via client_id.
        $appreg = application_registration::create(
            'My platform',
            new \moodle_url('https://lms.example.com'),
            'client-id-123',
            new \moodle_url('https://lms.example.com/lti/auth'),
            new \moodle_url('https://lms.example.com/lti/jwks'),
            new \moodle_url('https://lms.example.com/lti/token')
        );
        $appregrepo->save($appreg);

        // Create a registration for an issuer that doesn't support multiple clients, i.e. has no client_id support.
        $appreg2 = application_registration::create(
            'Another platform',
            new \moodle_url('https://lms2.example.com'),
            '',
            new \moodle_url('https://lms2.example.com/lti/auth'),
            new \moodle_url('https://lms2.example.com/lti/jwks'),
            new \moodle_url('https://lms2.example.com/lti/token')
        );
        $appregrepo->save($appreg2);

        $issuerdb = new issuer_database($appregrepo, new deployment_repository());

        // Verify we can find the registration including client_id.
        $registration = $issuerdb->findRegistrationByIssuer('https://lms.example.com', 'client-id-123');
        $this->assertInstanceOf(LtiRegistration::class, $registration);
        $this->assertEquals($appreg->get_authenticationrequesturl()->out(false), $registration->getAuthLoginUrl());
        $this->assertEquals($appreg->get_jwksurl()->out(false), $registration->getKeySetUrl());
        $this->assertEquals($appreg->get_accesstokenurl()->out(false), $registration->getAuthTokenUrl());
        $this->assertEquals($appreg->get_clientid(), $registration->getClientId());
        $this->assertEquals($appreg->get_platformid()->out(false), $registration->getIssuer());

        // Verify we can find the registration NOT including client_id.
        $registration = $issuerdb->findRegistrationByIssuer('https://lms2.example.com');
        $this->assertInstanceOf(LtiRegistration::class, $registration);
        $this->assertEquals($appreg2->get_authenticationrequesturl()->out(false), $registration->getAuthLoginUrl());
        $this->assertEquals($appreg2->get_jwksurl()->out(false), $registration->getKeySetUrl());
        $this->assertEquals($appreg2->get_accesstokenurl()->out(false), $registration->getAuthTokenUrl());
        $this->assertEquals($appreg2->get_clientid(), $registration->getClientId());
        $this->assertEquals($appreg2->get_platformid()->out(false), $registration->getIssuer());

        // Try to find the first registration using a different client_id, verifying it can't be found.
        $this->assertNull($issuerdb->findRegistrationByIssuer('https://lms.example.com', 'client-id-456'));

        // Try to find a registration using a non-existent issuer.
        $this->assertNull($issuerdb->findRegistrationByIssuer('https://not-found.example.com'));
    }

    /**
     * Test the Moodle implementation of the library database method test_find_deployment().
     *
     * @covers ::findDeployment
     */
    public function test_find_deployment() {
        $this->resetAfterTest();
        $appregrepo = new application_registration_repository();

        // Create a registration for an issuer that supports multiple clients via client_id.
        $appreg = application_registration::create(
            'My platform',
            new \moodle_url('https://lms.example.com'),
            'client-id-123',
            new \moodle_url('https://lms.example.com/lti/auth'),
            new \moodle_url('https://lms.example.com/lti/jwks'),
            new \moodle_url('https://lms.example.com/lti/token')
        );
        $appreg = $appregrepo->save($appreg);

        // Create a registration for an issuer that doesn't support multiple clients, i.e. has no client_id support.
        $appreg2 = application_registration::create(
            'Another platform',
            new \moodle_url('https://lms2.example.com'),
            '',
            new \moodle_url('https://lms2.example.com/lti/auth'),
            new \moodle_url('https://lms2.example.com/lti/jwks'),
            new \moodle_url('https://lms2.example.com/lti/token')
        );
        $appreg2 = $appregrepo->save($appreg2);

        // Add deployments to both registrations.
        $deploymentrepo = new deployment_repository();
        $dep = $appreg->add_tool_deployment('Site wide tool deployment', 'deployment-id-1');
        $deploymentrepo->save($dep);
        $dep2 = $appreg2->add_tool_deployment('Site wide tool deployment', 'deployment-id-2');
        $deploymentrepo->save($dep2);

        $issuerdb = new issuer_database($appregrepo, new deployment_repository());

        // Find the deployment for the first registration.
        $deployment = $issuerdb->findDeployment('https://lms.example.com', 'deployment-id-1', 'client-id-123');
        $this->assertInstanceOf(LtiDeployment::class, $deployment);
        $this->assertEquals($dep->get_deploymentid(), $deployment->getDeploymentId());

        // Find the deployment for the second registration, without using client_id.
        $deployment2 = $issuerdb->findDeployment('https://lms2.example.com', 'deployment-id-2');
        $this->assertInstanceOf(LtiDeployment::class, $deployment2);
        $this->assertEquals($dep2->get_deploymentid(), $deployment2->getDeploymentId());

        // Try to find the deployment for the first registration using an invalid client_id.
        $this->assertNull($issuerdb->findDeployment('https://lms.example.com', 'deployment-id-1', 'client-id-456'));

        // Try to find the deployment for the first registration using an invalid deploymentid.
        $this->assertNull($issuerdb->findDeployment('https://lms.example.com', 'deployment-id-2', 'client-id-123'));

        // Try to find a deployment for a non-existent issuer.
        $this->assertNull($issuerdb->findDeployment('https://not-found.example.com', 'deployment-id-2'));
    }
}
