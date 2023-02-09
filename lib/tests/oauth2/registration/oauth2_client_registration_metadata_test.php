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
 * Unit tests for {@see oauth2_client_registration_metadata}.
 *
 * @coversDefaultClass oauth2_client_registration_metadata
 * @package core
 * @copyright 2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth2_client_registration_metadata_test extends \advanced_testcase {
    /**
     * Test the factory method.
     *
     * @covers ::from_array
     * @dataProvider array_provider
     * @param array $arraymetadata the metadata to create the instance with.
     * @param array $expected the test expectations.
     */
    public function test_from_array(array $arraymetadata, array $expected = []): void {
        if (!empty($expected['exception'])) {
            $this->expectException($expected['exception']);
        }
        $metadata = oauth2_client_registration_metadata::from_array($arraymetadata);
        $this->assertInstanceOf(oauth2_client_registration_metadata::class, $metadata);

        $internaldata = $metadata->to_array();
        foreach ($internaldata as $index => $val) {
            $this->assertEquals($expected['metadata'][$index], $val);
        }
    }

    /**
     * Provides test data for the from_array() method.
     *
     * @return array test data.
     */
    public function array_provider(): array {
        return [
            'Valid client metadata' => [
                'metadata' =>  [
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
                    'token_endpoint_auth_method' => 'client_secret_basic',
                    'grant_types' => [
                        'authorization_code',
                        'refresh_token'
                    ],
                    'response_types' => [
                        'code'
                    ],
                    'scope' => 'profile email',
                ],
                'expected' => [
                    'metadata' =>  [
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
                        'token_endpoint_auth_method' => 'client_secret_basic',
                        'grant_types' => [
                            'authorization_code',
                            'refresh_token'
                        ],
                        'response_types' => [
                            'code'
                        ],
                        'scope' => 'profile email',
                    ],
                ]
            ],
            'Valid, using custom field names is accepted' => [
                'metadata' =>  [
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
                    'token_endpoint_auth_method' => 'client_secret_basic',
                    'grant_types' => [
                        'authorization_code',
                        'refresh_token'
                    ],
                    'response_types' => [
                        'code'
                    ],
                    'scope' => 'profile email',
                    'custom_field_1' => 'x', // A custom field.
                    'custom_field_2' => 'y', // A custom field.
                ],
                'expected' => [
                    'metadata' =>  [
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
                        'token_endpoint_auth_method' => 'client_secret_basic',
                        'grant_types' => [
                            'authorization_code',
                            'refresh_token'
                        ],
                        'response_types' => [
                            'code'
                        ],
                        'scope' => 'profile email',
                        'custom_field_1' => 'x',
                        'custom_field_2' => 'y',
                    ],
                ]
            ],
            'Invalid, non-TLS redirect URI' => [
                'metadata' =>  [
                    'client_name' => 'Institution site name',
                    'client_uri' => 'https://lms.example.org',
                    'logo_uri' => 'https://lms.example.org/pix/f/moodle-256.png',
                    'tos_uri' => 'https://lms.example.org',
                    'policy_uri' => 'https://lms.example.org',
                    'software_id' => 'moodle',
                    'software_version' => '4.1.1+',
                    'redirect_uris' => [
                        'https://lms.example.org/admin/oauth2callback.php',
                        'http://lms.example.org/admin/anothercallback.php', // HTTP URI Invalid.
                    ],
                    'token_endpoint_auth_method' => 'client_secret_basic',
                    'grant_types' => [
                        'authorization_code',
                        'refresh_token'
                    ],
                    'response_types' => [
                        'code'
                    ],
                    'scope' => 'profile email',
                ],
                'expected' => [
                    'exception' => \moodle_exception::class
                ]
            ],
            'Invalid, non-TLS JWKS URI' => [
                'metadata' =>  [
                    'client_name' => 'Institution site name',
                    'client_uri' => 'https://lms.example.org',
                    'logo_uri' => 'https://lms.example.org/pix/f/moodle-256.png',
                    'tos_uri' => 'https://lms.example.org',
                    'policy_uri' => 'https://lms.example.org',
                    'software_id' => 'moodle',
                    'software_version' => '4.1.1+',
                    'redirect_uris' => [
                        'https://lms.example.org/admin/oauth2callback.php',
                    ],
                    'token_endpoint_auth_method' => 'client_secret_basic',
                    'grant_types' => [
                        'authorization_code',
                        'refresh_token'
                    ],
                    'response_types' => [
                        'code'
                    ],
                    'scope' => 'profile email',
                    'jwks_uri' => 'http://lms.example.org/jwks' // HTTP URI Invalid.
                ],
                'expected' => [
                    'exception' => \moodle_exception::class
                ]
            ],
            'Invalid, JWKS URI and JWKS both present in metadata' => [
                'metadata' =>  [
                    'client_name' => 'Institution site name',
                    'client_uri' => 'https://lms.example.org',
                    'logo_uri' => 'https://lms.example.org/pix/f/moodle-256.png',
                    'tos_uri' => 'https://lms.example.org',
                    'policy_uri' => 'https://lms.example.org',
                    'software_id' => 'moodle',
                    'software_version' => '4.1.1+',
                    'redirect_uris' => [
                        'https://lms.example.org/admin/oauth2callback.php',
                    ],
                    'token_endpoint_auth_method' => 'client_secret_basic',
                    'grant_types' => [
                        'authorization_code',
                        'refresh_token'
                    ],
                    'response_types' => [
                        'code'
                    ],
                    'scope' => 'profile email',
                    'jwks_uri' => 'https://lms.example.org/jwks', // Cannot include both jwks and jwks_uri.
                    'jwks' => '{"keys":[{"kty":"RSA","alg":"RS256","use":"sig","e":"AQAB","n":"3nVd97Ufpgcxz8Er5fk5I4qfP7Z5U0u1Hkk'.
                        'WUeLzZabY7JXXag8Ys38Cxe_ggc32NsI2vFUrLHISXkADD0fGxhs_YId-IgIbpY8arm5gK5-MCAWNS61yGCifCKrmu6tuoxPT8vdRTS1R'.
                        'KnYNGtqHeapoSTzm-quTjliKWeKBtmSObFP1nB3FdusqH8qh3iNe5LSL5v4DvsPxoXOB7TzWLtIitzC1vUGPaERX3n7MX1PAugYOgtDmY'.
                        'zPmEouMrTTNN9yciCPS3FXWX-h_30a-lslHs4S1gnb4sK1GKvPr_Syerjsnp4mSi3Zxx9NxOYA0_4qn998NoMdqeV33l2SgIQ","kid":'.
                        '"e0d6f2d8e3e6d0623982"}]}'
                ],
                'expected' => [
                    'exception' => \moodle_exception::class
                ]
            ],
            'Invalid, JWKS invalid JSON string' => [
                'metadata' =>  [
                    'client_name' => 'Institution site name',
                    'client_uri' => 'https://lms.example.org',
                    'logo_uri' => 'https://lms.example.org/pix/f/moodle-256.png',
                    'tos_uri' => 'https://lms.example.org',
                    'policy_uri' => 'https://lms.example.org',
                    'software_id' => 'moodle',
                    'software_version' => '4.1.1+',
                    'redirect_uris' => [
                        'https://lms.example.org/admin/oauth2callback.php',
                    ],
                    'token_endpoint_auth_method' => 'client_secret_basic',
                    'grant_types' => [
                        'authorization_code',
                        'refresh_token'
                    ],
                    'response_types' => [
                        'code'
                    ],
                    'scope' => 'profile email',
                    'jwks' => '{"keys":' // Invalid JSON string.
                ],
                'expected' => [
                    'exception' => \moodle_exception::class
                ]
            ],
            'Valid, accepting defaults for grant_types, response_types and token_endpoint_auth_method' => [
                'metadata' =>  [
                    'client_name' => 'Institution site name',
                    'client_uri' => 'https://lms.example.org',
                    'logo_uri' => 'https://lms.example.org/pix/f/moodle-256.png',
                    'tos_uri' => 'https://lms.example.org',
                    'policy_uri' => 'https://lms.example.org',
                    'software_id' => 'moodle',
                    'software_version' => '4.1.1+',
                    'redirect_uris' => [
                        'https://lms.example.org/admin/oauth2callback.php',
                    ],
                    'scope' => 'profile email',
                    'jwks_uri' => 'https://lms.example.org/jwks'
                ],
                'expected' => [
                    'metadata' =>  [
                        'client_name' => 'Institution site name',
                        'client_uri' => 'https://lms.example.org',
                        'logo_uri' => 'https://lms.example.org/pix/f/moodle-256.png',
                        'tos_uri' => 'https://lms.example.org',
                        'policy_uri' => 'https://lms.example.org',
                        'software_id' => 'moodle',
                        'software_version' => '4.1.1+',
                        'redirect_uris' => [
                            'https://lms.example.org/admin/oauth2callback.php',
                        ],
                        'scope' => 'profile email',
                        'jwks_uri' => 'https://lms.example.org/jwks',
                        'grant_types' => ['authorization_code'], // Receives a default value when omitted.
                        'response_types' => ['code'], // Receives a default value when omitted.
                        'token_endpoint_auth_method' => 'client_secret_basic', // Receives a default value when omitted.
                    ],
                ]
            ],
        ];
    }
}
