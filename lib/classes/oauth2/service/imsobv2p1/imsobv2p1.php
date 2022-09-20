<?php

namespace core\oauth2\service\imsobv2p1;

use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\service\v1\service;

class imsobv2p1 implements service {

    protected issuer $issuer;

    protected array $endpoints;

    protected \curl $curl;

    protected bool $discovered = false;

    public function __construct(issuer $issuer, \curl $curl) {
        $this->issuer = $issuer;
        $this->endpoints = [];
        $this->curl = $curl;
    }

    public static function get_template(): ?issuer {
        $record = (object) [
            'name' => 'Open Badges',
            'image' => '',
            'servicetype' => 'imsobv2p1',
        ];

        return new issuer(0, $record);
    }

    public static function get_instance(issuer $issuer): service {
        return new self($issuer, new \curl());
    }

    public function get_issuer(): issuer {
        $this->discover_metadata();
        $this->register();
        return $this->issuer;
    }

    public function get_endpoints(): array {
        $this->discover_metadata();
        return array_values($this->endpoints);
    }

    public function get_field_mappings(): array {
        // This isn't an OpenID issuer and isn't intended to be used with auth/account creation.
        return [];
    }

    protected function discover_metadata(): void {
        if ($this->discovered) {
            return;
        }

        $url = $this->get_discovery_endpoint_url();

        if (!$json = $this->curl->get($url)) {
            $msg = 'Could not discover end points for identity issuer: ' . $this->issuer->get('name') . " [URL: $url]";
            throw new \moodle_exception($msg);
        }

        if ($msg = $this->curl->error) {
            throw new \moodle_exception('Could not discover service endpoints: ' . $msg);
        }

        $info = json_decode($json);
        if (empty($info)) {
            $msg = 'Could not discover end points for identity issuer: ' . $this->issuer->get('name') . " [URL: $url]";
            throw new \moodle_exception($msg);
        }

        $record = (object) [
            'name' => 'discovery_endpoint',
            'url' => $url,
        ];
        $this->endpoints[$record->name] = new endpoint(0, $record);

        $this->process_configuration_json($info);
        $this->discovered = true;
    }

    /**
     * Get the URL for the discovery manifest.
     *
     * @return string The URL of the discovery file, containing the endpoints.
     */
    protected function get_discovery_endpoint_url(): string {
        $url = $this->issuer->get('baseurl');
        if (!empty($url)) {
            // Add slash at the end of the base url.
            $url .= (substr($url, -1) == '/' ? '' : '/');
            // Append the well-known file for IMS OBv2.1.
            $url .= '.well-known/badgeconnect.json';
        }

        return $url;
    }

    protected function process_configuration_json(\stdClass $info) {

        $info = array_pop($info->badgeConnectAPI);
        foreach ($info as $key => $value) {
            if (substr_compare($key, 'Url', - strlen('Url')) === 0 && !empty($value)) {
                $record = new \stdClass();
                // Convert key names from xxxxUrl to xxxx_endpoint, in order to make it compliant with the Moodle oAuth API.
                $record->name = strtolower(substr($key, 0, - strlen('Url'))) . '_endpoint';
                $record->url = $value;

                $this->endpoints[$record->name] = new endpoint(0, $record);
            } else if ($key == 'scopesOffered') {
                // Get and update supported scopes.
                $this->issuer->set('scopessupported', implode(' ', $value));
            } else if ($key == 'image' && empty($this->issuer->get('image'))) {
                // Update the image with the value in the manifest file if it's valid and empty in the issuer.
                $url = filter_var($value, FILTER_SANITIZE_URL);
                // Remove multiple slashes in URL. It will fix the Badgr bug with image URL defined in their manifest.
                $url = preg_replace('/([^:])(\/{2,})/', '$1/', $url);
                if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
                    $this->issuer->set('image', $url);
                }
            }
        }
    }

    /**
     * Self-register the issuer if the 'registration' endpoint exists and client id and secret aren't defined.
     *
     * @return void
     */
    protected function register(): void {
        global $CFG, $SITE;

        $clientid = $this->issuer->get('clientid');
        $clientsecret = $this->issuer->get('clientsecret');

        // Registration request for getting client id and secret will be done only they are empty in the issuer.
        // For now this can't be run from PHPUNIT (because IMS testing platform needs real URLs). In the future, this
        // request can be moved to the moodle-exttests repository.
        if (empty($clientid) && empty($clientsecret) && (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST)) {
            $url = $this->get_registration_endpoint();
            if ($url) {
                $scopes = str_replace("\r", " ", $this->issuer->get('scopessupported'));

                // Add slash at the end of the site URL.
                $hosturl = $CFG->wwwroot;
                $hosturl .= (substr($CFG->wwwroot, -1) == '/' ? '' : '/');

                // Create the registration request following the format defined in the IMS OBv2.1 specification.
                $request = [
                    'client_name' => $SITE->fullname,
                    'client_uri' => $hosturl,
                    'logo_uri' => $hosturl . 'pix/f/moodle-256.png',
                    'tos_uri' => $hosturl,
                    'policy_uri' => $hosturl,
                    'software_id' => 'moodle',
                    'software_version' => $CFG->version,
                    'redirect_uris' => [
                        $hosturl . 'admin/oauth2callback.php'
                    ],
                    'token_endpoint_auth_method' => 'client_secret_basic',
                    'grant_types' => [
                        'authorization_code',
                        'refresh_token'
                    ],
                    'response_types' => [
                        'code'
                    ],
                    'scope' => $scopes
                ];
                $jsonrequest = json_encode($request);

                $this->curl->setHeader(['Content-type: application/json']);
                $this->curl->setHeader(['Accept: application/json']);

                // Send the registration request.
                if (!$jsonresponse = $this->curl->post($url, $jsonrequest)) {
                    $msg = 'Could not self-register identity issuer: ' . $this->issuer->get('name') .
                        ". Wrong URL or JSON data [URL: $url]";
                    throw new \moodle_exception($msg);
                }

                // Process the response and update client id and secret if they are valid.
                $response = json_decode($jsonresponse);
                if (property_exists($response, 'client_id')) {
                    $this->issuer->set('clientid', $response->client_id);
                    $this->issuer->set('clientsecret', $response->client_secret);
                } else {
                    $msg = 'Could not self-register identity issuer: ' . $this->issuer->get('name') .
                        '. Invalid response ' . $jsonresponse;
                    throw new \moodle_exception($msg);
                }
            }
        }
    }

    protected function get_registration_endpoint(): ?string {
        if (!empty($this->endpoints['registration_endpoint'])) {
            return ($this->endpoints['registration_endpoint'])->get('url');
        }
        return null;
    }
}
