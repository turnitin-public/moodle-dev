<?php

namespace core\oauth2\discovery;

class oauth2_auth_server_metadata implements configuration_discovery {

    protected \moodle_url $issuerurl;

    protected \curl $curl;

    protected \stdClass $issuerconfig;

    /** @var array associative array of endpoint names to urls. */
    protected array $endpoints;

    public function __construct(\moodle_url $issuerurl, \curl $curl) {
        $this->issuerurl = $issuerurl;
        $this->curl = $curl;
    }

    public function read_configuration(): \stdClass {
        $url = $this->get_discovery_url()->out(false);

        if (!$json = $this->curl->get($url)) {
            throw new \moodle_exception('Could not discover end points for identity issuer: ' . $this->issuerurl);
        }

        if ($msg = $this->curl->error) {
            throw new \moodle_exception('Could not discover service configuration: ' . $msg);
        }

        $info = json_decode($json);
        if (empty($info)) {
            throw new \moodle_exception('Could not discover end points for identity issuer: ' . $this->issuerurl);
        }
        $this->issuerconfig = (object) $info;

        return $this->issuerconfig;
    }

    public function get_endpoints(): array {
        $this->parse_endpoints_from_last_read();

        return $this->endpoints;
    }

    protected function parse_endpoints_from_last_read(): void {
        foreach ($this->issuerconfig as $key => $value) {
            if (substr_compare($key, '_endpoint', - strlen('_endpoint')) === 0) {
                $this->endpoints[$key] = $value;
            }
        }
    }

    /**
     * Get the discovery URL, as per the OAuth 2.0 Authorization Server Metadata specification.
     *
     * @return \moodle_url the discovery URL.
     */
    protected function get_discovery_url(): \moodle_url {
        // Per the spec (to allow multiple issuers per host) the path is appended after the well known configuration endpoint.
        $parsed = parse_url($this->issuerurl->out(false));
        $port = !empty($parsed['port']) ? ':'.$parsed['port'] : '';
        $url = $parsed['scheme']. '://' . $parsed['host'] . $port . '/.well-known/oauth-authorization-server' . $parsed['path'];

        return new \moodle_url($url);
    }
}
