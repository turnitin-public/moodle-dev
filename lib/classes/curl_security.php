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
 * Contains a class providing functions used to check the host/port black/whitelists for curl.
 *
 * @since 3.2.0
 * @package   core
 * @copyright 2016 Jake Dallimore
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jake Dallimore <jrhdallimore@gmail.com>
 */

namespace core;
use \core\ip_utils;

defined('MOODLE_INTERNAL') || exit();

/**
 * Host and port checking for curl.
 *
 * This class provides a means to check URL/host/port against the system-level cURL security entries.
 * It does not provide a means to add URLs, hosts or ports to the black/white lists; this is configured manually
 * via the site admin section of Moodle (See: 'Site admin' > 'Security' > 'HTTP Security').
 *
 * This class is currently used by the 'curl' wrapper class in lib/filelib.php.
 * Depends on:
 *  \core\ip_utils (several functions)
 *  /typo3/class.t3lib_div.php (cmpFQDN function).
 *
 * @since 3.2.0
 * @package   core
 * @copyright 2016 Jake Dallimore
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jake Dallimore <jrhdallimore@gmail.com>
 */
class curl_security {
    /**
     * @var array of supported transport schemes and their respective default ports.
     */
    protected static $transportschemes = [
        'http' => 80,
        'https' => 443
    ];

    /**
     * Checks whether the given URL is blacklisted by checking its address and port number against the black/white lists.
     * The behaviour of this function can be classified as strict, as it returns true for URLs which are invalid or
     * could not be parsed, as well as those valid URLs which were found in the blacklist.
     *
     * @since 3.2.0
     * @param string $url the URL to check.
     * @return bool true if the URL is blacklisted or invalid and false if the URL is not blacklisted.
     */
    public static function url_is_blacklisted($url) {
        // If no config data is present, then all hosts/ports are allowed.
        if (!self::is_enabled()) {
            return false;
        }

        // Try to parse the URL to get the 'host' and 'port' components.
        $bits = self::parse_url($url);
        if (!empty($bits) && count($bits) == 2) {
            // Check the host and port against the blacklist settings.
            if (self::host_is_blocked($bits[0]) || self::port_is_blocked($bits[1])) {
                return true;
            }
            return false;
        }
        return true;
    }

