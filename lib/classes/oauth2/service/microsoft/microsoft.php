<?php

namespace core\oauth2\service\microsoft;

use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\service\v1\service;
use core\oauth2\user_field_mapping;

class microsoft implements service {

    protected issuer $issuer;

    public function __construct(issuer $issuer) {
        $this->issuer = $issuer;
    }

    public static function get_template(): ?issuer {
        $record = (object) [
            'name' => 'Microsoft',
            'image' => 'https://www.microsoft.com/favicon.ico',
            'baseurl' => '',
            'loginscopes' => 'openid profile email user.read',
            'loginscopesoffline' => 'openid profile email user.read offline_access',
            'showonloginpage' => issuer::EVERYWHERE,
            'servicetype' => 'microsoft',
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
        $endpoints = [
            'authorization_endpoint' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token_endpoint' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'userinfo_endpoint' => 'https://graph.microsoft.com/v1.0/me/',
            'userpicture_endpoint' => 'https://graph.microsoft.com/v1.0/me/photo/$value',
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
            'givenName' => 'firstname',
            'surname' => 'lastname',
            'userPrincipalName' => 'email',
            'displayName' => 'alternatename',
            'officeLocation' => 'address',
            'mobilePhone' => 'phone1',
            'preferredLanguage' => 'lang'
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
