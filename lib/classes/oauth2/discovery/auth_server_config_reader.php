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

namespace core\oauth2\discovery;

use core\http_client;

/**
 * Simple reader class, allowing OAuth 2 Authorization Server Metadata to be read from an auth server's well-known.
 *
 * {@see https://www.rfc-editor.org/rfc/rfc8414}
 *
 * @package    core
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_server_config_reader {

    /** @var \stdClass the config object read from the discovery document. */
    protected \stdClass $metadata;

    /** @var array associative array of endpoint names to URLs. */
    protected array $endpoints = [];

    /**
     * Constructor.
     *
     * @param http_client $httpclient an http client instance.
     */
    public function __construct(protected http_client $httpclient) {
    }

    /**
     * Read the metadata from the remote host.
     *
     * @param \moodle_url $issuerurl the issuer base URL.
     * @return \stdClass the configuration data object.
     * @throws \moodle_exception if the URL is invalid, or the configuration was unable to be read.
     */
    public function read_configuration(\moodle_url $issuerurl): \stdClass {
        $this->validate_uri($issuerurl);

        try {
            $url = $this->get_configuration_url($issuerurl)->out(false);
            $response = $this->httpclient->request('GET', $url);
            $this->metadata = json_decode($response->getBody());
            $this->parse_endpoints_from_last_read();
            return $this->metadata;
        } catch (ClientException $e) {
            $responsepretty = Psr7\Message::toString($e->getResponse());
            throw new \moodle_exception("Metadata for issuer '{$this->issuerurl->out(false)}' not found. " .
                "Response: '$responsepretty'");
        }
    }

    /**
     * Get the endpoints from the configuration.
     *
     * @return array the array of endpoints.
     */
    public function get_endpoints(): array {
        return $this->endpoints;
    }

    protected function validate_uri(\moodle_url $issuerurl) {
        if (!empty($issuerurl->get_query_string())) {
            throw new \moodle_exception('Error: '.__METHOD__.': Auth server base URL cannot contain a query component.');
        }
        if (strtolower($issuerurl->get_scheme()) !== 'https') {
            throw new \moodle_exception('Error: '.__METHOD__.': Auth server base URL must use HTTPS scheme.');
        }
        // This catches URL fragments. Since a query string is ruled out above, out_omit_querystring(false) returns only fragments.
        if ($issuerurl->out_omit_querystring() != $issuerurl->out(false)) {
            throw new \moodle_exception('Error: '.__METHOD__.': Auth server base URL must not contain fragments.');
        }
    }

    /**
     * Parse the endpoints from the last read.
     *
     * @return void
     */
    protected function parse_endpoints_from_last_read(): void {
        foreach ($this->metadata as $key => $value) {
            if (substr_compare($key, '_endpoint', - strlen('_endpoint')) === 0) {
                $this->endpoints[$key] = $value;
            }
        }
    }

    /**
     * Get the Auth server metadata URL.
     *
     * Per {@see https://www.rfc-editor.org/rfc/rfc8414#section-3}, if the issuer URL contains a path component,
     * the well known suffix is added between the host and path components.
     *
     * @param \moodle_url $issuerurl the auth server base URL, on which to append the well known suffix.
     * @return \moodle_url the full URL to the auth server metadata.
     */
    protected function get_configuration_url(\moodle_url $issuerurl): \moodle_url {
        if ($path = $issuerurl->get_path()) {
            // Insert the well known suffix between the host and path components.
            $port = $issuerurl->get_port() ? ':'.$issuerurl->get_port() : '';
            $uri = "{$issuerurl->get_scheme()}://{$issuerurl->get_host()}$port/.well-known/oauth-authorization-server$path";
        } else {
            // No path, just append the well known suffix.
            $uri = $issuerurl->out(false);
            $uri .= (substr($uri, -1) == '/' ? '' : '/');
            $uri .= '.well-known/oauth-authorization-server';
        }

        return new \moodle_url($uri);
    }
}
