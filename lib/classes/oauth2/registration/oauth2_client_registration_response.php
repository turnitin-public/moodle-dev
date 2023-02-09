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

use Psr\Http\Message\ResponseInterface;

/**
 * Models the response to an OAuth 2 Dynamic Client Registration request.
 *
 * This is a base, subclassed into either information response or error responses.
 *
 * Information response: {@see https://www.rfc-editor.org/rfc/rfc7591#section-3.2.1}
 * Error response: {@see https://www.rfc-editor.org/rfc/rfc7591#section-3.2.2}
 *
 * @package    core
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 abstract class oauth2_client_registration_response {
    /**
     * Factory method to create the appropriate response instance from an HTTP response.
     *
     * @param ResponseInterface $response the HTTP response data.
     * @return oauth2_client_registration_response an instance of a response subclass.
     */
    public static function from_response(ResponseInterface $response): self {
        $statuscode = $response->getStatusCode();
        if (in_array($statuscode, [201])) {
            return oauth2_client_registration_information_response::from_response($response);
        } else {
            return oauth2_client_registration_error_response::from_response($response);
        }
    }

    /**
     * Override to indicate success or failure of the response subtype.
     *
     * @return bool true for success, false for failure.
     */
    public abstract function is_successful(): bool;

     /**
      * Output the response as a psr7 HTTP response instance.
      *
      * @return ResponseInterface the response instance.
      */
    public abstract function to_response(): ResponseInterface;
}
