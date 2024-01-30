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

namespace local\ltiadvantage\utility;

use auth_lti\local\ltiadvantage\utility\cookie_helper;

/**
 * Tests for the cookie_helper utlity class.
 *
 * @package    auth_lti
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \auth_lti\local\ltiadvantage\utility\cookie_helper
 */
class cookie_helper_test extends \advanced_testcase {

    /**
     * Test the cookie_headers_add_attribute() method.
     *
     * @dataProvider cookie_partitioning_provider
     * @covers ::cookie_headers_add_attribute
     *
     * @param array $headers the headers to search
     * @param array $cookienames the cookienames to match
     * @param string $attribute the attribute to add
     * @param bool $casesensitive whether to do a case-sensitive lookup for the attribute
     * @param array $expectedheaders the expected, updated headers
     * @return void
     */
    public function test_cookie_headers_add_attribute(array $headers, array $cookienames, string $attribute, bool $casesensitive,
            array $expectedheaders): void {

        $updatedheaders = cookie_helper::cookie_headers_add_attribute($headers, $cookienames, $attribute, $casesensitive);
        $this->assertEquals($expectedheaders, $updatedheaders);
    }

    /**
     * Data provider for testing partitioning opt in.
     *
     * @return array the test data.
     */
    public function cookie_partitioning_provider(): array {
        return [
            'Only one matching cookie header, without attribute' => [
                'headers' => [
                    'Set-Cookie: testcookie=value; path=/test/; secure; HttpOnly; SameSite=None',
                ],
                'cookienames' => [
                    'testcookie',
                ],
                'attribute' => 'Partitioned',
                'casesensitive' => false,
                'output' => [
                    'Set-Cookie: testcookie=value; path=/test/; secure; HttpOnly; SameSite=None; Partitioned;',
                ]
            ],
            'Several matching cookie headers, without attribute' => [
                'headers' => [
                    'Set-Cookie: testcookie=value; path=/test/; secure; HttpOnly; SameSite=None',
                    'Set-Cookie: mytestcookie=value; path=/test/; secure; HttpOnly; SameSite=None',
                ],
                'cookienames' => [
                    'testcookie',
                    'mytestcookie',
                ],
                'attribute' => 'Partitioned',
                'casesensitive' => false,
                'output' => [
                    'Set-Cookie: testcookie=value; path=/test/; secure; HttpOnly; SameSite=None; Partitioned;',
                    'Set-Cookie: mytestcookie=value; path=/test/; secure; HttpOnly; SameSite=None; Partitioned;',
                ]
            ],
            'Several matching cookie headers, several non-matching, all without attribute' => [
                'headers' => [
                    'Set-Cookie: testcookie=value; path=/test/; secure; HttpOnly; SameSite=None',
                    'Set-Cookie: mytestcookie=value; path=/test/; secure; HttpOnly; SameSite=None',
                    'Set-Cookie: anothertestcookie=value; path=/test/; secure; HttpOnly; SameSite=None',
                ],
                'cookienames' => [
                    'testcookie',
                    'mytestcookie',
                    'blah',
                    'etc',
                ],
                'attribute' => 'Partitioned',
                'casesensitive' => false,
                'output' => [
                    'Set-Cookie: testcookie=value; path=/test/; secure; HttpOnly; SameSite=None; Partitioned;',
                    'Set-Cookie: mytestcookie=value; path=/test/; secure; HttpOnly; SameSite=None; Partitioned;',
                    'Set-Cookie: anothertestcookie=value; path=/test/; secure; HttpOnly; SameSite=None',
                ]
            ],
            'Matching cookie headers some with existing attribute' => [
                'headers' => [
                    'Set-Cookie: testcookie=value; path=/test/; secure; HttpOnly; Partitioned; SameSite=None',
                    'Set-Cookie: mytestcookie=value; path=/test/; secure; HttpOnly; SameSite=None',
                ],
                'cookienames' => [
                    'testcookie',
                    'mytestcookie',
                    'etc',
                ],
                'attribute' => 'Partitioned',
                'casesensitive' => false,
                'output' => [
                    'Set-Cookie: testcookie=value; path=/test/; secure; HttpOnly; Partitioned; SameSite=None',
                    'Set-Cookie: mytestcookie=value; path=/test/; secure; HttpOnly; SameSite=None; Partitioned;',
                ]
            ],
            'Matching headers having match, case sensitive only' => [
                'headers' => [
                    'Set-Cookie: testcookie=value; path=/test/; secure; HttpOnly; SameSite=None; attribute=X',
                    'Set-Cookie: mytestcookie=value; path=/test/; secure; HttpOnly; SameSite=None',
                ],
                'cookienames' => [
                    'testcookie',
                    'mytestcookie',
                    'etc',
                ],
                'attribute' => 'attribute=x', // Note: Lower case 'x'.
                'casesensitive' => true,
                'output' => [
                    'Set-Cookie: testcookie=value; path=/test/; secure; HttpOnly; SameSite=None; attribute=X; attribute=x;',
                    'Set-Cookie: mytestcookie=value; path=/test/; secure; HttpOnly; SameSite=None; attribute=x;',
                ]
            ],
            'Other HTTP headers, some matching Set-Cookie, some not' => [
                'headers' => [
                    'Authorization: blah',
                    'Set-Cookie: testcookie=value; path=/test/; secure; HttpOnly; SameSite=None; Partitioned',
                    'Set-Cookie: mytestcookie=value; path=/test/; secure; HttpOnly; SameSite=None',
                ],
                'cookienames' => [
                    'testcookie',
                    'mytestcookie',
                ],
                'attribute' => 'Partitioned',
                'casesensitive' => false,
                'output' => [
                    'Authorization: blah',
                    'Set-Cookie: testcookie=value; path=/test/; secure; HttpOnly; SameSite=None; Partitioned',
                    'Set-Cookie: mytestcookie=value; path=/test/; secure; HttpOnly; SameSite=None; Partitioned;',
                ]
            ],
        ];
    }
}
