<?php

namespace core\oauth2\discovery;

class openid_connect_discovery implements configuration_discovery {

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
     * Get the discovery URL, as per the OpenID Connect Discovery 1.0 specification.
     *
     * @return \moodle_url the discovery URL.
     */
    protected function get_discovery_url(): \moodle_url {
        // Add slash at the end of the issuer url, if required, and append the OIDC discovery well known string.
        // Per the spec (to allow multiple issuers per host) any path component in the issuer URL is included in the discovery URL.
        $url = $this->issuerurl->out(false);
        $url .= (substr($url, -1) == '/' ? '' : '/');
        $url .= '.well-known/openid-configuration';
        return new \moodle_url($url);
    }
}
