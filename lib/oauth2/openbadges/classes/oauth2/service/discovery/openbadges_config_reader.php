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

namespace oauth2service_openbadges\oauth2\service\discovery;

/**
 * Simple reader class, allowing configuration information to be read from an Open Badges 2.1 manifest.
 *
 * As per https://www.imsglobal.org/spec/ob/v2p1#manifest
 *
 * @package    oauth2service_openbadges
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openbadges_config_reader {

    /** @var \moodle_url the base URL of the issuer. */
    protected \moodle_url $issuerurl;

    /** @var \curl curl instance for HTTP calls. */
    protected \curl $curl;

    /** @var \stdClass the config object read from the discovery document. */
    protected \stdClass $issuerconfig;

    /** @var array associative array of endpoint names to URLs. */
    protected array $endpoints;

    /**
     * Constructor.
     *
     * @param \curl $curl a curl instance for HTTP stuff.
     */
    public function __construct(\curl $curl) {
        $this->curl = $curl;
    }

    /**
     * Read the badge configuration from the remote host.
     *
     * @param \moodle_url $issuerbaseurl the base URL of the issuer, allowing the well-known to be determined.
     * @return \stdClass the configuration data object.
     * @throws \moodle_exception if the configuration was unable to be read.
     */
    public function read_json_configuration(\moodle_url $issuerbaseurl): \stdClass {
        $url = $this->get_configuration_url($issuerbaseurl)->out(false);

        if (!$json = $this->curl->get($url)) {
            throw new \moodle_exception("Badge manifest for issuer '$this->issuerurl' not found.");
        }

        if ($msg = $this->curl->error) {
            throw new \moodle_exception('Badge manifest read error: ' . $msg);
        }

        $info = json_decode($json);
        if (empty($info)) {
            throw new \moodle_exception("Badge manifest for issuer '$this->issuerurl' not found.");
        }
        $this->issuerconfig = (object) $info;

        $this->parse_endpoints_from_last_read();

        return $this->issuerconfig;
    }

    /**
     * Get the endpoints from the configuration.
     *
     * @return array the array of endpoints.
     */
    public function get_endpoints(): array {
        return $this->endpoints;
    }

    /**
     * Parse the endpoints from the last read.
     *
     * @return void
     */
    protected function parse_endpoints_from_last_read(): void {
        foreach ($this->issuerconfig->badgeConnectAPI[0] as $key => $value) {
            if (substr_compare($key, 'Url', - strlen('Url')) === 0 && !empty($value)) {
                // Convert key names from xxxxUrl to xxxx_endpoint, in order to make it compliant with the Moodle oAuth API.
                $name = strtolower(substr($key, 0, -strlen('Url'))) . '_endpoint';
                $this->endpoints[$name] = $value;
            }
        }
    }

    /**
     * Get the URL where the badge manifest can be found.
     *
     * Per https://www.imsglobal.org/spec/ob/v2p1#manifest (and per https://www.rfc-editor.org/rfc/rfc5785).
     *
     * @param \moodle_url $issuerbaseurl the issuer base URL, to which the well known suffix will be appended.
     * @return \moodle_url the well-known badge manifest URL.
     */
    protected function get_configuration_url(\moodle_url $issuerbaseurl): \moodle_url {
        // Add slash at the end of the issuer url, if required, and append the OIDC discovery well known string.
        // Per the spec, to allow multiple issuers per host, any path component in the issuer URL is included in the discovery URL.
        $url = $issuerbaseurl->out(false);

        // TODO: Per the badge spec and per RFC5785, the well known should not be at directory level,
        //  which is possible given the code below. We need to strip the path to resolve this.
        $url .= (substr($url, -1) == '/' ? '' : '/');
        $url .= '.well-known/badgeconnect.json';

        return new \moodle_url($url);
    }
}
