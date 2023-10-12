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
 * ltixformslib.php - library of classes for creating lti forms in Moodle, based on PEAR QuickForms.
 *
 *
 * @package   core_ltix
 * @copyright 2023 TII
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
use core_ltix\ltix_helper;
global $CFG;


/**
 * Adds all the elements to a form for the lti module.
 */
function attach_lti_elements($mform, $context, $_instance, $current) {
    global $COURSE, $CFG, $DB, $OUTPUT, $PAGE;


    // Determine whether this tool instance is using a domain-matched site tool which is not visible at the course level.
    // In such a case, the instance has a typeid (the site tool) and toolurl (the url used to domain match the site tool) set,
    // and the type still exists (is not deleted).
    $instancetypes = ltix_helper::lti_get_types_for_add_instance();
    $matchestoolnotavailabletocourse = false;

    $tooltypeid = $current->typeid;
    $tooltype = ltix_helper::lti_get_type($tooltypeid);

    // Store the id of the tool type should it be linked to a tool proxy, to aid in disabling certain form elements.
    $toolproxytypeid = $tooltype->toolproxyid ? $tooltypeid : '';

    $issitetooltype = $tooltype->course == get_site()->id;

    //$mform =& $this->_form;

    $showtypes = has_capability('moodle/ltix:addpreconfiguredinstance', $context);

    if($showtypes) {
        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('html', "<div data-attribute='dynamic-import' hidden aria-hidden='true' role='alert'></div>");
        $mform->addElement('header', 'externaltool', get_string('externaltool', 'ltix'));

        //Dropdown for preconfigured tools.
        $tooltypes = $mform->addElement('select', 'externaltooltypeid', get_string('external_tool_type', 'ltix'));

        $mform->addHelpButton('externaltooltypeid', 'external_tool_type', 'ltix');

        foreach (ltix_helper::lti_get_types_for_add_instance() as $id => $type) {
            if (!empty($type->toolproxyid)) {
                $toolproxy[] = $type->id;
                $attributes = array('globalTool' => 1, 'toolproxy' => 1);
                $enabledcapabilities = explode("\n", $type->enabledcapability);
                if (!in_array('Result.autocreate', $enabledcapabilities) ||
                    in_array('BasicOutcome.url', $enabledcapabilities)) {
                    $attributes['nogrades'] = 1;
                }
                if (!in_array('Person.name.full', $enabledcapabilities) &&
                    !in_array('Person.name.family', $enabledcapabilities) &&
                    !in_array('Person.name.given', $enabledcapabilities)) {
                    $attributes['noname'] = 1;
                }
                if (!in_array('Person.email.primary', $enabledcapabilities)) {
                    $attributes['noemail'] = 1;
                }
            } else if ($type->course == $COURSE->id) {
                $attributes = array('editable' => 1, 'courseTool' => 1, 'domain' => $type->tooldomain);
            } else if ($id != 0) {
                $attributes = array('globalTool' => 1, 'domain' => $type->tooldomain);
            } else {
                $attributes = array();
            }

            if ($id) {
                $config = ltix_helper::lti_get_type_config($id);
                if (!empty($config['contentitem'])) {
                    $attributes['data-contentitem'] = 1;
                    $attributes['data-id'] = $id;
                } else {
                    $noncontentitemtypes[] = $id;
                }
            }
            $tooltypes->addOption($type->name, $id, $attributes);
        }
    }



    // Add standard elements, common to all modules.
    //$this->standard_coursemodule_elements();
    //$mform->setAdvanced('cmidnumber');

    // Add standard buttons, common to all modules.
    //$this->add_action_buttons();




    //parent::__construct($current, $section, $cm, $course);
}


