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
 * Contains the MoodleQuickForm_number class.
 *
 * @package   core_form
 * @copyright 2019 Jake Dallimore <jrhdallimore@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("HTML/QuickForm/input.php");
require_once('templatable_form_element.php');

/**
 * Class representing a number type input element
 *
 * @package   core_form
 * @category  form
 * @copyright 2019 Jake Dallimore <jrhdallimore@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class MoodleQuickForm_number extends HTML_QuickForm_input implements templatable {

    use templatable_form_element {
        export_for_template as export_for_template_base;
    }

    protected $_min;
    protected $_max;
    protected $_text;

    /**
     * Constructor.
     *
     * @param string $elementName (optional) name of the text field
     * @param string $elementLabel (optional) text field label describing the field
     * @param string $text (optional) the text following the field (when not grouped) or under the field (when part of a group).
     * @param string $attributes (optional) Either a typical HTML attribute string or an associative array
     * @param null $options
     */
    public function __construct($elementName=null, $elementLabel=null, $text = '', $attributes=null, $options = null) {
        parent::__construct($elementName, $elementLabel, $attributes);
        $this->setPersistantFreeze(true); // Allow this element to post data when frozen (readonly), but not when hardfrozen.
        $this->_type = 'number';
        $this->_min = $options['min'] ?? null;
        $this->_max = $options['max'] ?? null;
        $this->_text = $text;
    }

    /**
     * Force the field to flow left-to-right.
     *
     * This is useful for fields such as URLs, passwords, settings, etc...
     *
     * @param bool $value The value to set the option to.
     */
    public function set_force_ltr($value) {
        $this->forceltr = (bool) $value;
    }

    public function export_for_template(renderer_base $output) {
        $context = $this->export_for_template_base($output);

        $context['min'] = $this->_min;
        $context['max'] = $this->_max;
        $context['text'] = $this->_text;

        return $context;
    }

}
