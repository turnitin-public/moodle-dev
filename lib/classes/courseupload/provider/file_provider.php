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

/**
 * Contains the file_provider interface, providing the contract for file providers in the context of course upload.
 *
 * @package    core
 * @subpackage course
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\courseupload\provider;

defined('MOODLE_INTERNAL') || die();

/**
 * The file_provider interface, providing the contract for file providers in the context of course upload.
 *
 * Plugins must implement this to provide:
 * - Information about the file they wish to upload to the course and have processed by mod plugins and
 *   This information includes:
 *   - Filename
 *   - File extension, so that courseupload can determine appropriate processors by extension.
 *
 * - A means to get the file, as a \stored_file object, when the time comes for a module to process the file.
 *
 * @package core\courseupload
 */
interface file_provider {

    /**
     * Returns the file being provided, as a stored_file object.
     *
     * @return \stored_file
     */
    public function get_file(): \stored_file;

    /**
     * Returns the name of the file being provided for upload.
     *
     * @return string
     */
    public function get_filename(): string;

    /**
     * Return the mimetype of the file, if known, otherwise null.
     *
     * @return string|null
     */
    public function get_mimetype(): ?string;

    /**
     * Return the extension of the file, if known, otherwise null.
     *
     * @return string|null
     */
    public function get_extension(): ?string;
}
