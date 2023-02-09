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
 * Representation of the request/response metadata in an OAuth 2 Dynamic Client Registration request/response..
 *
 * {@see https://www.rfc-editor.org/rfc/rfc7591#section-2}
 *
 * @package    core
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth2_client_registration_metadata {

    /**
     * Helper array mapping the spec field names to the instance var names.
     * @var string[]
     */
    private static array $fieldmap = [
        'redirect_uris' => 'redirecturis',
        'token_endpoint_auth_method' => 'tokenendpointauthmethod',
        'grant_types' => 'grantypes',
        'response_types' => 'responsetypes',
        'client_name' => 'clientname',
        'client_uri' => 'clienturi',
        'logo_uri' => 'logouri',
        'scope' => 'scope',
        'contacts' => 'contacts',
        'tos_uri' => 'tosuri',
        'policy_uri' => 'policyuri',
        'jwks_uri' => 'jwksuri',
        'jwks' => 'jwks',
        'software_id' => 'softwareid',
        'software_version' => 'softwareversion'
    ];

    protected function __construct(
        protected array $redirecturis = [],
        protected string $tokenendpointauthmethod = '',
        protected array $grantypes = [],
        protected array $responsetypes = [],
        protected string $clientname = '',
        protected string $clienturi = '',
        protected string $logouri = '',
        protected string $scope = '',
        protected array $contacts = [],
        protected string $tosuri = '',
        protected string $policyuri = '',
        protected string $jwksuri = '',
        protected string $jwks = '',
        protected string $softwareid = '',
        protected string $softwareversion = '',
        protected array $customfields = [] // Handle any other fields from extension specs.
    ) {
        $this->validate_uris();
        $this->validate_other();
        $this->apply_defaults();
        // TODO: redirect_uris MUST be present when redirect-based flows are used. E.g. auth code or implicit.
    }

    public static function from_array(array $metadata): self {
        // Map the 'real' keys from the array input into the constructor named params and then init.

        // First map all the known fields.
        $init = [];
        foreach (self::$fieldmap as $fieldname => $localname) {
            if (isset($metadata[$fieldname])) {
                $init[$localname] = $metadata[$fieldname];
                unset($metadata[$fieldname]);
            }
        }

        // Any other fields are treated as extension (custom) fields.
        foreach ($metadata as $key => $val) {
            $init['customfields'][$key] = $val;
        }

        return new self(...$init);
    }

    /**
     * Get the metadata as an array.
     *
     * @return array the array of metadata.
     */
    public function to_array(): array {
        $out = [];
        foreach (self::$fieldmap as $fieldname => $localname) {
            if (!empty($this->$localname)) {
                $out[$fieldname] = $this->$localname;
            }
        }
        foreach ($this->customfields as $key => $val) {
            $out[$key] = $val;
        }
        return $out;
    }

    protected function validate_tls_uri(string $uri) {
        $uri = new \moodle_url($uri);
        if (strtolower($uri->get_scheme()) !== 'https') {
            throw new \moodle_exception('Error: '.__METHOD__.': URI '.$uri->out(false).' must be HTTPS.');
        }
    }

    protected function validate_uris() {
        global $CFG;
        require_once($CFG->libdir . '/moodlelib.php');

        foreach ($this->redirecturis as $redirecturi) {
            validate_param($redirecturi, PARAM_URL);
            $this->validate_tls_uri($redirecturi);
        }

        if (!empty($this->clienturi)) {
            validate_param($this->clienturi, PARAM_URL);
        }

        if (!empty($this->logouri)) {
            validate_param($this->logouri, PARAM_URL);
        }

        if (!empty($this->tosuri)) {
            validate_param($this->tosuri, PARAM_URL);
        }

        if (!empty($this->policyuri)) {
            validate_param($this->policyuri, PARAM_URL);
        }

        if (!empty($this->jwksuri)) {
            validate_param($this->jwksuri, PARAM_URL);
            $this->validate_tls_uri($this->jwksuri);
        }
    }

    protected function validate_other(): void {
        // JWKS URI and JWKS MUST NOT both be included in the metadata.
        if (!empty($this->jwksuri) && !empty($this->jwks)) {
            throw new \moodle_exception('Error: '.__METHOD__.': Cannot include both jwks_uri and jwks in metadata.');
        }

        // If present, JWKS must be JSON.
        if (!empty($this->jwks) && json_decode($this->jwks) === null) {
            throw new \moodle_exception('Error: '.__METHOD__.': jwks must be valid JSON.');
        }
    }

    protected function apply_defaults() {
        if (empty($this->responsetypes)) {
            $this->responsetypes[] = 'code';
        }

        if (empty($this->granttypes)) {
            $this->granttypes[] = 'authorization_code';
        }

        if (empty($this->tokenendpointauthmethod)) {
            $this->tokenendpointauthmethod = "client_secret_basic";
        }
    }
}
