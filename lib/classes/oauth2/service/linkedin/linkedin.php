<?php

namespace core\oauth2\service\linkedin;

use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\service\v1\service;
use core\oauth2\user_field_mapping;

class linkedin implements service {

    protected issuer $issuer;

    public function __construct(issuer $issuer) {
        $this->issuer = $issuer;
    }

    public static function get_template(): ?issuer {
        $record = (object) [
            'name' => 'LinkedIn',
            'image' => 'https://static.licdn.com/scds/common/u/images/logos/favicons/v1/favicon.ico',
            'baseurl' => 'https://api.linkedin.com/v2',
            'loginscopes' => 'r_liteprofile r_emailaddress',
            'loginscopesoffline' => 'r_liteprofile r_emailaddress',
            'showonloginpage' => issuer::EVERYWHERE,
            'servicetype' => 'linkedin',
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
            'authorization_endpoint' => 'https://www.linkedin.com/oauth/v2/authorization',
            'token_endpoint' => 'https://www.linkedin.com/oauth/v2/accessToken',
            'email_endpoint' => 'https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))',
            'userinfo_endpoint' => "https://api.linkedin.com/v2/me?projection=(localizedFirstName,localizedLastName,"
                . "profilePicture(displayImage~digitalmediaAsset:playableStreams))",
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
            'localizedFirstName' => 'firstname',
            'localizedLastName' => 'lastname',
            'elements[0]-handle~-emailAddress' => 'email',
            'profilePicture-displayImage~-elements[0]-identifiers[0]-identifier' => 'picture'
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
