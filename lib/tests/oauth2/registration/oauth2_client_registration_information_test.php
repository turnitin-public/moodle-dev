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

/**
 * Unit tests for {@see oauth2_client_registration_information}.
 *
 * @coversDefaultClass oauth2_client_registration_information
 * @package core
 * @copyright 2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth2_client_registration_information_test extends \advanced_testcase {
    /**
     * Test the factory method.
     *
     * @covers ::from_array
     * @dataProvider from_array_provider
     * @param array $arrayclientinfo the metadata to create the instance with.
     * @param array $expected the test expectations.
     * @return void
     */
    public function test_from_array(array $arrayclientinfo, array $expected = []): void {
        if (!empty($expected['exception'])) {
            $this->expectException($expected['exception']);
        }
        $clientinfo = oauth2_client_registration_information::from_array($arrayclientinfo);
        $this->assertInstanceOf(oauth2_client_registration_information::class, $clientinfo);

        $this->assertEquals($expected['client_info']['client_id'], $clientinfo->get_client_id());
        $this->assertEquals($expected['client_info']['client_secret'], $clientinfo->get_client_secret());
        $this->assertEquals($expected['client_info']['client_id_issued_at'], $clientinfo->get_client_id_issued_at());
        $this->assertEquals($expected['client_info']['client_secret_expires_at'], $clientinfo->get_client_secret_expires_at());
        $this->assertInstanceOf(oauth2_client_registration_metadata::class, $clientinfo->get_metadata());
    }

    /**
     * Provides test data for the from_array() method.
     *
     * @return array test data.
     */
    public function from_array_provider(): array {
        return [
            'Valid, all client metadata provided' => [
                'response_data' =>  [
                    'client_id' => 'abc123',
                    'client_secret' => 'secret12345',
                    'client_id_issued_at' => '88888888',
                    'client_secret_expires_at' => '9999999',
                    'custom_field_1' => 'x',
                    'custom_field_2' => 'y',
                ],
                'expected' => [
                    'client_info' =>  [
                        'client_id' => 'abc123',
                        'client_secret' => 'secret12345',
                        'client_id_issued_at' => '88888888',
                        'client_secret_expires_at' => '9999999',
                    ],
                ]
            ],
            'Valid, only client_id provided' => [
                'response_data' =>  [
                    'client_id' => 'abc123',
                ],
                'expected' => [
                    'client_info' =>  [
                        'client_id' => 'abc123',
                        'client_secret' => null,
                        'client_id_issued_at' => null,
                        'client_secret_expires_at' => null,
                    ],
                ]
            ],
            'Invalid, client_id omitted' => [
                'response_data' =>  [
                    'client_secret' => 'secret12345',
                    'client_id_issued_at' => '88888888',
                    'client_secret_expires_at' => '9999999',
                ],
                'expected' => [
                    'exception' => \moodle_exception::class
                ]
            ],
            'Valid, client_secret provided without client_secret_expires_at, accepts default value of 0' => [
                'response_data' =>  [
                    'client_id' => 'abc123',
                    'client_secret' => 'secret12345',
                    'client_id_issued_at' => '88888888',
                ],
                'expected' => [
                    'client_info' =>  [
                        'client_id' => 'abc123',
                        'client_secret' => 'secret12345',
                        'client_id_issued_at' => '88888888',
                        'client_secret_expires_at' => 0,
                    ],
                ]
            ],
        ];
    }

    /**
     * Test exporting a client information instance to an array.
     *
     * @covers ::to_array
     * @dataProvider to_array_provider
     * @param array $params the constructor params for creating the client info instance.
     * @param array $expected the test expectations.
     * @return void
     */
    public function test_to_array(array $params, array $expected = []): void {
        $clientinfo = new oauth2_client_registration_information(...array_values($params));
        $this->assertEquals($expected['client_info'], $clientinfo->to_array());
    }

    /**
     * Data provider for testing the to_array() method.
     *
     * @return array test data.
     */
    public function to_array_provider(): array {
        return [
            'Valid, all args present, metadata contains custom field' => [
                'params' => [
                    'client_id' => '123abc',
                    'metadata' => oauth2_client_registration_metadata::from_array([
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
                    'client_id_issued_at' => 6666666,
                    'client_secret' => 'afbHa33edafbHa33edafbHa33edafbHa33ed',
                    'client_secret_expires_at' => 88888888,
                ],
                'expected' => [
                    'client_info' => [
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
                        'client_id_issued_at' => 6666666,
                        'client_secret' => 'afbHa33edafbHa33edafbHa33edafbHa33ed',
                        'client_secret_expires_at' => 88888888,
                    ]
                ]
            ],
            'Valid, minimal args present, metadata contains custom field' => [
                'params' => [
                    'client_id' => '123abc',
                    'metadata' => oauth2_client_registration_metadata::from_array([
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
                        'custom_field' => 'custom'
                    ]),
                ],
                'expected' => [
                    'client_info' => [
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
                        'custom_field' => 'custom',
                        'client_id' => '123abc',
                    ]
                ]
            ],
            'Valid, metadata contains custom field name clashes' => [
                'params' => [
                    'client_id' => '123abc', // We cannot override this by accepting the value from a metadata custom field.
                    'metadata' => oauth2_client_registration_metadata::from_array([
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
                        'client_id' => 'custom', // This will be discarded and client_id='abc123' (as above) used instead.
                        'client_id_issued_at' => 9999 // This will be discarded.
                    ]),
                ],
                'expected' => [
                    'client_info' => [
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
                        'client_id' => '123abc',
                    ]
                ]
            ]
        ];
    }
}
