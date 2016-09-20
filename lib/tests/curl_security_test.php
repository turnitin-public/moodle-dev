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

/**
 * Unit tests for /lib/classes/curl_security.php.
 *
 * @package   core
 * @category  phpunit
 * @copyright 2016 Jake Dallimore
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * cURL security test suite.
 *
 * @package    core
 * @copyright  2016 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_curl_security_testcase extends advanced_testcase {
    /**
     * @dataProvider curl_security_url_data_provider
     */
    public function test_curl_security_url_is_blacklisted($url, $blockedhosts, $allowedports, $expected) {
        $this->resetAfterTest(true);
        set_config('curlsecurityblockedhosts', $blockedhosts);
        set_config('curlsecurityallowedport', $allowedports);
        $this->assertEquals($expected, \core\curl_security::url_is_blacklisted($url));
    }

    function curl_security_url_data_provider() {
        // Format: url, blocked hosts, allowed ports, expected result.
        return [
            // Base set without the blacklist enabled - no checking takes place.
            ["http://localhost/x.png", "", "", false],      // IP=127.0.0.1, Port=80 (port inferred from http).
            ["http://localhost:80/x.png", "", "", false],   // IP=127.0.0.1, Port=80 (specific port overrides http scheme).
            ["https://localhost/x.png", "", "", false],     // IP=127.0.0.1, Port=443 (port inferred from https).
            ["http://localhost:443/x.png", "", "", false],  // IP=127.0.0.1, Port=443 (specific port overrides http scheme).
            ["localhost/x.png", "", "", false],             // IP=127.0.0.1, Port=80 (port inferred from http fallback).
            ["localhost:443/x.png", "", "", false],         // IP=127.0.0.1, Port=443 (port hard specified, despite http fallback).
            ["http://127.0.0.1/x.png", "", "", false],      // IP=127.0.0.1, Port=80 (port inferred from http).
            ["127.0.0.1/x.png", "", "", false],             // IP=127.0.0.1, Port=80 (port inferred from http fallback).
            ["http://localhost:8080/x.png", "", "", false], // IP=127.0.0.1, Port=8080 (port hard specified).
            ["http://192.168.1.10/x.png", "", "", false],   // IP=192.168.1.10, Port=80 (port inferred from http).
            ["https://192.168.1.10/x.png", "", "", false],  // IP=192.168.1.10, Port=443 (port inferred from https).
            ["http://sub.example.com/x.png", "", "", false], // IP=::1, Port = 80 (port inferred from http).
            ["http://s-1.d-1.com/x.png", "", "", false],    // IP=::1, Port = 80 (port inferred from http).

            // Test set using domain name filters but with all ports allowed (empty).
            ["http://localhost/x.png", "localhost", "", true],
            ["localhost/x.png", "localhost", "", true],
            ["localhost:0/x.png", "localhost", "", true],
            ["ftp://localhost/x.png", "localhost", "", true],
            ["http://127.0.0.1/x.png", "localhost", "", true],
            ["http://sub.example.com/x.png", "localhost", "", false],
            ["http://example.com/x.png", "example.com", "", true],
            ["http://sub.example.com/x.png", "example.com", "", false],

            // Test set using wildcard domain name filters but with all ports allowed (empty).
            ["http://sub.example.com/x.png", "*.com", "", true],
            ["http://example.com/x.png", "*.example.com", "", false],
            ["http://sub.example.com/x.png", "*.example.com", "", true],
            ["http://sub.example.com/x.png", "*.sub.example.com", "", false],
            // ["http://sub.example.com/x.png", "*.example", "", false], // This should be false, but cmpFQDN lets it through.

            // Test set using IP address filters but with all ports allowed (empty).
            ["http://localhost/x.png", "127.0.0.1", "", true],
            ["http://127.0.0.1/x.png", "127.0.0.1", "", true],
            ["http://sub.example.com", "127.0.0.1", "", false],

            // Test set using CIDR IP range filters but with all ports allowed (empty).
            ["http://localhost/x.png", "127.0.0.0/24", "", true],
            ["http://127.0.0.1/x.png", "127.0.0.0/24", "", true],
            ["http://sub.example.com", "127.0.0.0/24", "", false],

            // Test set using last-group range filters but with all ports allowed (empty).
            ["http://localhost/x.png", "127.0.0.0-30", "", true],
            ["http://127.0.0.1/x.png", "127.0.0.0-30", "", true],
            ["http://sub.example.com", "127.0.0.0/24", "", false],

            // Test set using port filters but with all hosts allowed (empty).
            ["http://localhost/x.png", "", "80\n443", false],
            ["http://localhost:80/x.png", "", "80\n443", false],
            ["https://localhost/x.png", "", "80\n443", false],
            ["http://localhost:443/x.png", "", "80\n443", false],
            ["http://sub.example.com:8080/x.png", "", "80\n443", true],
            ["http://sub.example.com:-80/x.png", "", "80\n443", true],
            ["http://sub.example.com:aaa/x.png", "", "80\n443", true],

            // Test set using port filters and hosts filters.
            ["http://localhost/x.png", "127.0.0.1", "80\n443", true],
            ["http://127.0.0.1/x.png", "127.0.0.1", "80\n443", true],
            ["http://sub.example.com", "127.0.0.1", "80\n443", false],

            // Note on testing IPv6 notation:
            // At present, the 'curl_security' class doesn't support IPv6 url notation.
            // E.g.  http://[ad34::dddd]:port/resource
            // This is because it uses clean_param(x, PARAM_URL) as part of parsing, which won't validate urls using IPv6 notation.
            // The underlying IPv6 address and range support is in place, however, so if clean_param is changed in future,
            // please uncomment the following test sets.

            // IPv6 notation tests.
            //["http://[::1]/x.png", "", "", false], // No config set, so no parsing takes place.
            //["http://[::1]/x.png", "::1", "", true],
            //["http://[::1]/x.png", "::1/64", "", true],
            //["http://[fe80::dddd]/x.png", "fe80::cccc-eeee", "", true],
            //["http://[fe80::dddd]/x.png", "fe80::dddd/128", "", true],
        ];
    }

    /**
     * @dataProvider curl_security_settings_data_provider
     */
    public function test_curl_security_is_enabled($blockedhosts, $allowedports, $expected) {
        $this->resetAfterTest(true);
        set_config('curlsecurityblockedhosts', $blockedhosts);
        set_config('curlsecurityallowedport', $allowedports);
        $this->assertEquals($expected, \core\curl_security::is_enabled());
    }

    function curl_security_settings_data_provider() {
        // Format: blocked hosts, allowed ports, expected result.
        return [
            ["", "", false],
            ["127.0.0.1", "", true],
            ["localhost", "", true],
            ["127.0.0.0/24\n192.0.0.0/24", "", true],
            ["", "80\n443", true],
        ];
    }

    /**
     * @dataProvider curl_security_host_data_provider
     */
    public function test_curl_security_host_is_blocked($host, $blockedhosts, $expected) {
        $this->resetAfterTest(true);
        set_config('curlsecurityblockedhosts', $blockedhosts);
        $this->assertEquals($expected, \core\curl_security::host_is_blocked($host));
    }

    function curl_security_host_data_provider() {
        return [
            // IPv4 hosts.
            ["127.0.0.1", "127.0.0.1", true],
            ["127.0.0.1", "127.0.0.0/24", true],
            ["127.0.0.1", "127.0.0.0-40", true],
            ["127.0.0.1", "localhost", true], // Matched after a DNS reverse lookup.
            ["127.0.0.1", "*.localhost", false],
            ["", "127.0.0.0/24", false],


            // IPv6 hosts.
            // ["::", "::", true], // This should match but 'address_in_subnet()' has trouble with fully collapsed IPv6 addresses.
            ["::1", "::1", true],
            ["::1", "::0-cccc", true],
            ["::1", "::0/64", true],
            ["FE80:0000:0000:0000:0000:0000:0000:0000", "fe80::/128", true],
            ["fe80::eeee", "fe80::ddde/64", true],
            ["fe80::dddd", "fe80::cccc-eeee", true],
            ["fe80::dddd", "fe80::ddde-eeee", false],

            // Domain name hosts.
            ["example.com", "example.com", true],
            ["example.com", "*.com", true],
            ["example.com", "*.example.com", false],
            ["sub.example.com", "*.example.com", true],
            ["sub.sub.example.com", "*.example.com", true],
            ["sub.example.com", "*example.com", false],
            // ["sub.example.com", "*.example", false], // This should be false, but cmpFQDN lets it through.
            ["localhost", "127.0.0.1", true], // Matched after a DNS forward lookup.
            ["localhost", "127.0.0.0/24", true],
            ["localhost", "127.0.0.0-40", true],
        ];
    }

    /**
     * @dataProvider curl_security_port_data_provider
     */
    public function test_curl_security_port_is_blocked($port, $allowedports, $expected) {
        $this->resetAfterTest(true);
        set_config('curlsecurityallowedport', $allowedports);
        $this->assertEquals($expected, \core\curl_security::port_is_blocked($port));
    }

    function curl_security_port_data_provider() {
        return [
            ["", "80\n443", true],
            [" ", "80\n443", true],
            ["-1", "80\n443", true],
            [-1, "80\n443", true],
            ["n", "80\n443", true],
            [0, "80\n443", true],
            ["0", "80\n443", true],
            [8080, "80\n443", true],
            ["8080", "80\n443", true],
            ["80", "80\n443", false],
            [80, "80\n443", false],
            [443, "80\n443", false],
        ];
    }

    function test_curl_security_get_blocked_url_string() {
        $this->assertEquals(get_string('curlsecurityurlblocked', 'admin'), \core\curl_security::get_blocked_url_string());
    }
}
