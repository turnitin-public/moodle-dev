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

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

/**
 * Models an OAuth 2 Dynamic Client Registration request, facilitating creation of the HTTP request.
 *
 * {@see https://www.rfc-editor.org/rfc/rfc7591#section-3.1}
 *
 * @package    core
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth2_client_registration_request {

    /**
     * Constructor.
     *
     * @param \moodle_url $uri the URI where the registration request will be sent, aka the authorization service
     * @param oauth2_client_registration_metadata $metadata
     * @param string|null $bearertoken
     */
    public function __construct(
        protected \moodle_url $uri,
        protected oauth2_client_registration_metadata $metadata,
            protected ?string $bearertoken = null) {

        if (strtolower($uri->get_scheme()) !== 'https') {
            throw new \moodle_exception("Error: ". __METHOD__ . ": Bad URI scheme. Must be HTTPS.");
        }
    }

    /**
     * Export as an HTTP request.
     *
     * @return RequestInterface the request.
     */
    public function to_request(): RequestInterface {
        return new Request(
            'POST',
            $this->uri->out(false),
            [
                'Content-type' => 'application/json',
                'Accept' => 'application/json',
            ],
            json_encode($this->metadata->to_array())
        );
    }
}
