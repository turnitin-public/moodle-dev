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
 * Contains the user entity class.
 *
 * @package    enrol_lti
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\local\ltiadvantage\entity;

/**
 * Class user, instances of which represent a specific lti user in the tool.
 *
 * A user is always associated with a resource, as lti users cannot exist without a tool-published-resource. A user will
 * always come from either:
 * - a resource link launch or
 * - a membership sync
 * Both of which required a published resource.
 *
 * Additionally, a user may be associated with a given resource_link instance, to signify that the user originated from
 * that resource_link. If a user is not associated with a resource_link, such as when creating a user during a member
 * sync, that param is nullable. This can be achieved via the factory method user::create_from_resource_link() or set
 * after instantiation via the user::set_resource_link_id() method.
 *
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later */
class user {

    /** @var int the id of the published resource to which this user belongs. */
    private $resourceid;

    /** @var int the local id of the deployment instance to which this user belongs. */
    private $deploymentid;

    /** @var string the id of the user in the platform site. */
    private $sourceid;

    /** @var int|null the id of this user instance, or null if not stored yet. */
    private $id;

    /** @var int|null the id of the user in the tool site, or null if the instance hasn't been stored yet. */
    private $localid;

    /** @var string user first name. */
    private $firstname;

    /** @var string user last name. */
    private $lastname;

    /** @var string username of the user. */
    private $username;

    /** @var string email address of the user. */
    private $email;

    /** @var string city of the user. */
    private $city;

    /** @var string country of the user. */
    private $country;

    /** @var string institution of the user.*/
    private $institution;

    /** @var string timezone of the user. */
    private $timezone;

    /** @var int maildisplay of the user. */
    private $maildisplay;

    /** @var string language code of the user. */
    private $lang;

    /** @var string auth type of the user. */
    private $auth;

    /** @var mnethostid of the user. */
    private $mnethostid;

    /** @var int Whether the user is confirmed or not. */
    private $confirmed;

    /** @var float|int the user's last grade value. */
    private $lastgrade;

    /** @var int|null the user's last access unix timestamp, or null if they have not accessed the resource yet.*/
    private $lastaccess;

    /** @var int|null the id of the resource_link instance, or null if the user doesn't originate from one. */
    private $resourcelinkid;

    /**
     * Private constructor.
     *
     * @param int $resourceid the id of the published resource to which this user belongs.
     * @param int $deploymentid the local id of the deployment instance to which this user belongs.
     * @param string $sourceid the id of the user in the platform site.
     * @param string $firstname user first name.
     * @param string $lastname user last name.
     * @param string $username the user's username.
     * @param string $email the user's email.
     * @param string $lang the user's language code.
     * @param string $city the user's city.
     * @param string $country the user's country.
     * @param string $institution the user's institution.
     * @param string $timezone the user's timezone.
     * @param int|null $maildisplay the user's maildisplay, or null to select defaults.
     * @param float|null $lastgrade the user's last grade value.
     * @param int|null $lastaccess the user's last access time, or null if they haven't accessed the resource.
     * @param int|null $resourcelinkid the id of the resource link to link to the user, or null if not applicable.
     * @param int|null $localid the local id of the user, or null if it's a not-yet-persisted object.
     * @param int|null $id the id of this object instance, or null if it's a not-yet-persisted object.
     */
    private function __construct(int $resourceid, int $deploymentid, string $sourceid, string $firstname,
            string $lastname, string $username, string $email, string $lang, string $city, string $country,
            string $institution, string $timezone, ?int $maildisplay, ?float $lastgrade, ?int $lastaccess,
            ?int $resourcelinkid = null, ?int $localid = null, ?int $id = null) {

        global $CFG;
        $this->resourceid = $resourceid;
        $this->deploymentid = $deploymentid;
        $this->sourceid = $sourceid;
        $this->firstname = $firstname;
        $this->lastname = $lastname;
        $this->username = $username;
        $this->email = $email;
        $this->email = \core_user::clean_field($email, 'email');
        // If the email was stripped/not set then fill it with a default one.
        // This stops the user from being redirected to edit their profile page.
        $this->email = $this->email ?: $this->username . "@example.com";
        $this->lang = $lang;
        $this->city = $city;
        $this->country = $country;
        $this->institution = $institution;
        $this->timezone = $timezone;
        $this->resourcelinkid = $resourcelinkid;
        $this->localid = $localid;
        $this->id = $id;
        $this->mnethostid = $CFG->mnet_localhost_id;
        $this->confirmed = 1;
        $this->auth = 'lti';
        if (is_null($maildisplay)) {
            if (isset($CFG->defaultpreference_maildisplay)) {
                $this->maildisplay = $CFG->defaultpreference_maildisplay;
            } else {
                $this->maildisplay = 2;
            }
        } else {
            $this->maildisplay = $maildisplay;
        }
        $this->lastgrade = $lastgrade ?? 0;
        $this->lastaccess = $lastaccess;
    }

