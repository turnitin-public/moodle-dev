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

namespace auth_lti\local\ltiadvantage\utility;

/**
 * Helper class providing utils dealing with cookies in LTI.
 *
 * This class primarily provides a means to augment outbound cookie headers, in order to satisfy browser-specific
 * requirements for setting 3rd party cookies.
 *
 * @package    auth_lti
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cookie_helper {

    /**
     * Ensures the Moodle session cookie can be partitioned by browsers supporting cookie partitioning.
     *
     * This ensures the session cookie is properly set during iframe launches when cookies are 3rd party and are subject
     * to browser-specific 3rd party cookie policy. Setting the cookie ensure session continuity during the auth flow.
     *
     * Currently, this method only adds a single cookie attribute to support cookie partitioning in Chrome.
     *
     * Cookie partitioning is:
     * - A browser mechanism allowing 3rd party cookies to be set.
     * - Only relevant when the launch takes place in an iframe and is thus subject to browser-specific 3rd party cookie
     * policy.
     * - Only supported by some browsers and supported in different ways. See browser support notes below.
     **
     * Browser support for cookie partitioning (as at Jan 2024):
     *  - Chrome requires the 'partitioned' attribute on the cookie to support partitioning via CHIPS.
     *   See https://developers.google.com/privacy-sandbox/3pcd/chips.
     *  - Firefox doesn't require a special cookie attribute to support partitioning via its 'Total cookie protection'.
     *  So, nothing is required here to support Firefox.
     *  - Safari/Webkit doesn't support partitioned cookies, so nothing can be done here. Safari requires the use of the
     *  storage access API which is resolved via a front end workflow.
     *
     * The importance of cookies during a launch:
     * Cookies are critical to the OIDC auth flow. The 'nonce' value required by the auth flow is stored in $SESSION and
     * must be available across requests. Without a persistent $SESSION, nonce cannot be retrieved (from $SESSION) and
     * subsequently validated against the value sent in the auth response (in the id_token JWT), meaning the auth
     * validation will fail.
     * See 'nonce' in https://openid.net/specs/openid-connect-core-1_0.html#AuthRequest
     * See also: https://openid.net/specs/openid-connect-core-1_0.html#NonceNotes
     * Note: In Moodle's implementation, the nonce value is stored in $SESSION (not a distinct cookie as mentioned in
     * the spec), but the implications are the same: the cookie that facilitates nonce retrieval must be present in all
     * requests during the auth flow.
     * @see \enrol_lti\local\ltiadvantage\lib\launch_cache_session for details on how the nonce is cached in $SESSION.
     *
     * @param array $cookienames array containing the names of the cookies to match.
     * @return void
     */
    public static function add_partitioning_to_cookies(array $cookienames): void {
        // Chrome: find the 'Set-Cookie: NAME=XX' header and add the partitioned attribute if not set already.
        // Note: This employs an ugly header()-based method since PHP doesn't support this attribute in cookie APIs yet.
        // See: https://github.com/php/php-src/issues/12646 for details on that limitation.
        $allsetcookieheaders = array_filter(headers_list(), function($val) {
            return preg_match("/Set-Cookie:/i", $val);
        });

        $updatedheaders = self::cookie_headers_add_attribute($allsetcookieheaders, $cookienames, 'Partitioned');

        // Note: The header_remove() method is quite crude and removes all headers of that header name.
        header_remove('Set-Cookie');
        foreach ($updatedheaders as $header) {
            header($header, false);
        }
    }

    /**
     * Convenience method for adding partitioning support to a single cookie instead of a list.
     *
     * @param string $cookiename the cookie name to match
     * @return void
     */
    public static function add_partitioning_to_cookie(string $cookiename): void {
        self::add_partitioning_to_cookies([$cookiename]);
    }

    /**
     * Given a list of HTTP header strings, return a list of HTTP header strings where the matched 'Set-Cookie' headers
     * have been updated with the attribute defined by $attribute.
     *
     * This method does not verify whether a given attribute is valid or not. It blindly sets it and returns the header
     * strings. It's up to calling code to determine whether an attribute makes sense or not.
     *
     * @param array $headerstrings the array of header strings.
     * @param array $cookiestomatch the array of cookie names to match.
     * @param string $attribute the attribute to set on each matched 'Set-Cookie' header.
     * @param bool $casesensitive whether to match the attribute in a case-sensitive way.
     * @return array the updated array of header strings.
     */
    public static function cookie_headers_add_attribute(array $headerstrings, array $cookiestomatch,
            string $attribute, bool $casesensitive = false): array {

        foreach ($headerstrings as $index => $headerstring) {
            foreach ($cookiestomatch as $cookiename)
                if (self::cookie_header_matches_name($headerstring, $cookiename)
                    && !self::cookie_header_contains_attribute($headerstring, $attribute, $casesensitive)) {

                    $headerstrings[$index] = self::cookie_header_append_attribute($headerstring, $attribute);
                }
        }

        return $headerstrings;
    }

    /**
     * Check whether the header string is a 'Set-Cookie' header for the cookie identified by $cookiename.
     *
     * @param string $headerstring the header string to check.
     * @param string $cookiename the name of the cookie to match.
     * @return bool true if the header string is a Set-Cookie header for the named cookie, false otherwise.
     */
    private static function cookie_header_matches_name(string $headerstring, string $cookiename): bool {
        // Generally match the format, but in a case-insensitive way so that 'set-cookie' and "SET-COOKIE" both valid.
        return preg_match("/Set-Cookie: *$cookiename=/i", $headerstring)
            // Case-sensitive match on cookiename, which is case-sensitive.
            && preg_match("/: *$cookiename=/", $headerstring);
    }

    /**
     * Check whether the header string contains the given attribute.
     *
     * @param string $headerstring the header string to check.
     * @param string $attribute the attribute to check for.
     * @param bool $casesensitive whether to perform a case-sensitive check.
     * @return bool true if the header contains the attribute, false otherwise.
     */
    private static function cookie_header_contains_attribute(string $headerstring, string $attribute,
            bool $casesensitive): bool {

        if ($casesensitive) {
            return str_contains($headerstring, $attribute);
        }
        return str_contains(strtolower($headerstring), strtolower($attribute));
    }

    /**
     * Append the given attribute to the header string.
     *
     * @param string $headerstring the header string to append to.
     * @param string $attribute the attribute to append.
     * @return string the updated header string.
     */
    private static function cookie_header_append_attribute(string $headerstring, string $attribute): string {
        $headerstring = rtrim($headerstring, ';'); // Sometimes set, but make sure we don't double up.
        return "$headerstring; $attribute;";
    }

}
