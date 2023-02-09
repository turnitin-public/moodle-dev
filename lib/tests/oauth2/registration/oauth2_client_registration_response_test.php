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

namespace core\oauth2\registration;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Unit tests for {@see oauth2_client_registration_response}.
 *
 * @coversDefaultClass oauth2_client_registration_response
 * @package core
 * @copyright 2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth2_client_registration_response_test extends \advanced_testcase {
    /**
     * Testing the factory method returns a properly configured instance.
     *
     * @covers ::from_response
     * @dataProvider from_response_provider
     * @param ResponseInterface $response the mocked response data from the authorisation server.
     * @param array $expected the test case expectations
     */
    public function test_from_response(ResponseInterface $response, array $expected = []) {
        if (!empty($expected['exception'])) {
            $this->expectException($expected['exception']);
        }
        $regresponse = oauth2_client_registration_response::from_response($response);

        $this->assertInstanceOf(oauth2_client_registration_response::class, $regresponse);
        $this->assertEquals($expected['successful'], $regresponse->is_successful());

        if ($expected['successful']) {
            $this->assertInstanceOf(oauth2_client_registration_information_response::class, $regresponse);
        } else {
            $this->assertInstanceOf(oauth2_client_registration_error_response::class, $regresponse);
        }
    }

    /**
     * Provides test data for testing from_response().
     *
     * @return array test data.
     */
    public function from_response_provider(): array {
        return [
            'Successful registration response' => [
                'response' => new Response(201, ['Content-type' => 'application/json'], '{"client_id": "abc123"}'),
                'expected' => [
                    'successful' => true,
                ]
            ],
            'Registration error response' => [
                'response' => new Response(400, ['Content-type' => 'application/json'],
                    '{"error": "invalid_client_metadata", "error_description": "The grant type \'authorization_code\' must be '.
                    'registered along with the response type \'code\' but found only \'implicit\' instead."}'),
                'expected' => [
                    'successful' => false,
                ]
            ],
            'Improperly formatted response body' => [
                'response' => new Response(201, ['Content-type' => 'application/json'], '{"client_id": "abc"'), // Missing '}'.
                'expected' => [
                    'exception' => \moodle_exception::class
                ]
            ],
        ];
    }
}
