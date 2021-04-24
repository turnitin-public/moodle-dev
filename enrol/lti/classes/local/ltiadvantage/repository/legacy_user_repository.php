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

namespace enrol_lti\local\ltiadvantage\repository;

use enrol_lti\helper;

/**
 * The legacy_user_repository class, instances of which are responsible for querying LTI 1.1/2.0 users.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class legacy_user_repository {

    /**
     * Get a user record by its legacy consumerkey/id identifiers.
     *
     * @param string $legacyconsumerkey the consumer key of the legacy tool deployment to which this user belongs.
     * @param string $legacyuserid the legacy id of the user on the platform.
     * @return \stdClass|null the user record, if found, null otherwise.
     */
    public function find_by_consumer(string $legacyconsumerkey, string $legacyuserid): ?\stdClass {
        $legacyusername = helper::create_username($legacyconsumerkey, $legacyuserid);
        return \core_user::get_user_by_username($legacyusername) ?: null;
    }
}