    /**
     * Checks whether the given fully qualified domain name is blocked.
     * The method logic is as follows:
     * 1. Check the 'host' component against the list of domain names and wildcard domain names.
     *  - This will perform a DNS reverse lookup if required.
     * 2. Check the 'host component against the list of IPv4/IPv6 addresses and ranges.
     *  - This will perform a DNS forward lookup if required.
     *
     * @since 3.2.0
     * @param string $host the 'host' component of the URL to check against the blacklist.
     * @return bool true if the host is both valid and blocked, false otherwise.
     */
    public static function host_is_blocked($host) {
        global $CFG;
        if (empty($host) || !is_string($host)) {
            return false;
        }

        // Get the blocked hosts by category.
        $blockedhosts = self::get_blacklisted_hosts_by_category();

        // Fix for square brackets in the 'host' portion of the URL (only occurs if an IPv6 address is specified).
        $host = str_replace(array('[', ']'), '', $host); // RFC3986, section 3.2.2.

        // Regardless of whether the 'host' component contains an IP or a FQDN, check it against the blacklisted domain names.
        // Note: cmpFQDN() performs a DNS reverse lookup, if required, providing support for IPv4 and IPv6 address inputs here.
        $domainhostsblocked = array_merge($blockedhosts['wildcard'], $blockedhosts['domain']);
        if (count($domainhostsblocked) > 0) {
            require_once($CFG->libdir.'/typo3/class.t3lib_div.php');
            if (\t3lib_div::cmpFQDN($host, implode(',', $domainhostsblocked))) {
                return true;
            }
        }

        // DNS forward lookup (resolve the FQDN to the first corresponding IP address).
        if (ip_utils::is_domain_name($host)) {
            $host = gethostbyname($host);
        }

        // The site may not have any blacklisted domains (may use IP instead), so check blacklisted IP/ranges too.
        if (ip_utils::is_ipv4_address($host) || ip_utils::is_ipv6_address($host)) {
            $iphostsblocked = array_merge($blockedhosts['ipv4'], $blockedhosts['ipv6']);
            foreach ($iphostsblocked as $subnet) {
                if (address_in_subnet($host, $subnet)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Checks whether the given port is blocked, as determined by its absence on the ports whitelist.
     * Ports are assumed to be blocked unless found in the whitelist.
     *
     * @since 3.2.0
     * @param integer|string $port the port to check against the ports whitelist.
     * @return bool true if the port is blocked, false otherwise.
     */
    public static function port_is_blocked($port) {
        $portnum = intval($port);
        // Intentionally block port 0 as the use of it is considered bad practice.
        if (empty($port) || (string)$portnum !== (string)$port) {
            return true;
        }

        $allowedports = self::get_whitelisted_ports();
        if (empty($allowedports)) {
            return false;
        }
        return !in_array($portnum, $allowedports);
    }

    /**
     * Returns the string error for a blocked URL.
     *
     * @since 3.2.0
     * @return string the string error.
     */
    public static function get_blocked_url_string() {
        return get_string('curlsecurityurlblocked', 'admin');
    }

    /**
     * Convenience method to check whether we have any entries in the host blacklist or ports whitelist admin settings.
     * If no entries are found at all, the assumption is that the blacklist is disabled entirely.
     *
     * @since 3.2.0
     * @return bool true if one or more entries exist, false otherwise.
     */
    public static function is_enabled() {
        return (!empty(self::get_whitelisted_ports()) || !empty(self::get_blacklisted_hosts()));
    }

    /**
     * Helper to get all entries from the admin setting, as an array, sorted by classification.
     * Classifications include 'ipv4', 'ipv6', 'domain', 'wildcard'.
     *
     * @since 3.2.0
     * @return array of host/domain/ip entries from the 'curlsecurityblockedhosts' config.
     */
    protected static function get_blacklisted_hosts_by_category() {
        // For each of the admin setting entries, check and place in the correct section of the config array.
        $config=  ['ipv6' => [], 'ipv4' => [], 'domain' => [], 'wildcard' => []];
        $entries = self::get_blacklisted_hosts();
        foreach ($entries as $entry) {
            if (ip_utils::is_ipv6_address($entry) || ip_utils::is_ipv6_range($entry)) {
                $config['ipv6'][] = $entry;
            } else if (ip_utils::is_ipv4_address($entry) || ip_utils::is_ipv4_range($entry)) {
                $config['ipv4'][] = $entry;
            } else if (ip_utils::is_domain_name($entry)) {
                $config['domain'][] = $entry;
            } else if (ip_utils::is_wildcard_domain_name($entry)) {
                $config['wildcard'][] = $entry;
            }
        }
        return $config;
    }

    /**
     * Helper that return the whitelisted ports, as defined in the 'curlsecurityallowedport' setting.
     *
     * @since 3.2.0
     * @return array the array of whitelisted ports.
     */
    protected static function get_whitelisted_ports() {
        global $CFG;
        return array_filter(explode("\n", $CFG->curlsecurityallowedport), function($entry) {
            return !empty($entry);
        });
    }

    /**
     * Helper that return the blacklisted hosts, as defined in the 'curlsecurityblockedhosts' setting.
     *
     * @return array the array of blacklisted host entries.
     */
    protected static function get_blacklisted_hosts() {
        global $CFG;
        return array_filter(explode("\n", $CFG->curlsecurityblockedhosts), function($entry) {
            return !empty($entry);
        });
    }

    /**
     * Try to parse a URL to determine the 'host' and 'port' components, as defined in RFC3986.
     * If the URL is malformed or if PHP cannot parse it, this function will return false, indicating a parsing failure.
     *
     * @since 3.2.0
     * @param string $url the URL to be parsed.
     * @return array|bool list of host and port components on successful parsing; boolean false otherwise.
     */
    protected static function parse_url($url) {
        // Let's check the URL syntax first.
        $url = clean_param($url, PARAM_URL);
        if (empty($url)) {
            return false;
        }

        // To parse the URL, we first need a protocol. If we don't have one, default to http://.
        if (strpos($url, "://") === false && substr($url, 0, 1) != "/") {
            $url = "http://" . $url;
        }

        // Try to parse the URL. Note, there are some URLs which pass validation (clean_param) but which PHP cannot parse.
        // E.g. http://localhost:0/text.txt.
        if (($parsedinfo  = parse_url($url)) === false) {
            return false;
        }

        // URL was parsed by php. Now try to isolate the 'host' and 'port' components, making inferences where necessary.
        $host = $parsedinfo['host'];
        $port = null; // Port will be empty unless explicitly set in the $url, so we need to check this below.

        // Try to retrieve/infer the port number.
        if (!empty($parsedinfo['port'])) {
            $port = $parsedinfo['port'];
        } else if (!empty($parsedinfo['scheme'])) {
            // Otherwise, try to infer the port from the transport schemes array.
            if (isset(self::$transportschemes[$parsedinfo['scheme']])) {
                $port = self::$transportschemes[$parsedinfo['scheme']];
            }
        }

        // Return false if the URL could not be properly parsed into the 'host' and 'port' components.
        if (empty($host) || is_null($port)) {
            return false;
        }

        // Parsing was successful, so return the values.
        return [$host, $port];
    }
}
