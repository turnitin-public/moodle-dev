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

namespace core\oauth2\registration;

/**
 * Models the client information present in a successful response to an OAuth2 Dynamic Client Registration request.
 *
 * This can be expanded in future to add support for RFC7592 (registration management) if needed.
 *
 * {@see https://www.rfc-editor.org/rfc/rfc7591#section-3.2.1}
 *
 * @package    core
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth2_client_registration_information {
    /**
     * Helper array mapping the spec field names to the instance var names.
     * @var string[]
     */
    private static array $fieldmap = [
        'client_id' => 'clientid',
        'client_id_issued_at' => 'clientidissuedat',
        'client_secret' => 'clientsecret',
        'client_secret_expires_at' => 'clientsecretexpiresat'
    ];

    /**
     * Constructor.
     *
     * @param string $clientid the client_id value assigned by the auth server.
     * @param oauth2_client_registration_metadata $metadata the metadata sent in the registration request which must be included.
     * @param int|null $clientidissuedat unix time at which the client_id was issued, or null if not relevant.
     * @param string|null $clientsecret the client_secret value, or null if not relevant.
     * @param int $clientsecretexpiresat unix time at which the client_secret expires, defaulting to 0 meaning no expiry time.
     */
    public function __construct(
        protected string $clientid,
        protected oauth2_client_registration_metadata $metadata,
        protected ?int $clientidissuedat = null,
        protected ?string $clientsecret = null,
            protected int $clientsecretexpiresat = 0) {
    }

    /**
     * Factory method for getting an instance based on array data.
     *
     * @param array $clientinfo the array of input data to create the response.
     * @return oauth2_client_registration_information an instance of this class.
     * @throws \moodle_exception if any required fields are missing.
     */
    public static function from_array(array $clientinfo): self {
        if (empty($clientinfo['client_id'])) {
            throw new \moodle_exception('Error: '. __METHOD__.': client_id is a required field.');
        }
        $init = [];
        foreach (self::$fieldmap as $fieldname => $localname) {
            if (isset($clientinfo[$fieldname])) {
                $init[$localname] = $clientinfo[$fieldname];
                unset($clientinfo[$fieldname]);
            }
        }
        $init['metadata'] = oauth2_client_registration_metadata::from_array($clientinfo);

        return new self(...$init);
    }

    /**
     * Export this instance, including all metadata, to an array.
     *
     * Any custom/extension fields in the metadata which are found to clash with client info fields like client_id, etc. are simply
     * discarded.
     *
     * @return array the array of registration client information.
     */
    public function to_array(): array {
        $clientinfo = $this->metadata->to_array();

        // Prevent metadata values overriding client identifiers and such.
        foreach (array_keys(self::$fieldmap) as $index) {
            unset($clientinfo[$index]);
        }

        $clientinfo['client_id'] = $this->clientid;
        if (!is_null($this->clientidissuedat)) {
            $clientinfo['client_id_issued_at'] = $this->clientidissuedat;
        }
        if (!empty($this->clientsecret)) {
            $clientinfo['client_secret'] = $this->clientsecret;
            $clientinfo['client_secret_expires_at'] = $this->clientsecretexpiresat;
        }

        return $clientinfo;
    }

    public function get_client_id(): string {
        return $this->clientid;
    }

    public function get_client_secret(): ?string {
        return $this->clientsecret;
    }

    public function get_client_id_issued_at(): ?int {
        return $this->clientidissuedat;
    }

    public function get_client_secret_expires_at(): ?int {
        return $this->clientsecretexpiresat;
    }

    public function get_metadata(): oauth2_client_registration_metadata {
        return $this->metadata;
    }
}
