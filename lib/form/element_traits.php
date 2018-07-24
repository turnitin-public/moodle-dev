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
 * Provides a trait used to override some methods in the lib/pear/HTML/QuickForm/element.php base class, namely:
 * - _generateId(): overriding with a non static $idx val, and incorporating name - see MDL-30168 for details of why.
 *
 *
 * @package   core_form
 * @copyright 2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

trait form_element {
    /**
     * Automatically generates and assigns an 'id' attribute for the element.
     *
     * Currently used to ensure that labels work on radio buttons and
     * checkboxes. Per idea of Alexander Radivanovich.
     *
     * @access private
     * @return void
     */
    function _generateId() {
        if ($this->getAttribute('id')) {
            return;
        }

        $id = $this->getName();
        $id = 'id_' . str_replace(array('qf_', '[', ']'), array('', '_', ''), $id);
        $id = clean_param($id, PARAM_ALPHANUMEXT);
        $this->updateAttributes(array('id' => $id));
    }
}

trait element_radio {
    /**
     * Automatically generates and assigns an 'id' attribute for the element.
     *
     * Currently used to ensure that labels work on radio buttons and
     * checkboxes. Per idea of Alexander Radivanovich.
     *
     * @access private
     * @return void
     */
    function _generateId() {
        // Override the standard implementation, since you can have multiple
        // check-boxes with the same name on a form. Therefore, add the
        // (cleaned up) value to the id.

        if ($this->getAttribute('id')) {
            return;
        }

        $id = $this->getName();
        $id = 'id_' . str_replace(array('qf_', '[', ']'), array('', '_', ''), $id);
        $id = clean_param($id, PARAM_ALPHANUMEXT);

        $id = $id . '_' . clean_param($this->getValue(), PARAM_ALPHANUMEXT);
        $this->updateAttributes(array('id' => $id));
    }
}