    /**
     * Factory method for creating a user instance associated with a given resource_link instance.
     *
     * @param int $resourcelinkid the local id of the resource link instance to link to the user.
     * @param int $resourceid the id of the published resource to which this user belongs.
     * @param int $deploymentid the local id of the deployment instance to which this user belongs.
     * @param string $sourceid the id of the user in the platform site.
     * @param string $firstname user first name.
     * @param string $lastname user last name.
     * @param string $username the user's username.
     * @param string $lang the user's language code.
     * @param string $email the user's email.
     * @param string $city the user's city.
     * @param string $country the user's country.
     * @param string $institution the user's institution.
     * @param string $timezone the user's timezone.
     * @param int|null $maildisplay the user's maildisplay, or null to select defaults.
     * @param float|null $lastgrade the user's last grade value.
     * @param int|null $lastaccess the user's last access time, or null if they haven't accessed the resource.
     * @param int|null $localid the local id of the user, or null if it's a not-yet-persisted object.
     * @param int|null $id the id of this lti user instance, or null if it's a not-yet-persisted object.
     * @return user the user instance.
     */
    public static function create_from_resource_link(int $resourcelinkid, int $resourceid, int $deploymentid,
            string $sourceid, string $firstname, string $lastname, string $username, string $lang, string $email = '',
            string $city = '', string $country = '', string $institution = '', string $timezone = '',
            ?int $maildisplay = null, ?float $lastgrade = null, ?int $lastaccess = null, ?int $localid = null,
            int $id = null): user {

        return new self($resourceid, $deploymentid, $sourceid, $firstname, $lastname, $username, $email, $lang, $city,
            $country, $institution, $timezone, $maildisplay, $lastgrade, $lastaccess, $resourcelinkid, $localid, $id);
    }

    /**
     * Factory method for creating a user without a resource_link association.
     *
     * @param int $resourceid the id of the published resource to which this user belongs.
     * @param int $deploymentid the local id of the deployment instance to which this user belongs.
     * @param string $sourceid the id of the user in the platform site.
     * @param string $firstname user first name.
     * @param string $lastname user last name.
     * @param string $username the user's username.
     * @param string $lang the user's language code.
     * @param string $email the user's email.
     * @param string $city the user's city.
     * @param string $country the user's country.
     * @param string $institution the user's institution.
     * @param string $timezone the user's timezone.
     * @param int|null $maildisplay the user's maildisplay, or null to select defaults.
     * @param float|null $lastgrade the user's last grade value.
     * @param int|null $lastaccess the user's last access time, or null if they haven't accessed the resource.
     * @param int|null $localid the local id of the user, or null if it's a not-yet-persisted object.
     * @param int|null $id the id of this lti user instance, or null if it's a not-yet-persisted object.
     * @return user the user instance.
     */
    public static function create(int $resourceid, int $deploymentid, string $sourceid, string $firstname,
            string $lastname, string $username, string $lang, string $email = '', string $city = '',
            string $country = '', string $institution = '', string $timezone = '', ?int $maildisplay = null,
            ?float $lastgrade = null, ?int $lastaccess = null, ?int $localid = null, int $id = null): user {

        return new self($resourceid, $deploymentid, $sourceid, $firstname, $lastname, $username, $email, $lang, $city,
            $country, $institution, $timezone, $maildisplay, $lastgrade, $lastaccess, null, $localid, $id);
    }

    /**
     * Get the id of this user instance.
     *
     * @return int|null the object id, or null if not yet persisted.
     */
    public function get_id(): ?int {
        return $this->id;
    }

    /**
     * Get the id of the resource_link instance to which this user is associated.
     *
     * @return int|null the object id, or null if the user isn't associated with one.
     */
    public function get_resourcelinkid(): ?int {
        return $this->resourcelinkid;
    }

