<?php

namespace core\oauth2\service\google;

use core\oauth2\discovery\openid_connect_discovery;
use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\user_field_mapping;
use core\oauth2\service\v1\service;

class google implements service {

    protected issuer $issuer;

    protected array $endpoints;

    protected bool $discovered = false;

    protected openid_connect_discovery $oidcconfigreader;

    public function __construct(issuer $issuer, ?openid_connect_discovery $oidcconfigreader) {
        $this->issuer = $issuer;
        $this->endpoints = [];
        if ($oidcconfigreader) {
            $this->oidcconfigreader = $oidcconfigreader;
        }
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
        $issuerurl = $issuer->get('baseurl');
        $oidcconfigreader = !empty($issuerurl) ? new openid_connect_discovery(new \moodle_url($issuerurl), new \curl()) : null;
        return new self($issuer, $oidcconfigreader);
    }

    public function get_issuer(): issuer {
        $this->get_issuer_configuration();
        return $this->issuer;
    }

    public function get_endpoints(): array {
        $this->get_issuer_configuration();
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

    protected function get_issuer_configuration(): void {
        if ($this->discovered || empty($this->oidcconfigreader)) {
            return;
        }

        // TODO: we usually save the discovery_endpoint here but we don't need to - it's not needed as long as google does oidc.
        //$record = (object) [
        //    'name' => 'discovery_endpoint',
        //    'url' => $url,
        //];
        //$this->endpoints[$record->name] = new endpoint(0, $record);

        $this->issuerconfig = $this->oidcconfigreader->read_configuration();
        foreach ($this->issuerconfig as $key => $value) {
            if ($key == 'scopes_supported') {
                $this->issuer->set('scopessupported', implode(' ', $value));
            }
        }

        foreach ($this->oidcconfigreader->get_endpoints() as $name => $url) {
            $record = (object) [
                'name' => $name,
                'url' => $url
            ];
            $this->endpoints[$record->name] = new endpoint(0, $record);
        }
        $this->discovered = true;
    }
}
