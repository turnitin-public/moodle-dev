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
 * Contains the file_extension_handler interface.
 *
 * @package    core
 * @subpackage course
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\courseupload\handler;

/**
 * The file_extension_handler interface.
 *
 * Plugins return a collection of file_extension_handler type objects when they wish to register their intent to process files with
 * particular extensions.
 *
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface file_extension_handler {
    /**
     * @return string the file extension the handler would like to process, where '*' can be used to represent 'any extension'.
     */
    public function get_extension(): string;

    /**
     * Return a localised and human readable string describing how the processor handles files of this extension.
     *
     * E.g. "Add media to the course page".
     *
     * @return string
     */
    public function get_description(): string;

    /**
     * Return the fully qualified name of processor class which will process the extension
     *
     * E.g. 'mod_label\classes\courseupload\processors\file_extension_processor'.
     *
     * @return string
     */
    public function get_processor(): string;
}
