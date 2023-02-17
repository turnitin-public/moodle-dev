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

namespace core\oauth2\discovery;

use core\http_client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Unit tests for {@see openid_config_reader}.
 *
 * @coversDefaultClass \core\oauth2\discovery\openid_config_reader
 * @package core
 * @copyright 2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openid_config_reader_test extends \advanced_testcase {

    /**
     * Test reading the config for an openid provider.
     *
     * @covers ::read_configuration
     * @dataProvider config_provider
     * @param string $issuerurl the openid provider issuer URL.
     * @param ResponseInterface $httpresponse a stub HTTP response.
     * @param array $expected test expectations.
     * @return void
     */
    public function test_read_configuration(string $issuerurl, ResponseInterface $httpresponse, array $expected = []) {
        $mock = new MockHandler([$httpresponse]);
        $handlerStack = HandlerStack::create($mock);
        if (!empty($expected['request'])) {
            // Request history tracking to allow asserting that request was sent as expected below (to the stub client).
            $container = [];
            $history = Middleware::history($container);
            $handlerStack->push($history);
        }

        if (!empty($expected['exception'])) {
            $this->expectException($expected['exception']);
        }
        $configreader = new openid_config_reader(new http_client(['handler' => $handlerStack]), new \moodle_url($issuerurl));

        $config = $configreader->read_configuration();

        if (!empty($expected['request'])) {
            // Verify the request goes to the correct URL (i.e. the well known suffix is correctly positioned).
            $this->assertEquals($expected['request']['url'], $container[0]['request']->getUri());
        }

        $this->assertEquals($expected['metadata'], (array) $config);
    }

    /**
     * Provider for testing read_configuration().
     *
     * @return array test data.
     */
    public function config_provider(): array {
        return [
            'Valid, good issuer URL, good config' => [
                'issuer_url' => 'https://app.example.com',
                'http_response' => new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        "issuer" => "https://app.example.com",
                        "authorization_endpoint" => "https://app.example.com/authorize",
                        "token_endpoint" => "https://app.example.com/token",
                        "token_endpoint_auth_methods_supported" => [
                            "client_secret_basic",
                            "private_key_jwt"
                        ],
                        "token_endpoint_auth_signing_alg_values_supported" => [
                            "RS256",
                            "ES256"
                        ],
                        "userinfo_endpoint" => "https://app.example.com/userinfo",
                        "jwks_uri" => "https://app.example.com/jwks.json",
                        "registration_endpoint" => "https://app.example.com/register",
                        "scopes_supported" => [
                            "openid",
                            "profile",
                            "email",
                        ],
                        "response_types_supported" => [
                            "code",
                            "code token"
                        ],
                        "service_documentation" => "http://app.example.com/service_documentation.html",
                        "ui_locales_supported" => [
                            "en-US",
                            "en-GB",
                            "fr-FR",
                        ]
                    ])
                ),
                'expected' => [
                    'request' => [
                        'url' => 'https://app.example.com/.well-known/openid-configuration'
                    ],
                    'metadata' => [
                        "issuer" => "https://app.example.com",
                        "authorization_endpoint" => "https://app.example.com/authorize",
                        "token_endpoint" => "https://app.example.com/token",
                        "token_endpoint_auth_methods_supported" => [
                            "client_secret_basic",
                            "private_key_jwt"
                        ],
                        "token_endpoint_auth_signing_alg_values_supported" => [
                            "RS256",
                            "ES256"
                        ],
                        "userinfo_endpoint" => "https://app.example.com/userinfo",
                        "jwks_uri" => "https://app.example.com/jwks.json",
                        "registration_endpoint" => "https://app.example.com/register",
                        "scopes_supported" => [
                            "openid",
                            "profile",
                            "email",
                        ],
                        "response_types_supported" => [
                            "code",
                            "code token"
                        ],
                        "service_documentation" => "http://app.example.com/service_documentation.html",
                        "ui_locales_supported" => [
                            "en-US",
                            "en-GB",
                            "fr-FR",
                        ]
                    ]
                ]
            ],
            'Valid, issuer URL with path component confirming well known suffix placement' => [
                'issuer_url' => 'https://app.example.com/some/path',
                'http_response' => new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        "issuer" => "https://app.example.com",
                        "authorization_endpoint" => "https://app.example.com/authorize",
                        "token_endpoint" => "https://app.example.com/token",
                        "token_endpoint_auth_methods_supported" => [
                            "client_secret_basic",
                            "private_key_jwt"
                        ],
                        "token_endpoint_auth_signing_alg_values_supported" => [
                            "RS256",
                            "ES256"
                        ],
                        "userinfo_endpoint" => "https://app.example.com/userinfo",
                        "jwks_uri" => "https://app.example.com/jwks.json",
                        "registration_endpoint" => "https://app.example.com/register",
                        "scopes_supported" => [
                            "openid",
                            "profile",
                            "email",
                        ],
                        "response_types_supported" => [
                            "code",
                            "code token"
                        ],
                        "service_documentation" => "http://app.example.com/service_documentation.html",
                        "ui_locales_supported" => [
                            "en-US",
                            "en-GB",
                            "fr-FR",
                        ]
                    ])
                ),
                'expected' => [
                    'request' => [
                        'url' => 'https://app.example.com/some/path/.well-known/openid-configuration'
                    ],
                    'metadata' => [
                        "issuer" => "https://app.example.com",
                        "authorization_endpoint" => "https://app.example.com/authorize",
                        "token_endpoint" => "https://app.example.com/token",
                        "token_endpoint_auth_methods_supported" => [
                            "client_secret_basic",
                            "private_key_jwt"
                        ],
                        "token_endpoint_auth_signing_alg_values_supported" => [
                            "RS256",
                            "ES256"
                        ],
                        "userinfo_endpoint" => "https://app.example.com/userinfo",
                        "jwks_uri" => "https://app.example.com/jwks.json",
                        "registration_endpoint" => "https://app.example.com/register",
                        "scopes_supported" => [
                            "openid",
                            "profile",
                            "email",
                        ],
                        "response_types_supported" => [
                            "code",
                            "code token"
                        ],
                        "service_documentation" => "http://app.example.com/service_documentation.html",
                        "ui_locales_supported" => [
                            "en-US",
                            "en-GB",
                            "fr-FR",
                        ]
                    ]
                ]
            ],
            'Invalid, non HTTPS issuer URL' => [
                'issuer_url' => 'http://app.example.com',
                'http_response' => new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        "issuer" => "https://app.example.com",
                        "authorization_endpoint" => "https://app.example.com/authorize",
                        "token_endpoint" => "https://app.example.com/token",
                        "token_endpoint_auth_methods_supported" => [
                            "client_secret_basic",
                            "private_key_jwt"
                        ],
                        "token_endpoint_auth_signing_alg_values_supported" => [
                            "RS256",
                            "ES256"
                        ],
                        "userinfo_endpoint" => "https://app.example.com/userinfo",
                        "jwks_uri" => "https://app.example.com/jwks.json",
                        "registration_endpoint" => "https://app.example.com/register",
                        "scopes_supported" => [
                            "openid",
                            "profile",
                            "email",
                        ],
                        "response_types_supported" => [
                            "code",
                            "code token"
                        ],
                        "service_documentation" => "http://app.example.com/service_documentation.html",
                        "ui_locales_supported" => [
                            "en-US",
                            "en-GB",
                            "fr-FR",
                        ]
                    ])
                ),
                'expected' => [
                    'exception' => \moodle_exception::class
                ]
            ],
            'Invalid, query string in issuer URL' => [
                'issuer_url' => 'https://app.example.com?test=cat',
                'http_response' => new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        "issuer" => "https://app.example.com",
                        "authorization_endpoint" => "https://app.example.com/authorize",
                        "token_endpoint" => "https://app.example.com/token",
                        "token_endpoint_auth_methods_supported" => [
                            "client_secret_basic",
                            "private_key_jwt"
                        ],
                        "token_endpoint_auth_signing_alg_values_supported" => [
                            "RS256",
                            "ES256"
                        ],
                        "userinfo_endpoint" => "https://app.example.com/userinfo",
                        "jwks_uri" => "https://app.example.com/jwks.json",
                        "registration_endpoint" => "https://app.example.com/register",
                        "scopes_supported" => [
                            "openid",
                            "profile",
                            "email",
                        ],
                        "response_types_supported" => [
                            "code",
                            "code token"
                        ],
                        "service_documentation" => "http://app.example.com/service_documentation.html",
                        "ui_locales_supported" => [
                            "en-US",
                            "en-GB",
                            "fr-FR",
                        ]
                    ])
                ),
                'expected' => [
                    'exception' => \moodle_exception::class
                ]
            ],
            'Invalid, fragment in issuer URL' => [
                'issuer_url' => 'https://app.example.com/#cat',
                'http_response' => new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        "issuer" => "https://app.example.com",
                        "authorization_endpoint" => "https://app.example.com/authorize",
                        "token_endpoint" => "https://app.example.com/token",
                        "token_endpoint_auth_methods_supported" => [
                            "client_secret_basic",
                            "private_key_jwt"
                        ],
                        "token_endpoint_auth_signing_alg_values_supported" => [
                            "RS256",
                            "ES256"
                        ],
                        "userinfo_endpoint" => "https://app.example.com/userinfo",
                        "jwks_uri" => "https://app.example.com/jwks.json",
                        "registration_endpoint" => "https://app.example.com/register",
                        "scopes_supported" => [
                            "openid",
                            "profile",
                            "email",
                        ],
                        "response_types_supported" => [
                            "code",
                            "code token"
                        ],
                        "service_documentation" => "http://app.example.com/service_documentation.html",
                        "ui_locales_supported" => [
                            "en-US",
                            "en-GB",
                            "fr-FR",
                        ]
                    ])
                ),
                'expected' => [
                    'exception' => \moodle_exception::class
                ]
            ],
            'Valid, port in issuer URL' => [
                'issuer_url' => 'https://app.example.com:8080/some/path',
                'http_response' => new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        "issuer" => "https://app.example.com",
                        "authorization_endpoint" => "https://app.example.com/authorize",
                        "token_endpoint" => "https://app.example.com/token",
                        "token_endpoint_auth_methods_supported" => [
                            "client_secret_basic",
                            "private_key_jwt"
                        ],
                        "token_endpoint_auth_signing_alg_values_supported" => [
                            "RS256",
                            "ES256"
                        ],
                        "userinfo_endpoint" => "https://app.example.com/userinfo",
                        "jwks_uri" => "https://app.example.com/jwks.json",
                        "registration_endpoint" => "https://app.example.com/register",
                        "scopes_supported" => [
                            "openid",
                            "profile",
                            "email",
                        ],
                        "response_types_supported" => [
                            "code",
                            "code token"
                        ],
                        "service_documentation" => "http://app.example.com/service_documentation.html",
                        "ui_locales_supported" => [
                            "en-US",
                            "en-GB",
                            "fr-FR",
                        ]
                    ])
                ),
                'expected' => [
                    'request' => [
                        'url' => 'https://app.example.com:8080/some/path/.well-known/openid-configuration'
                    ],
                    'metadata' => [
                        "issuer" => "https://app.example.com",
                        "authorization_endpoint" => "https://app.example.com/authorize",
                        "token_endpoint" => "https://app.example.com/token",
                        "token_endpoint_auth_methods_supported" => [
                            "client_secret_basic",
                            "private_key_jwt"
                        ],
                        "token_endpoint_auth_signing_alg_values_supported" => [
                            "RS256",
                            "ES256"
                        ],
                        "userinfo_endpoint" => "https://app.example.com/userinfo",
                        "jwks_uri" => "https://app.example.com/jwks.json",
                        "registration_endpoint" => "https://app.example.com/register",
                        "scopes_supported" => [
                            "openid",
                            "profile",
                            "email",
                        ],
                        "response_types_supported" => [
                            "code",
                            "code token"
                        ],
                        "service_documentation" => "http://app.example.com/service_documentation.html",
                        "ui_locales_supported" => [
                            "en-US",
                            "en-GB",
                            "fr-FR",
                        ]
                    ]
                ]
            ]
        ];
    }
}
