<?php

namespace core\oauth2\service\facebook;

use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\service\v1\service;
use core\oauth2\user_field_mapping;

class facebook implements \core\oauth2\service\v1\service {

    protected issuer $issuer;

    public function __construct(issuer $issuer) {
        $this->issuer = $issuer;
    }

    public static function get_template(): ?issuer {
        $record = (object) [
            'name' => 'Facebook',
            'image' => 'https://facebookbrand.com/wp-content/uploads/2016/05/flogo_rgb_hex-brc-site-250.png',
            'baseurl' => '',
            'loginscopes' => 'public_profile email',
            'loginscopesoffline' => 'public_profile email',
            'showonloginpage' => issuer::EVERYWHERE,
            'servicetype' => 'facebook',
        ];

        return new issuer(0, $record);
    }

    public static function get_instance(issuer $issuer): service {
        return new self($issuer);
    }

    public function get_issuer(): issuer {
        return $this->issuer;
    }

    public function get_endpoints(): array {
        // The Facebook API version.
        $apiversion = '2.12';
        // The Graph API URL.
        $graphurl = 'https://graph.facebook.com/v' . $apiversion;
        // User information fields that we want to fetch.
        $infofields = [
            'id',
            'first_name',
            'last_name',
            'picture.type(large)',
            'name',
            'email',
        ];
        $endpoints = [
            'authorization_endpoint' => sprintf('https://www.facebook.com/v%s/dialog/oauth', $apiversion),
            'token_endpoint' => $graphurl . '/oauth/access_token',
            'userinfo_endpoint' => $graphurl . '/me?fields=' . implode(',', $infofields)
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
        // Create the field mappings.
        $mapping = [
            'name' => 'alternatename',
            'last_name' => 'lastname',
            'email' => 'email',
            'first_name' => 'firstname',
            'picture-data-url' => 'picture',
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
}
