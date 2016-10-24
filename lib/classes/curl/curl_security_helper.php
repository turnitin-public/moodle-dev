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
 * @package   core
 * @copyright 2016 Jake Dallimore
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jake Dallimore <jrhdallimore@gmail.com>
 */

namespace core\curl;
use core\ip_utils;

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
 *  core\ip_utils (several functions)
 *  moodlelib (clean_param)
 *
 * @package   core
 * @copyright 2016 Jake Dallimore
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jake Dallimore <jrhdallimore@gmail.com>
 */
final class curl_security_helper {
    /**
     * @var array of supported transport schemes and their respective default ports.
     */
    private static $transportschemes = [
        'http' => 80,
        'https' => 443
    ];

    /**
     * Checks whether the given URL is blacklisted by checking its address and port number against the black/white lists.
     * The behaviour of this function can be classified as strict, as it returns true for URLs which are invalid or
     * could not be parsed, as well as those valid URLs which were found in the blacklist.
     *
     * @param string $url the URL to check.
     * @return bool true if the URL is blacklisted or invalid and false if the URL is not blacklisted.
     */
    public static function url_is_blacklisted($url) {
        // If no config data is present, then all hosts/ports are allowed.
        if (!self::security_enabled()) {
            return false;
        }

        // Try to parse the URL to get the 'host' and 'port' components.
        $parsed = self::url_parse($url);

        // The port will be empty unless explicitly set in the $url (uncommon), so try to infer it from the supported schemes.
        if (!$parsed['port'] && $parsed['scheme'] && isset(self::$transportschemes[$parsed['scheme']])) {
            $parsed['port'] = self::$transportschemes[$parsed['scheme']];
        }

        if ($parsed['port'] && $parsed['host']) {
            // Check the host and port against the blacklist/whitelist entries.
            return self::host_is_blocked($parsed['host']) || self::port_is_blocked($parsed['port']);
        }
        return true;
    }

