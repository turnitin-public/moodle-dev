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
 * Contains the file_processor interface.
 *
 * @package    core
 * @subpackage course
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\courseupload\processor;

use core\courseupload\provider\file_provider;

/**
 * The file_processor interface, describing the behaviour of file_processors.
 *
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface file_processor {
    /**
     * Static factory method, allowing core to ask the registered processor for an instance when required.
     *
     * @return file_processor an instance of a file_processor.
     */
    public static function getInstance(): file_processor;

    /**
     * Process the file encapsulated by the file_provider, creating the course module and returning its instance id.
     *
     * @param file_provider $fileprovider a file_provider object.
     * @return int the id of the course module which has been created.
     */
    public function process_file(file_provider $fileprovider): int;
}
