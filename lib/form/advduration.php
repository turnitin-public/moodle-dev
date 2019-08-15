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
 * Contains the MoodleQuickForm_advduration class, for creating duration pickers in Moodle.
 *
 * @package   core_form
 * @copyright 2019 Jake Dallimore <jrhdallimore@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;
require_once($CFG->libdir . '/form/group.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * A form element for selecting a period of time.
 *
 * This element allows users to select a period of time using the following periods:
 * - weeks
 * - days
 * - hours
 * - minutes
 * - seconds
 * The element supports any combination of the above periods, allowing users to create both simple and complex elements, like:
 * - Single-period input elements (for example, an hours selector or days selector),
 * - Multi-period input elements (for example, a days, hours, minutes selector or a weeks, hours, days selector)
 *
 * The value returned by the element is duration in seconds.
 *
 * @package   core_form
 * @copyright 2019 Jake Dallimore <jrhdallimore@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class MoodleQuickForm_advduration extends MoodleQuickForm_group {
    /** @var int the duration, in seconds */
    protected $value = null;

    /** @var array $options the array of options controlling the display for this element.*/
    protected $options;

    /** @var array $defaultoptions The array of default values for the options controlling the element's display.*/
    protected static $defaultoptions = ['optional' => false, 'units' => ['d', 'h', 'i']];

    /** @var array $supportedunits assoc array of supported increments and their respective second values, in order. */
    protected static $supportedunits = ['w' => 604800, 'd' => 86400, 'h' => 3600, 'i' => 60, 's' => 1];

    /**
     * Constructor for the advduration element.
     *
     * @param string $elementName Element's name
     * @param mixed $elementLabel Label(s) for an element
     * @param array $options Options to control the element's display. Recognised values are
     *   'optional' => true/false - whether to display an 'enabled' checkbox next to the element.
     *   'units'    => an array of characters represeting the period selectors to display. See the $supportedunits instance var
     *     for the list of valid values.
     * @param mixed $attributes Either a typical HTML attribute string or an associative array
     */
    public function __construct($elementName = null, $elementLabel = null, $options = [], $attributes = null) {
        parent::__construct($elementName, $elementLabel, $attributes);

        $this->_persistantFreeze = true;
        $this->appendName = true;
        $this->_type = 'advduration';

        // Merge config options with local defaults.
        $options = $options ?? [];
        $this->set_options($options);
    }

    function accept(&$renderer, $required = false, $error = null) {
        $renderer->renderElement($this, $required, $error);
    }

    function _createElements() {
        $this->_elements = [];

        // TODO: what attributes can a group support, generally speaking?
        $atts =  [];

        foreach ($this->options['units'] as $unit) {
            switch ($unit) {
                case 'w':
                    $this->_elements[] = $this->createFormElement('number', 'w', '', get_string('weeks', 'moodle'), $atts,
                        ['min' => -1]);
                    break;
                case 'd':
                    $this->_elements[] = $this->createFormElement('number', 'd', '', get_string('days', 'moodle'), $atts,
                        ['min' => -1]);
                    break;
                case 'h':
                    $this->_elements[] = $this->createFormElement('number', 'h', '', get_string('hours', 'moodle'), $atts,
                        ['min' => -1]);
                    break;
                case 'i':
                    $this->_elements[] = $this->createFormElement('number', 'i', '', get_string('minutes', 'moodle'), $atts,
                        ['min' => -1]);
                    break;
                case 's':
                    $this->_elements[] = $this->createFormElement('number', 's', '', get_string('seconds', 'moodle'), $atts,
                        ['min' => -1]);
                    break;
            }

            // TODO: call setHiddenLabel on number if it makes sense to even have that method.
        }

        // TODO: Check that the attributes are needed here.
        if($this->options['optional']) {
            $this->_elements[] = $this->createFormElement('checkbox', 'enabled', null, get_string('enable'),
                $this->getAttributes(), true);
        }

    }

    /**
     * Output a timestamp. Give it the name of the group.
     * Override of standard quickforms method.
     *
     * @param  array $submitValues
     * @param  bool $assoc if true the returned value is an associative array
     * @return array field name => value. The value is the time interval in seconds.
     */
    public function exportValue(&$submitValues, $assoc = false) {
        // Lookup the seconds value of each unit, multiply by the submitted value to get the seconds count for that field,
        // and then add to the sum total.
        $totalsecs = 0;

        // A hard frozen element won't submit any values, so we need to check existence.
        /*if (isset($submitValues[$this->getName()])) {
            foreach ($submitValues[$this->getName()] as $unit => $val) {
                if ($unit == 'enabled') continue;
                $totalsecs += (self::$supportedunits[$unit] * (int)$val);
            }
        }*/
        $values = [];
        foreach ($this->_elements as $element) {
            $export = $element->exportValue($submitValues[$this->getName()], true);
            $values[$element->getName()] = is_null($export) ? $export : array_pop($export);
            if ($element->getName() != "enabled") {
                $totalsecs += $values[$element->getName()];
            }
        }
        if (empty($values) || ($this->options['optional'] && empty($values['enabled']))) {
            return $this->_prepareValue(null, $assoc);
        }

        return $this->_prepareValue($totalsecs, $assoc);
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return array the context data for the template.
     */
    /*public function export_for_template(renderer_base $output) {
        $context = $this->export_for_template_base($output);

        // Do we have a value for the element? If so, translate it into its constituent units for display.
        $value = $this->getValue() ?? 0;
        $unitvalues = $this->value_to_unit_values($value);

        foreach ($this->options['units'] as $unit) {
            $context[$unit] = ['value' => $unitvalues[$unit]];
        }
        return $context;
    }*/


    public function onQuickFormEvent($event, $arg, &$caller) {
        switch ($event) {
            case 'updateValue':
                /*// constant values override both default and submitted ones
                // default values are overriden by submitted
                $value = $this->_findValue($caller->_constantValues);
                if (null === $value) {
                    $value = $this->_findValue($caller->_submitValues);
                    if (null === $value) {
                        $value = $this->_findValue($caller->_defaultValues);
                    }
                }
                // Convert the value from the submit values if need be.
                if (is_array($value)) {
                    $value = $this->exportValue($caller->_submitValues);
                }
                if (null !== $value) {
                    $this->setValue($value);
                }
                return true;
                */
                $value = $this->_findValue($caller->_constantValues);
                if (null === $value) {
                    if ($caller->isSubmitted()) {
                        $value = $this->_findValue($caller->_submitValues);
                    } else {
                        $value = $this->_findValue($caller->_defaultValues);
                    }
                }
                // On load.
                if (!is_array($value)) {
                    $value = $value ?? 0;
                    $secondval = $value;
                    $value = $this->value_to_unit_values($value);

                    // If optional, default to off, unless a duration was provided.
                    if ($this->options['optional']) {
                        $value['enabled'] = $secondval != 0;
                    }
                } else {
                // On submit.
                    $value['enabled'] = isset($value['enabled']);
                }
                if (null !== $value){
                    $this->setValue($value);
                }
                break;
            case 'createElement':
                $optional = $arg[2]['optional'] ?? false;
                if ($optional) {
                    $caller->disabledIf($arg[0], $arg[0] . '[enabled]');
                }
                return parent::onQuickFormEvent($event, $arg, $caller);
            default:
                return parent::onQuickFormEvent($event, $arg, $caller);
        }
    }

    /**
     * Sets the display options for the element, by merging and validating user-specified options with the default values.
     *
     * @param array $options the user display options.
     * @throws coding_exception if one or more of the user-specified options is invalid.
     */
    protected function set_options(array $options): void {
        $this->options = array_merge(self::$defaultoptions, $options);

        // Give the units array string keys so we can
        $this->options['units'] = array_combine($this->options['units'], $this->options['units']);


        // Validate the 'units' option against the supported unit values.
        $maxunitvalue = 0;
        $minunitvalue = 604801;
        foreach ($this->options['units'] as $unit) {
            if (!array_key_exists($unit, self::$supportedunits)) {
                throw new coding_exception($unit . ' is not a supported unit in MoodleQuickForm_advduration.');
            }
            if (self::$supportedunits[$unit] > $maxunitvalue) {
                $maxunitvalue = self::$supportedunits[$unit];
            }
            if (self::$supportedunits[$unit] < $minunitvalue) {
                $minunitvalue = self::$supportedunits[$unit];
            }
        }
        // Make sure we have a set of consecutive units. E.g. h,i,s or w,d,h.
        $this->add_missing_units($minunitvalue, $maxunitvalue);
    }

    /**
     * Adds any missing units to the options, ensuring a consecutive group of units in the options.
     *
     * @param int $minunit the minimum unit found in the options.
     * @param int $maxunit the maximum unit found in the options.
     */
    protected function add_missing_units(int $minunit, int $maxunit) {
        $this->options['units'] = [];
        foreach (self::$supportedunits as $unit => $value) {
            if ($value <= $maxunit && $value >= $minunit) {
                $this->options['units'][] = $unit;
            }
        }
    }

    /**
     * Converts an integer number of seconds to the most suitable combination of supported units.
     *
     * Returns an associative array of unit chars and values. For example:
     * $units = value_to_unit_values(60);
     * returns:
     * ['w' => 0, 'd' => 0, 'h' => 0, 'i' => 1, 's' => 0]
     * indicating that the value of 60 seconds is equal to 1 minute.
     *
     * @param int $seconds an amount of time in seconds.
     * @return array associative array ($number => $unit)
     */
    protected function value_to_unit_values(int $seconds): array {
        $datetimefrom = new \DateTime('@0');
        $datetimeto = new \DateTime("@$seconds");
        $values = array_fill_keys(array_keys(self::$supportedunits), 0);

        // DateInterval can't output weeks diff, so calculate it.
        $dateinterval = $datetimefrom->diff($datetimeto);
        $values['w'] = (int) floor($dateinterval->format('%a') / 7);
        $values['d'] = (int) $dateinterval->format('%a') % 7;
        $values['h'] = (int) $dateinterval->format('%h');
        $values['i'] = (int) $dateinterval->format('%i');
        $values['s'] = (int) $dateinterval->format('%s');

        return $values;
    }
}
