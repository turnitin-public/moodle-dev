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
 * Contains a simple class providing some useful internet protocol-related functions.
 *
 * @since 3.2.0
 * @package   core
 * @copyright 2016 Jake Dallimore
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jake Dallimore <jrhdallimore@gmail.com>
 */

namespace core;

defined('MOODLE_INTERNAL') || exit();

/**
 * Static helper class providing some useful IP-related functions.
 *
 * @since 3.2.0
 * @package   core
 * @copyright 2016 Jake Dallimore
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jake Dallimore <jrhdallimore@gmail.com>
 */
final class ip_utils {
    /**
     * Syntax checking for domain names, including fully qualified domain names (host name included).
     * This function does not verify the existence of the domain name. It only verifies syntactic correctness.
     * This is loosely based on RFC1034/1035, however, due to relaxed TLD restrictions, it should not be considered fully compliant.
     *
     * @param string $domainname the input string to check.
     * @return bool true if the string has valid syntax, false otherwise.
     */
    public static function is_domain_name($domainname) {
        // The entire name cannot exceed 253 characters.
        if (strlen($domainname) > 253) {
            return false;
        }
        // Tertiary domain labels can have 63 octets max, and must not have begin or end with a hyphen. Number = unlimited.
        // The TLD label cannot begin with a number, but otherwise, is only loosely restricted (as per lib/validateurlsyntax.php).
        $alphanum    = '[a-zA-Z0-9]';
        $domain_tertiary   = '(' . $alphanum . '(([a-zA-Z0-9-]{0,61})' . $alphanum . ')?\.)*';
        $domain_toplevel   = '([a-zA-Z](([a-zA-Z0-9-]*)[a-zA-Z0-9])?)';
        $address       = '(' . $domain_tertiary .  $domain_toplevel . ')';
        $regexp = '#^' . $address . '$#i'; // Case insensitive matching.
        if (preg_match($regexp, $domainname, $match)) {
            return true;
        }
        return false;
    }

    /**
     * Syntax checker for wildcard domain names.
     * This function only confirms syntactic correctness. It does not check for the existence of the domain/subdomains.
     *
     * @param string $domainname the string to check.
     * @return bool true if the input string is a valid wildcard domain name, false otherwise.
     */
    public static function is_wildcard_domain_name($domainname) {
        // The entire name cannot exceed 253 characters.
        if (strlen($domainname) > 253) {
            return false;
        }
        // A wildcard domain name must have a left-positioned wildcard symbol (*) to be considered valid.
        // Tertiary domain labels can have 63 chars max, and must not have begin or end with a hyphen. Number = unlimited.
        // The TLD label cannot begin with a number, but otherwise, is only loosely restricted (as per lib/validateurlsyntax.php).
        $alphanum    = '[a-zA-Z0-9]';
        $domain_wildcard = '((\*)\.){1}';
        $domain_tertiary   = '(' . $alphanum . '(([a-zA-Z0-9-]{0,61})' . $alphanum . ')?\.)*';
        $domain_toplevel   = '([a-zA-Z](([a-zA-Z0-9-]*)[a-zA-Z0-9])?)';
        $address       = '(' . $domain_wildcard . $domain_tertiary .  $domain_toplevel . ')';
        $regexp = '#^' . $address . '$#i'; // Case insensitive matching.
        if (preg_match($regexp, $domainname, $match)) {
            return true;
        }
        return false;
    }

    /**
     * Syntax validation for IPv4 addresses.
     *
     * @param string $address the address to check.
     * @return bool true if the address is a valid IPv4 address or range, false otherwise.
     */
    public static function is_ipv4_address($address) {
        return (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false);
    }

    /**
     * Syntax checking for IPv4 address ranges.
     * Supports CIDR notation and last-group ranges.
     * Eg. 127.0.0.0/24 or 127.0.0.80-255
     *
     * @param string $addressrange the address range to check.
     * @return bool true if the string is a valid range representation, false otherwise.
     */
    public static function is_ipv4_range($addressrange) {
        // Address range in CIDR notation.
        if (preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\/(\d{1,2})$#', $addressrange, $match)) {
            // Check the dotted quad numerical limits.
            if ($match[1] > 255 || $match[1] < 0  || $match[2] > 255 || $match[2] < 0
                || $match[3] > 255 || $match[3] < 0 || $match[4] > 255 || $match[4] < 0) {
                return false;
            }

            // And check the range numerical limit.
            if ($match[5] > 32) {
                return false;
            }
            return true;
        }

        // Address range in last-group range notation.
        if (preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})-(\d{1,3})$#', $addressrange, $match)) {
            // The address part component must be valid.
            $address = "{$match[1]}.{$match[2]}.{$match[3]}.{$match[4]}";
            if (!self::is_ipv4_address($address)) {
                return false;
            }

            // Check the range portion of the string.
            if ($match[5] > 255 || $match[5] < $match[4]) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Syntax validation for IPv6 addresses.
     * This function does not check whether the address is assigned, only its syntactical correctness.
     *
     * @param string $address the address to check.
     * @return bool true if the address has correct syntax, false otherwise.
     */
    public static function is_ipv6_address($address) {
        return (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !==  false);
    }

    /**
     * Syntax validation for IPv6 address ranges.
     * Supports CIDR notation and last-group ranges.
     * Eg. fe80::d98c/64 or fe80::d98c-ffee
     *
     * @param string $addressrange the IPv6 address range to check.
     * @return bool true if the string is a valid range representation, false otherwise.
     */
    public static function is_ipv6_range($addressrange) {
        // Check address range in CIDR notation.
        $ipv6parts = explode('/', $addressrange);
        if (count($ipv6parts) == 2) {
            // Check the address component.
            if (!self::is_ipv6_address($ipv6parts[0])) {
                return false;
            }

            // Address component is ok, so check the range component of the address, making sure the int cast was correct.
            $range = (int)$ipv6parts[1];
            if ((string)$range !== $ipv6parts[1] || $range < 0 || $range > 128) {
                // Either the int conversion was bad, or the range isn't valid, so return.
                return false;
            }
            return true;
        }

        // Check last-group ranges.
        $ipv6parts = explode('-', $addressrange);
        if (count($ipv6parts) == 2) {
            // Check the address component.
            if (!self::is_ipv6_address($ipv6parts[0])) {
                return false;
            }

            // Check the range component.
            $addressparts = explode(':', $ipv6parts[0]);
            $rangestart = $addressparts[count($addressparts)-1];
            $rangeend = $ipv6parts[1];

            // Range limits must be hex and must not exceed 4 characters.
            if (!ctype_xdigit($rangestart) || !ctype_xdigit($rangeend) || strlen($rangeend) > 4 || strlen($rangestart) > 4) {
                return false;
            }

            // Ensure the range is valid.
            $rangestartdec = hexdec($rangestart);
            $rangeenddec = hexdec($rangeend);
            if ($rangeenddec < $rangestartdec) {
                return false;
            }
            return true;
        }
        return false;
    }
}
