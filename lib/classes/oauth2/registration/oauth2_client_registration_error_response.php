<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace core\oauth2\registration;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Representation of an error response to an OAuth2 Dynamic Client Registration request, containing error information.
 *
 * {@see https://www.rfc-editor.org/rfc/rfc7591#section-3.2.2}
 *
 * @package    core
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth2_client_registration_error_response extends oauth2_client_registration_response {

    /**
     * Constructor.
     *
     * @param array $errorinfo array of error information.
     * @param int $statuscode the HTTP status code.
     */
    public function __construct(protected array $errorinfo, protected int $statuscode = 400) {
    }

    /**
     * Factory method to get an instance from the HTTP error response to an OAuth 2 Dynamic Client Registration request.
     *
     * @param ResponseInterface $response the HTTP error response to an OAuth 2 Dynamic Client Registration request.
     * @return oauth2_client_registration_error_response
     * @throws \moodle_exception if the response is incomplete or represents a valid information response.
     */
    public static function from_response(ResponseInterface $response): self {
        $statuscode = $response->getStatusCode();
        if (in_array($statuscode, [201])) {
            throw new \moodle_exception('Error: '. __METHOD__ . ': Bad status code. Must NOT be 201.');
        }

        $responsebody = $response->getBody()->getContents();
        $decodedbody = json_decode($responsebody, true);
        if (is_null($decodedbody)) {
            throw new \moodle_exception('Error: '. __METHOD__ . ': Failed to decode response body. Invalid JSON.');
        }

        return new self($decodedbody, $statuscode);
    }

    public function is_successful(): bool {
        return false;
    }

    public function to_response(): ResponseInterface {
        return new Response(
            $this->statuscode,
            ['Cache-control' => 'no-store', 'Pragma' => 'no-cache', 'Content-type' => 'application/json'],
            json_encode($this->errorinfo)
        );
    }

    /**
     * Return this response's error data as an array.
     *
     * @return array the error info.
     */
    public function get_error_info(): array {
        return $this->errorinfo;
    }
}
