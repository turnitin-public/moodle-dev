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

use GuzzleHttp\Psr7\Request;

/**
 * Unit tests for {@see oauth2_client_registration_request}.
 *
 * @coversDefaultClass oauth2_client_registration_request
 * @package core
 * @copyright 2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth2_client_registration_request_test extends \advanced_testcase {

    /**
     * Testing the constructor logic.
     *
     * @covers ::__construct
     * @dataProvider construct_data_provider
     * @param string $uri the URI to send the request to
     * @param array $expected the test case expectations
     */
    public function test_request_construct(string $uri, array $expected = []): void {
        if (!empty($expected['exception'])) {
            $this->expectException($expected['exception']);
        }
        $this->assertInstanceOf(
            oauth2_client_registration_request::class,
            new oauth2_client_registration_request(new \moodle_url($uri), oauth2_client_registration_metadata::from_array([])
        ));
    }

    /**
     * Provides constructor logic test data.
     *
     * @return array test data.
     */
    public function construct_data_provider(): array {
        return [
            'Invalid URI scheme' => [
                'uri' => 'http://example.com',
                'expected' => [
                    'exception' => \moodle_exception::class
                ]
            ],
            'Valid scheme' => [
                'uri' => 'https://example.com',
            ]
        ];
    }

    /**
     * Test getting the HTTP request object.
     *
     * @covers ::to_request
     */
    public function test_to_request() {
        $metadata = oauth2_client_registration_metadata::from_array(['redirect_uris' => ['https://my.lms.org']]);
        $uri = new \moodle_url('https://example.com');
        $regrequest = new oauth2_client_registration_request(new \moodle_url($uri), $metadata);

        $httprequest = $regrequest->to_request();

        $this->assertInstanceOf(Request::class, $httprequest);
        $this->assertEquals('POST', $httprequest->getMethod());
        $this->assertEquals('application/json', $httprequest->getHeader('Content-type')[0]);
        $this->assertEquals('application/json', $httprequest->getHeader('Accept')[0]);
        $body = $httprequest->getBody()->getContents();
        $this->assertJson($body);
        $this->assertStringContainsString('redirect_uris', $body);
    }
}
