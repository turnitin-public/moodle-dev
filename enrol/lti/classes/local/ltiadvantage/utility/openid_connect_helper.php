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

namespace enrol_lti\local\ltiadvantage\utility;

/**
 * Utility class for LTI Advantage OIDC launches.
 *
 * @package    enrol_lti
 * @copyright  2024 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class openid_connect_helper {

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
     * @return void
     */
    public static function set_partitioned_sesscookie(): void {
        // Chrome: find the 'Set-Cookie: MoodleSession=' header and add the partitioned attribute if required.
        // Note: This is ugly since PHP doesn't support this attribute yet: https://github.com/php/php-src/issues/12646
        global $CFG;
        $sessheader = array_filter(headers_list(), function($val) use ($CFG) {
            return str_contains($val, 'MoodleSession'.$CFG->sessioncookie)
                && !str_contains(strtolower($val), 'partitioned');
        });
        if (!empty($sessheader) ) {
            header_remove('Set-Cookie');
            header(reset($sessheader) . '; Partitioned;');
        }
        // TODO make sure there is only one 'Set-Cookie' header before wiping headers. If there are 2, we'll wipe them
        //  both and that's a bug waiting to happen. If 2, we need to make sure to add back any other wiped headers.
    }
}
