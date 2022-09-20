<?php

namespace core\oauth2\service\google;

use core\oauth2\issuer;
use core\oauth2\endpoint;
use core\oauth2\user_field_mapping;
use core\oauth2\service\v1\service;

class google implements service {

    protected issuer $issuer;

    protected array $endpoints;

    protected \curl $curl;

    protected bool $discovered = false;

    public function __construct(issuer $issuer, \curl $curl) {
        $this->issuer = $issuer;
        $this->endpoints = [];
        $this->curl = $curl;
    }

    public static function get_template(): ?issuer {
        $record = (object) [
            'name' => 'Google',
            'image' => 'https://accounts.google.com/favicon.ico',
            'baseurl' => 'https://accounts.google.com/',
            'loginparamsoffline' => 'access_type=offline&prompt=consent',
            'showonloginpage' => issuer::EVERYWHERE,
            'servicetype' => 'google',
        ];
        return new issuer(0, $record);
    }

    public static function get_instance(issuer $issuer): service {
        return new self($issuer, new \curl());
    }

    public function get_issuer(): issuer {
        $this->discover_metadata();
        return $this->issuer;
    }

    public function get_endpoints(): array {
        $this->discover_metadata();
        return array_values($this->endpoints);
    }

    public function get_field_mappings(): array {
        $mapping = [
            'given_name' => 'firstname',
            'middle_name' => 'middlename',
            'family_name' => 'lastname',
            'email' => 'email',
            'nickname' => 'alternatename',
            'picture' => 'picture',
            'address' => 'address',
            'phone' => 'phone1',
            'locale' => 'lang',
        ];

        $m = [];
        foreach ($mapping as $external => $internal) {
            $record = (object) [
                'externalfield' => $external,
                'internalfield' => $internal
            ];
            $m[] = new user_field_mapping(0, $record);
        }
        return $m;
    }

    protected function discover_metadata(): void {
        if ($this->discovered) {
            return;
        }

        $url = $this->get_discovery_endpoint_url();

        if (!$json = $this->curl->get($url)) {
            $msg = 'Could not discover end points for identity issuer: ' . $this->issuer->get('name') . " [URL: $url]";
            throw new \moodle_exception($msg);
        }

        if ($msg = $this->curl->error) {
            throw new \moodle_exception('Could not discover service endpoints: ' . $msg);
        }

        $info = json_decode($json);
        if (empty($info)) {
            $msg = 'Could not discover end points for identity issuer: ' . $this->issuer->get('name') . " [URL: $url]";
            throw new \moodle_exception($msg);
        }

        $record = (object) [
            'name' => 'discovery_endpoint',
            'url' => $url,
        ];
        $this->endpoints[$record->name] = new endpoint(0, $record);

        $this->process_configuration_json($info);
        $this->discovered = true;
    }

    protected function get_discovery_endpoint_url(): string {
        $url = $this->issuer->get('baseurl');
        if (!empty($url)) {
            // Add slash at the end of the base url.
            $url .= (substr($url, -1) == '/' ? '' : '/');
            // Append the well-known file for OIDC.
            $url .= '.well-known/openid-configuration';
        }

        return $url;
    }

    protected function process_configuration_json(\stdClass $info) {
        foreach ($info as $key => $value) {
            if (substr_compare($key, '_endpoint', - strlen('_endpoint')) === 0) {
                $record = new \stdClass();
                $record->name = $key;
                $record->url = $value;

                $this->endpoints[$record->name] = new endpoint(0, $record);
            }

            if ($key == 'scopes_supported') {
                $this->issuer->set('scopessupported', implode(' ', $value));
            }
        }
    }
}
