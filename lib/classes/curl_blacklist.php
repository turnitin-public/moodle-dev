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

namespace core;

defined('MOODLE_INTERNAL') || exit();

/**
 * cURL IP and port restriction class.
 *
 * This class allows one to query the blacklist for blocked URLs, IPs and ports. It does not provide a means to add
 * URLs, IPs or ports to the blacklist however, as this is configured manually via the site admin section of Moodle.
 * E.g. 'Site admin' > 'Security' > 'cURL Security'.
 *
 * This class is currently used by the 'curl' wrapper class in lib/filelib.php.
 *
 * @since Moodle 3.1.1
 * @package   core
 * @copyright 2016 Jake Dallimore
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jake Dallimore <jrhdallimore@gmail.com>
 */
class curl_blacklist {

    private $parsedips = array();
    private $parsedport = 0;
    private $error = '';

    /**
     * curl_blacklist constructor.
     */
    public function __construct() {}

    /**
     * Checks whether the given URL is blacklisted by checking its IP address and port number.
     * The behaviour of this function can be classified as strict, as it returns true for URLs which are invalid or
     * could not be parsed, as well as those valid URLs which were found in the blacklist.
     * @since Moodle 3.1.1
     *
     * @param $url string the URL to check.
     *
     * @return bool true if the URL is valid and blacklisted, or if the URL is invalid/could not be parsed. Return
     * false if the URL is valid and not blacklisted.
     */
    public function url_is_blacklisted($url) {
        if ($this->parse_url($url) === true) {
            return $this->parsed_url_is_blacklisted();
        }
        return true;
    }

    /**
     * Convenience wrapper method used to check whether a given IP is blacklisted.
     * All checks handled by the private method.
     * @since Moodle 3.1.1
     *
     * @param $ip string the IP address to check.
     *
     * @return bool true if the IP is blacklisted, false otherwise.
     */
    public function ip_is_blacklisted($ip) {
        return $this->host_is_blocked($ip);
    }

    /**
     * Convenience wrapper method used to check whether a given port is blacklisted (i.e. not in the whitelist).
     * All checks handled by the private method.
     * @since Moodle 3.1.1
     *
     * @param $port integer port to check against the whitelist.
     *
     * @return bool true if the port is blacklisted, false otherwise.
     */
    public function port_is_blacklisted($port) {
        return $this->port_is_blocked($port);
    }

    /**
     * Returns the error string associated with the last URL/IP/Port check.
     * E.g. "Port is blocked", "IP is blocked", etc.
     * @since Moodle 3.1.1
     *
     * @return string the error associated with the last check, or an empty string if the URL, IP, port isn't blocked.
     */
    public function get_error_string() {
        return $this->error;
    }

    /**
     * Checks whether the most recently parsed URL is blocked by checking its IP address and port number.
     * Should only be called after a successful parsing operation.
     * @since Moodle 3.1.1
     *
     * @return bool true if the URL is blocked, false otherwise.
     */
    private function parsed_url_is_blacklisted() {
        foreach ($this->parsedips as $ip) {
            if ($this->host_is_blocked($ip)) {
                $this->error = get_string('curlblacklistipblocked', 'admin', $ip);
                return true;
            }
        }
        if ($this->port_is_blocked($this->parsedport)) {
            $this->error = get_string('curlblacklistportblocked', 'admin', $this->parsedport);
            return true;
        }
        return false;
    }

    /**
     * Checks whether the given IP address is blocked, as determined by its presence on the blacklist.
     * @since Moodle 3.1.1
     *
     * @param $ip string the IP address to check.
     *
     * @return bool true if the IP is both valid and blocked, false otherwise.
     */
    private function host_is_blocked($ip) {
        if (empty($ip) || !is_string($ip)) {
            return false;
        }

        global $CFG;
        $list = explode("\n", $CFG->curlblacklistblockedip);

        foreach ($list as $subnet) {
            $subnet = trim($subnet);
            if (address_in_subnet($ip, $subnet)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks whether the given port is blocked, as determined by its absence on the whitelist.
     * Ports are assumed to be blocked unless found in the whitelist.
     * @since Moodle 3.1.1
     *
     * @param $port integer port to check against the whitelist.
     *
     * @return bool true if the port is blocked, false otherwise.
     */
    private function port_is_blocked($port) {
        // Intentionally block port 0 as use of it is considered bad practise.
        if (empty($port) || !is_int($port)) {
           return true;
        }

        global $CFG;
        $list = explode("\n", $CFG->curlblacklistallowedport);
        return (!in_array($port, $list));
    }

    /**
     * Try to parse an http/https URL to determine the IP and port.
     * If the URL is malformed or if PHP cannot parse it, this function will return false.
     * @since Moodle 3.1.1
     *
     * @param $url string URL to be parsed.
     *
     * @return bool true if the URL was successfully parsed, false otherwise.
     */
    private function parse_url($url) {
        // Reset all parsing state vars.
        $this->parsedips = array();
        $this->parsedport = 0;
        $this->error = '';

        // Let's check the URL syntax first.
        $url = clean_param($url, PARAM_URL);
        if (empty($url)) {
            $this->error = get_string('curlblacklisturlblocked', 'admin');
            return false;
        }

        // To parse the URL, we first need a protocol. If we don't have one, default to http://.
        if (strpos($url, "://") === false && substr($url, 0, 1) != "/") {
            $url = "http://" . $url;
        }

        // Try to parse the URL. Note, there may be some URLs that pass validation but which PHP cannot parse.
        // E.g. http://localhost:0/text.txt.
        if (($parsedinfo  = parse_url($url)) === false) {
            $this->error = get_string('curlblacklisturlblocked', 'admin');
            return false;
        }

        // Valid and parsed, so set up the local vars.
        $ips = array();
        $port = null;
        $parseerror = '';

        // Fetch the hostname and determine its IP address.
        if (!empty($parsedinfo['host'])) {
            $ips = gethostbynamel($parsedinfo['host']);
        }

        // Try to retrieve/infer the port number.
        if (!empty($parsedinfo['port'])) {
            // Port has been specified directly.
            $port = $parsedinfo['port'];
        } else if (!empty($parsedinfo['scheme'])) {
            // Otherwise, infer it from the scheme, but we only support http(s) transport schemes.
            if ($parsedinfo['scheme'] == 'http') {
                $port = 80;
            } else if ($parsedinfo['scheme'] == 'https') {
                $port = 443;
            }
        }

        // Check whether we have IP and port.
        if (empty($ips)) {
            $parseerror = get_string('curlblacklisturlparseiperror', 'admin');
        }
        if (is_null($port) && !empty($parseerror)) {
            $parseerror = get_string('curlblacklisturlparseporterror', 'admin');
        }

        // URL parsing problems, treat as blocked.
        if (!empty($parseerror)) {
            $this->error = get_string('curlblacklisturlblocked', 'admin');
            return false;
        }

        // Parsing was successful.
        $this->parsedips = $ips;
        $this->parsedport = $port;
        return true;
    }
}