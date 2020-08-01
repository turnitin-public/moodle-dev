<?php
namespace IMSGlobal\LTI13;

use \Firebase\JWT\JWT;

class JWKS_Endpoint {

    private $keys;

    public function __construct(array $keys) {
        $this->keys = $keys;
    }

    public static function new($keys) {
        return new JWKS_Endpoint($keys);
    }

    public static function from_issuer(Database $database, $issuer) {
        $registration = $database->find_registration_by_issuer($issuer);
        return new JWKS_Endpoint([$registration->get_kid() => $registration->get_tool_private_key()]);
    }

    public static function from_registration(LTI_Registration $registration) {
        return new JWKS_Endpoint([$registration->get_kid() => $registration->get_tool_private_key()]);
    }

    public function get_public_jwks() {
        $jwks = [];
        foreach ($this->keys as $kid => $private_key) {
            $key_res = openssl_pkey_get_private($private_key);
            $key_details = openssl_pkey_get_details($key_res);

            $components = array(
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'e' => JWT::urlsafeB64Encode($key_details['rsa']['e']),
                'n' => JWT::urlsafeB64Encode($key_details['rsa']['n']),
                'kid' => $kid,
            );
            $jwks[] = $components;
        }
        return ['keys' => $jwks];
    }

    public function output_jwks() {
        echo json_encode($this->get_public_jwks());
    }

}
