<?php

namespace core\oauth2\service\clever;

use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\service\v1\service;
use core\oauth2\user_field_mapping;

class clever implements service {

    protected issuer $issuer;

    public function __construct(issuer $issuer) {
        $this->issuer = $issuer;
    }

    public static function get_template(): ?issuer {
        $record = (object) [
            'name' => 'Clever',
            'image' => 'https://apps.clever.com/favicon.ico',
            'basicauth' => 1,
            'baseurl' => '',
            'showonloginpage' => issuer::LOGINONLY,
            'servicetype' => 'clever',
        ];

        return new issuer(0, $record);
    }

    public function get_endpoints(): array {
        $endpoints = [
            'authorization_endpoint' => 'https://clever.com/oauth/authorize',
            'token_endpoint' => 'https://clever.com/oauth/tokens',
            'userinfo_endpoint' => 'https://api.clever.com/v3.0/me',
            'userdata_endpoint' => 'https://api.clever.com/v3.0/users'
        ];
        $e = [];
        foreach ($endpoints as $name => $url) {
            $record = (object) [
                'name' => $name,
                'url' => $url
            ];
            $e[] = new endpoint(0, $record);
        }
        return $e;
    }

    public function get_field_mappings(): array {
        $mapping = [
            'data-id' => 'idnumber',
            'data-name-first' => 'firstname',
            'data-name-last' => 'lastname',
            'data-email' => 'email'
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

    public static function get_instance(issuer $issuer): service {
        return new self($issuer);
    }

    public function get_issuer(): issuer {
        return $this->issuer;
    }
}
