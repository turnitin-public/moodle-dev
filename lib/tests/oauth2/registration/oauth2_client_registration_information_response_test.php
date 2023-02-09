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
 * Unit tests for {@see oauth2_client_registration_information_response}.
 *
 * @coversDefaultClass oauth2_client_registration_information_response
 * @package core
 * @copyright 2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth2_client_registration_information_response_test extends \advanced_testcase {
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
        $clientinforesponse = oauth2_client_registration_information_response::from_response($response);
        $this->assertInstanceOf(oauth2_client_registration_information_response::class, $clientinforesponse);

        $this->assertTrue($clientinforesponse->is_successful());
        $this->assertInstanceOf(oauth2_client_registration_information::class, $clientinforesponse->get_client_information());
    }

    /**
     * Provides test data for the from_array() method.
     *
     * @return array test data.
     */
    public function array_provider(): array {
        return [
            'Valid, status code ok, response ok' => [
                'response' => new Response(201, ['Content-type' => 'application/json'], '{"client_id": "abc123"}'),
                'expected' => [
                    'successful' => true,
                ]
            ],
            'Invalid, status code ok, response malformed' => [
                'response' => new Response(201, ['Content-type' => 'application/json'], '{"client_id": "abc1'), // Bad JSON.
                'expected' => [
                    'exception' => \moodle_exception::class
                ]
            ],
            'Invalid, status code not 201' => [
                'response' => new Response(400, ['Content-type' => 'application/json'], '{"client_id": "abc123"}'),
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
        $regresponse = new oauth2_client_registration_information_response(...array_values($params));
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
            'Valid, all information params provided' => [
                'params' => [
                    new oauth2_client_registration_information(
                        '123abc',
                        oauth2_client_registration_metadata::from_array([
                            'client_name' => 'Institution site name',
                            'client_uri' => 'https://lms.example.org',
                            'logo_uri' => 'https://lms.example.org/pix/f/moodle-256.png',
                            'tos_uri' => 'https://lms.example.org',
                            'policy_uri' => 'https://lms.example.org',
                            'software_id' => 'moodle',
                            'software_version' => '4.1.1+',
                            'redirect_uris' => [
                                'https://lms.example.org/admin/oauth2callback.php'
                            ],
                            'jwks_uri' => 'https://lms.example.org/oauth/jwks',
                            'token_endpoint_auth_method' => 'client_secret_basic',
                            'grant_types' => [
                                'authorization_code',
                                'refresh_token'
                            ],
                            'response_types' => [
                                'code'
                            ],
                            'scope' => 'profile email',
                            'custom_field_1' => 'custom'
                        ]),
                        66666666,
                        'afbHa33edafbHa33edafbHa33edafbHa33ed',
                        88888888,
                    )
                ],
                'expected' => [
                    'response' => new Response(
                        201,
                        ['Cache-control' => 'no-store', 'Pragma' => 'no-cache', 'Content-type' => 'application/json'],
                        json_encode([
                            'client_name' => 'Institution site name',
                            'client_uri' => 'https://lms.example.org',
                            'logo_uri' => 'https://lms.example.org/pix/f/moodle-256.png',
                            'tos_uri' => 'https://lms.example.org',
                            'policy_uri' => 'https://lms.example.org',
                            'software_id' => 'moodle',
                            'software_version' => '4.1.1+',
                            'redirect_uris' => [
                                'https://lms.example.org/admin/oauth2callback.php'
                            ],
                            'jwks_uri' => 'https://lms.example.org/oauth/jwks',
                            'token_endpoint_auth_method' => 'client_secret_basic',
                            'grant_types' => [
                                'authorization_code',
                                'refresh_token'
                            ],
                            'response_types' => [
                                'code'
                            ],
                            'scope' => 'profile email',
                            'custom_field_1' => 'custom',
                            'client_id' => '123abc',
                            'client_id_issued_at' => 66666666,
                            'client_secret' => 'afbHa33edafbHa33edafbHa33edafbHa33ed',
                            'client_secret_expires_at' => 88888888,
                        ])
                    )
                ]
            ]
        ];
    }
}
