<?php

namespace core\oauth2\discovery;

interface configuration_discovery {
    public function read_configuration(): \stdClass; // Read and return the remote server config.
    public function get_endpoints(): array; // associative array of string endpoints, [name => url]
}
