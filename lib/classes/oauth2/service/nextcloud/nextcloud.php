<?php

namespace core\oauth2\service\nextcloud;

use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\user_field_mapping;
use core\oauth2\service\v1\service;

class nextcloud implements service {

    protected issuer $issuer;

    public function __construct(issuer $issuer) {
        $this->issuer = $issuer;
    }

    public static function get_template(): ?issuer {
        $record = (object) [
            'name' => 'Nextcloud',
            'image' => 'https://nextcloud.com/wp-content/themes/next/assets/img/common/favicon.png?x16328',
            'basicauth' => 1,
            'servicetype' => 'nextcloud',
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
        $baseurl = $this->issuer->get('baseurl');
        // Add trailing slash to baseurl, if needed.
        if (substr($baseurl, -1) !== '/') {
            $baseurl .= '/';
        }

        $endpoints = [
            // Baseurl will be prepended later.
            'authorization_endpoint' => 'index.php/apps/oauth2/authorize',
            'token_endpoint' => 'index.php/apps/oauth2/api/v1/token',
            'userinfo_endpoint' => 'ocs/v2.php/cloud/user?format=json',
            'webdav_endpoint' => 'remote.php/webdav/',
            'ocs_endpoint' => 'ocs/v1.php/apps/files_sharing/api/v1/shares',
        ];
        $e = [];
        foreach ($endpoints as $name => $url) {
            $record = (object) [
                'name' => $name,
                'url' => $baseurl . $url,
            ];
            $e[] = new endpoint(0, $record);
        }
        return $e;
    }

    public function get_field_mappings(): array {
        // Create the field mappings.
        $mapping = [
            'ocs-data-email' => 'email',
            'ocs-data-id' => 'username',
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