    /**
     * Associate this user with the given resource_link instance, denoting that this user launched from the instance.
     *
     * @param int $resourcelinkid the id of the resource_link instance.
     */
    public function set_resourcelinkid(int $resourcelinkid): void {
        $this->resourcelinkid = $resourcelinkid;
    }

    /**
     * Get the id of the published resource to which this user is associated.
     *
     * @return int the resource id.
     */
    public function get_resourceid(): int {
        return $this->resourceid;
    }

    /**
     * Get the id of the deployment instance to which this user belongs.
     *
     * @return int id of the deployment instance.
     */
    public function get_deploymentid(): int {
        return $this->deploymentid;
    }

    /**
     * Get the id of the user in the platform.
     *
     * @return string the source id.
     */
    public function get_sourceid(): string {
        return $this->sourceid;
    }

    /**
     * Get the id of the user in the tool.
     *
     * @return int|null the id, or null if the object instance hasn't yet been persisted.
     */
    public function get_localid(): ?int {
        return $this->localid;
    }

    /**
     * Get the firstname of the user.
     *
     * @return string the user's first name
     */
    public function get_firstname(): string {
        return $this->firstname;
    }

    /**
     * Set the first name of the user.
     *
     * @param string $firstname the new first name.
     */
    public function set_firstname(string $firstname): void {
        $this->firstname = $firstname;
    }

    /**
     * Get the last name of the user.
     *
     * @return string the user's last name.
     */
    public function get_lastname(): string {
        return $this->lastname;
    }

    /**
     * Sets the last name of the user.
     *
     * @param string $lastname the new last name.
     */
    public function set_lastname(string $lastname): void {
        $this->lastname = $lastname;
    }

    /**
     * Get the username of this user.
     *
     * @return string the username.
     */
    public function get_username(): string {
        return $this->username;
    }

    /**
     * Get the email of this user.
     *
     * @return string the email address.
     */
    public function get_email(): string {
        return $this->email;
    }

    /**
     * Get the user's city.
     *
     * @return string the city.
     */
    public function get_city(): string {
        return $this->city;
    }

    /**
     * Get the user's country code.
     *
     * @return string the country code.
     */
    public function get_country(): string {
        return $this->country;
    }

    /**
     * Get the instituation of the user.
     *
     * @return string the institution.
     */
    public function get_institution(): string {
        return $this->institution;
    }

    /**
     * Get the timezone of the user.
     *
     * @return string the user timezone.
     */
    public function get_timezone(): string {
        return $this->timezone;
    }

    /**
     * Get the maildisplay of the user.
     *
     * @return int the maildisplay.
     */
    public function get_maildisplay(): int {
        return $this->maildisplay;
    }

    /**
     * Get the lang code of the user.
     *
     * @return string the user's language code.
     */
    public function get_lang(): string {
        return $this->lang;
    }

    /**
     * Get the auth plugin for the user.
     *
     * @return string the auth plugin.
     */
    public function get_auth(): string {
        return $this->auth;
    }

    /**
     * Get the mnethostid for this user.
     *
     * @return mixed mnnethostid of the user.
     */
    public function get_mnethostid() {
        return $this->mnethostid;
    }

    /**
     * Get whether this user is confirmed or not.
     *
     * @return int 1 for confirmed user, 0 for non-confirmed.
     */
    public function get_confirmed(): int {
        return $this->confirmed;
    }

    /**
     * Get the last grade value for this user.
     *
     * @return float|null the float grade, or null if never graded.
     */
    public function get_lastgrade(): ?float {
        return $this->lastgrade;
    }

    /**
     * Set the last grade for the user.
     *
     * @param float $lastgrade the last grade the user received.
     */
    public function set_lastgrade(float $lastgrade): void {
        $this->lastgrade = $lastgrade;
    }

    /**
     * Get the last access timestamp for this user.
     *
     * @return int|null the last access timestampt, or null if the user hasn't accessed the resource.
     */
    public function get_lastaccess(): ?int {
        return $this->lastaccess;
    }

    /**
     * Set the last access time for the user.
     *
     * @param int $time unix timestamp representing the last time the user accessed the published resource.
     * @throws \coding_exception if $time is not a positive int.
     */
    public function set_lastaccess(int $time): void {
        if ($time < 0) {
            throw new \coding_exception('Cannot set negative access time');
        }
        $this->lastaccess = $time;
    }
}
