<?php

namespace oauth2service_custom;

use core\http_client;
use core\oauth2\discovery\openid_config_reader;
use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\user_field_mapping;

/**
 * Custom OAuth 2 service plugin implementation.
 *
 * The custom service is a generic plugin intended to support creation of a range of OAuth 2 and OpenId Connect issuers.
 * This custom service uses the core oauth2 client and does not provide its own custom implementation.
 *
 * Currently, this plugins supports the following:
 *
 * 1. Manual configuration (if 'baseurl' is omitted in the issuer):
 * - When baseurl is omitted, the issuer data will be returned as set in the form (no discovery takes place).
 * - No endpoints are generated, these must be set by the user after issuer creation.
 * - No user field mappings are generated, these must be set by the user after issuer creation.
 * - Manual configuration is preferred for OAuth 2 apps, since the discovery process only currently supports OpenId compatible apps.
 *
 * 2. OpenId Connect metadata discovery (if 'baseurl' is set on the issuer):
 * - The plugin will attempt to read the openid configuration for the service.
 * - Discovered endpoints are used and supported scopes set and
 * - Default user field mappings (based on OpenId user claims) are returned.
 *
 * Currently unsupported:
 * - OAuth 2 Auth Server Metadata discovery (this plugin is currently only compatible with openid discovery)
 * - OAuth 2 Dynamic Client Registration (with or without a token).
 * - OpenId Connect Dynamic Client Registration (with or without a token).
 *
 * @package    oauth2service_custom
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service extends \core\oauth2\service\service {

    /** @var bool whether the service configuration has already been read. */
    protected bool $configread = false;

    /** @var array the OAuth 2 endpoints found in the OpenID configuration. */
    protected array $endpoints = [];

    /** @var array the array of user field mapping instances. */
    protected array $userfieldmapping = [];

    /**
     * Constructor.
     *
     * @param issuer $issuer the issuer instance this plugin receives after form submission.
     * @param openid_config_reader $configreader an openid configuration reader instance.
     */
    public function __construct(protected issuer $issuer, protected openid_config_reader $configreader) {
    }

    public static function get_template(): ?issuer {
        return null; // Custom deliberately provides a blank template for the issuer form.
    }

    public static function get_instance(issuer $issuer): \core\oauth2\service\service {
        return new self($issuer, new openid_config_reader(new http_client()));
    }

    public function get_issuer(): issuer {
        $this->read_configuration();
        return $this->issuer;
    }

    public function get_endpoints(): array {
        $this->read_configuration();
        return array_values($this->endpoints);
    }

    public function get_field_mappings(): array {
        // User field mapping only returned when the service supports openid metadata discovery.
        $this->read_configuration();
        if (!$this->configread) {
            return [];
        }

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

        foreach ($mapping as $external => $internal) {
            $record = (object) [
                'externalfield' => $external,
                'internalfield' => $internal
            ];
            $this->userfieldmapping[] = new user_field_mapping(0, $record);
        }
        return $this->userfieldmapping;
    }

    public function validation(array $coreerrors): array {
        $errors = [];
        if (!isset($coreerrors['baseurl']) && !empty($this->issuer->get('baseurl'))) {
            // The 'baseurl' field is used to find the openid config. Make sure this URL is suitable for that.
            $base = new \moodle_url($this->issuer->get('baseurl'));
            $querystring = (!empty($base->get_query_string()));
            $badcheme = (strtolower($base->get_scheme()) !== 'https');
            // This last bit catches URL fragments. If the query string is empty, out_omit_querystring(false) returns only
            // fragments.
            $fragments = ($base->out_omit_querystring() != $base->out(false));

            if ($querystring || $badcheme || $fragments) {
                $errors['baseurl'] = 'The base URL is not valid for use with discovery. It must be an HTTPS URL without query '.
                    'strings or parameters.';
            } else {
                // URL is suitable. Make sure the config can be read before allowing form save.
                try {
                    $this->read_configuration();
                } catch (\Exception $e) {
                    $errors['baseurl'] = 'The OpenId configuration could not be read from '
                        . $this->configreader->get_last_read_config_url()->out(false) . '. If the service doesn\'t support '.
                        'configuration discovery, this should be left blank';
                }
            }
        }
        return $errors;
    }

    /**
     * Tries to read the server metadata, parsing it for endpoints, supported scopes, etc.
     *
     * @return void
     */
    protected function read_configuration(): void {
        $issuerbaseurl = $this->issuer->get('baseurl');

        if ($this->configread || empty($issuerbaseurl)) {
            return;
        }

        // Only read from the remote once per request, which permits checking the configuration endpoint during form validation.
        $cache = \cache::make('oauth2service_custom', 'openidconfiguration');
        if (!$openidconfig = $cache->get($issuerbaseurl)) {
            try {
                $openidconfig = $this->configreader->read_configuration(new \moodle_url($issuerbaseurl));

                // This isn't openid config per se, but it's nice to have included in the list of endpoints.
                $openidconfig->discovery_endpoint = $this->configreader->get_last_read_config_url()->out(false);

                $cache->set($issuerbaseurl, $openidconfig);
            } catch (\Exception $e) {
                throw new \moodle_exception("Server metadata for issuer '{$this->issuer->get('name')}' not found. 
                    The configuration document could not be read.");
            }
        }

        // Process the config.
        foreach ($openidconfig as $key => $value) {
            if (substr_compare($key, '_endpoint', - strlen('_endpoint')) === 0) {
                $record = (object) [
                    'name' => $key,
                    'url' => $value
                ];
                $this->endpoints[$key] = new endpoint(0, $record);
            }
        }

        if (!empty($openidconfig->scopes_supported)) {
            $this->issuer->set('scopessupported', implode(' ', $openidconfig->scopes_supported));
        }

        $this->configread = true;
    }
}