    /**
     * Checks whether the host portion of a url is blocked.
     * The host portion may be a FQDN, IPv4 address or a IPv6 address wrapped in square brackets, as per standard URL notation.
     * E.g.
     *     images.example.com
     *     127.0.0.1
     *     [0.0.0.0.0.0.0.1]
     * The method logic is as follows:
     * 1. Check the host component against the list of IPv4/IPv6 addresses and ranges.
     *  - This will perform a DNS forward lookup if required.
     * 2. Check the host component against the list of domain names and wildcard domain names.
     *  - This will perform a DNS reverse lookup if required.
     *
     * @param string $host the host component of the URL to check against the blacklist.
     * @return bool true if the host is both valid and blocked, false otherwise.
     */
    public static function host_is_blocked($host) {
        if (empty($host) || !is_string($host)) {
            return false;
        }

        // Fix for square brackets in the 'host' portion of the URL (only occurs if an IPv6 address is specified).
        $host = str_replace(array('[', ']'), '', $host); // RFC3986, section 3.2.2.
        $blacklistedhosts = self::get_blacklisted_hosts_by_category();

        if (ip_utils::is_ipv4_address($host) || ip_utils::is_ipv6_address($host)) {
            if (self::address_explicitly_blocked($host)) {
                return true;
            }

            // Only perform a reverse lookup if there is a point to it (i.e. we have rules to check against).
            if ($blacklistedhosts['domain'] || $blacklistedhosts['wildcard']) {
                $hostname = gethostbyaddr($host); // DNS reverse lookup - supports both IPv4 and IPv6 address formats.
                if ($hostname !== $host && self::host_explicitly_blocked($hostname)) {
                    return true;
                }
            }
        } else if (ip_utils::is_domain_name($host)) {
            if (self::host_explicitly_blocked($host)) {
                return true;
            }

            // Only perform a forward lookup if there are IP rules to check against.
            if ($blacklistedhosts['ipv4'] || $blacklistedhosts['ipv6']) {
                $hostip = gethostbyname($host); // DNS forward lookup - only returns IPv4 addresses!
                if ($hostip !== $host && self::address_explicitly_blocked($hostip)) {
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
        return !empty($allowedports) && !in_array($portnum, $allowedports);
    }

    /**
     * Returns the string error for a blocked URL.
     *
     * @return string the string error.
     */
    public static function get_blocked_url_string() {
        return get_string('curlsecurityurlblocked', 'admin');
    }

    /**
     * Convenience method to check whether we have any entries in the host blacklist or ports whitelist admin settings.
     * If no entries are found at all, the assumption is that the blacklist is disabled entirely.
     *
     * @return bool true if one or more entries exist, false otherwise.
     */
    public static function security_enabled() {
        return (!empty(self::get_whitelisted_ports()) || !empty(self::get_blacklisted_hosts()));
    }

    /**
     * Checks whether the input address is blocked by at any of the IPv4 or IPv6 address rules.
     *
     * @param string $addr the ip address to check.
     * @return bool true if the address is covered by an entry in the blacklist, false otherwise.
     */
    private static function address_explicitly_blocked($addr) {
        $blockedhosts = self::get_blacklisted_hosts_by_category();
        $iphostsblocked = array_merge($blockedhosts['ipv4'], $blockedhosts['ipv6']);
        return address_in_subnet($addr, implode(',', $iphostsblocked));
    }

    /**
     * Checks whether the input hostname is blocked by any of the domain/wildcard rules.
     *
     * @param string $host the hostname to check
     * @return bool true if the host is covered by an entry in the blacklist, false otherwise.
     */
    private static function host_explicitly_blocked($host) {
        $blockedhosts = self::get_blacklisted_hosts_by_category();
        $domainhostsblocked = array_merge($blockedhosts['domain'], $blockedhosts['wildcard']);
        return ip_utils::is_domain_in_allowed_list($host, $domainhostsblocked);
    }

    /**
     * Helper to get all entries from the admin setting, as an array, sorted by classification.
     * Classifications include 'ipv4', 'ipv6', 'domain', 'wildcard'.
     *
     * @return array of host/domain/ip entries from the 'curlsecurityblockedhosts' config.
     */
    private static function get_blacklisted_hosts_by_category() {
        // For each of the admin setting entries, check and place in the correct section of the config array.
        return array_reduce(self::get_blacklisted_hosts(), function($carry, $entry) {
            return array_merge_recursive($carry, [
                'ipv6' => ip_utils::is_ipv6_address($entry) || ip_utils::is_ipv6_range($entry) ? $entry : [],
                'ipv4' => ip_utils::is_ipv4_address($entry) || ip_utils::is_ipv4_range($entry) ? $entry : [],
                'domain' => ip_utils::is_domain_name($entry) ? $entry : [],
                'wildcard' => ip_utils::is_wildcard_domain_name($entry) ? $entry : []
            ]);
        }, ['ipv4' => [], 'ipv6' => [], 'domain' => [], 'wildcard' => []]);
    }

    /**
     * Helper that returns the whitelisted ports, as defined in the 'curlsecurityallowedport' setting.
     *
     * @return array the array of whitelisted ports.
     */
    private static function get_whitelisted_ports() {
        global $CFG;
        return array_filter(explode("\n", $CFG->curlsecurityallowedport), function($entry) {
            return !empty($entry);
        });
    }

    /**
     * Helper that returns the blacklisted hosts, as defined in the 'curlsecurityblockedhosts' setting.
     *
     * @return array the array of blacklisted host entries.
     */
    private static function get_blacklisted_hosts() {
        global $CFG;
        return array_filter(array_map('trim', explode("\n", $CFG->curlsecurityblockedhosts)), function($entry) {
            return !empty($entry);
        });
    }

    /**
     * Try to parse a URL to determine it's components, as defined in RFC3986.
     * If the URL is malformed or if PHPs parse_url cannot parse it, this function will return an array with component keys only.
     * If the parsing is successful, then one or more of the array values may be set, depending on the url string provided.
     *
     * @param string $url the URL to be parsed.
     * @return array associative array containing the url components and, if successful, their values.
     */
    private static function url_parse($url) {
        // Make sure the URL is valid.
        $url = clean_param($url, PARAM_URL);
        $valid = !empty($url) && substr($url, 0, 1) != "/";

        // To properly parse the URL, PHP needs a transport scheme. If we don't have one, default to http://.
        // This a PHP bug: The host portion of the url (e.g. 'example.com') is returned as the 'path' if the scheme is omitted.
        $url = strpos($url, "://") === false ? 'http://' . $url : $url;

        // Try to parse the URL.
        $parsed  = parse_url($url);
        $defaults = ['scheme' => '', 'host' => '', 'port' => '', 'user' => '',
                     'pass' => '', 'path' => '', 'query' => '', 'fragment' => ''];
        return $valid && $parsed ? $parsed + $defaults : $defaults;
    }
}
