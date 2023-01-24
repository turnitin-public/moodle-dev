<?php

namespace oauth2service_custom;

use core\oauth2\issuer;
use core\oauth2\service\config\config;

class service extends \core\oauth2\service\service {

    protected issuer $issuer;

    /**
     * TODO: this will likely require several discovery instances (for use in get_endpoints) and dynamic registration instances
     */
    public function __construct(issuer $issuer) {
        $this->issuer = $issuer;
    }

    public static function get_template(): ?issuer {
        return null; // Custom doesn't provide any setup template.
    }

    public static function get_instance(issuer $issuer): \core\oauth2\service\service {
        return new self($issuer);
    }

    public function get_issuer(): issuer {
        return $this->issuer;
    }

    public function get_endpoints(): array {
        // TODO: This will discover endpoints based on the base URL and use either OAuth2 endpoint discovery,
        //  or OIDC endpoint discovery.
        return [];
    }
}
