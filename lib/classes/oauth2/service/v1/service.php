<?php

namespace core\oauth2\service\v1;

use core\oauth2\issuer;
use core\oauth2\user_field_mapping;
use core\oauth2\endpoint;

/**
 * Defines the API standard issuers must implement to support their inclusion in core_oauth2 service creation.
 */
interface service {
    /**
     * Return the \core\oauth2\issuer persistent, containing the default configuration for the service.
     *
     * @return issuer|null the issuer containing service-specific data, or null if the service doesn't require any specific data.
     */
    public static function get_template(): ?issuer;

    /**
     * Factory method to return an instance of the class based on the issuer input data.
     *
     * @param issuer $issuer
     * @return service
     */
    public static function get_instance(issuer $issuer): service;

    /**
     * Get the issuer data as determined by the service.
     *
     * Services may wish to augment the underlying issuer data as part of their individual business rules or specifications. This
     * method is called to get that final issuer data.
     *
     * @return issuer
     */
    public function get_issuer(): issuer;

    /**
     * Get the OAuth 2 endpoints for the service.
     *
     * @return endpoint[]
     */
    public function get_endpoints(): array;

    /**
     * Return the user_field_mapping instances, if the service supports it.
     *
     * @return user_field_mapping[]
     */
    public function get_field_mappings(): array;
}
