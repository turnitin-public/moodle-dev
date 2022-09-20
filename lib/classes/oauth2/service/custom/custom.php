<?php

namespace core\oauth2\service\custom;

use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\service\v1\service;
use core\oauth2\user_field_mapping;

class custom implements service {

    protected issuer $issuer;

    public function __construct(issuer $issuer) {
        $this->issuer = $issuer;
    }

    public static function get_template(): ?issuer {
        return null; // Empty template.
    }

    public static function get_instance(issuer $issuer): service {
        return new self($issuer);
    }

    public function get_issuer(): issuer {
        return $this->issuer;
    }

    public function get_endpoints(): array {
        return [];
    }

    public function get_field_mappings(): array {
        return [];
    }
}
