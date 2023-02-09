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
 * Unit tests for {@see oauth2_client_registration_error_response}.
 *
 * @coversDefaultClass oauth2_client_registration_error_response
 * @package core
 * @copyright 2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth2_client_registration_error_response_test extends \advanced_testcase {
    /**
     * Test the factory method.
     *
     * @covers ::from_response
     * @dataProvider array_provider
     * @param ResponseInterface $response the mocked response data from the authorisation server.
     * @param array $expected the test expectations.
     */
    public function test_from_response(ResponseInterface $response, array $expected = []): void {
        if (!empty($expected['exception'])) {
            $this->expectException($expected['exception']);
        }
        $clienterrorresponse = oauth2_client_registration_error_response::from_response($response);
        $this->assertInstanceOf(oauth2_client_registration_error_response::class, $clienterrorresponse);

        $this->assertFalse($clienterrorresponse->is_successful());
        $this->assertArrayHasKey('error', $clienterrorresponse->get_error_info());
    }

    /**
     * Provides test data for the from_response() method.
     *
     * @return array test data.
     */
    public function array_provider(): array {
        return [
            'Valid, status code ok, response ok' => [
                'response' => new Response(400, ['Content-type' => 'application/json'], '{"error": "invalid_redirect_uri"}'),
                'expected' => [
                    'successful' => false,
                ]
            ],
            'Valid, status code as specified by the auth server, response ok' => [
                'response' => new Response(401, ['Content-type' => 'application/json'], '{"error": "invalid_redirect_uri"}'),
                'expected' => [
                    'successful' => false,
                ]
            ],
            'Invalid, status code invalid for an error, response ok' => [
                'response' => new Response(201, ['Content-type' => 'application/json'], '{"error": "invalid_redirect_uri"}'),
                'expected' => [
                    'exception' => \moodle_exception::class
                ]
            ],
            'Invalid, status code valid, improperly formatted error JSON' => [
                'response' => new Response(400, ['Content-type' => 'application/json'], '{"error": "invalid_redir'), // Bad JSON.
                'expected' => [
                    'exception' => \moodle_exception::class
                ]
            ],
        ];
    }

    /**
     * Test the to_response() method.
     *
     * @covers ::to_response
     * @dataProvider to_response_provider
     * @param array $params the constructor params for creating the client info instance.
     * @param array $expected the test expectations.
     * @return void
     */
    public function test_to_response(array $params, array $expected = []): void {
        $regresponse = new oauth2_client_registration_error_response(...array_values($params));
        $response = $regresponse->to_response();
        $this->assertEquals($expected['response']->getStatusCode(), $response->getStatusCode());
        $this->assertEquals($expected['response']->getHeaders(), $response->getHeaders());
        $this->assertEquals(
            json_decode($expected['response']->getBody()->getContents(), true),
            json_decode($response->getBody()->getContents(), true)
        );
    }

    /**
     * Provider for testing the to_response() method.
     *
     * @return array test data.
     */
    public function to_response_provider(): array {
        return [
            'Valid, error information provided' => [
                'params' => [
                    'errorinfo' => [
                        'error' => 'invalid_redirect_uri',
                        'error_description' => 'The URI https://lms.example.org/admin/oauth2callback.php is not allowed.'
                    ],
                    'status_code' => 400,
                ],
                'expected' => [
                    'response' => new Response(
                        400,
                        ['Cache-control' => 'no-store', 'Pragma' => 'no-cache', 'Content-type' => 'application/json'],
                        json_encode([
                            'error' => 'invalid_redirect_uri',
                            'error_description' => 'The URI https://lms.example.org/admin/oauth2callback.php is not allowed.'
                        ])
                    )
                ]
            ],
            'Valid, status code not 400' => [
                'params' => [
                    'errorinfo' => [
                        'error' => 'invalid_redirect_uri',
                        'error_description' => 'The URI https://lms.example.org/admin/oauth2callback.php is not allowed.'
                    ],
                    'status_code' => 405,
                ],
                'expected' => [
                    'response' => new Response(
                        405,
                        ['Cache-control' => 'no-store', 'Pragma' => 'no-cache', 'Content-type' => 'application/json'],
                        json_encode([
                            'error' => 'invalid_redirect_uri',
                            'error_description' => 'The URI https://lms.example.org/admin/oauth2callback.php is not allowed.'
                        ])
                    )
                ]
            ]
        ];
    }
}
