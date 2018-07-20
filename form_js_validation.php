<?php

require_once('config.php');
require_once ($CFG->dirroot.'/lib/formslib.php');

class mod_test_mod_form extends moodleform {
    function definition() {
        $mform = $this->_form;
        $scenario = $this->_customdata['scenario'];
        $theme = $this->_customdata['theme'];

        $mform->addElement('hidden', 'scenario', $scenario);
        $mform->setType('scenario', PARAM_INT);

        $mform->addElement('hidden', 'theme', $theme);
        $mform->setType('theme', PARAM_TEXT);


        // Normal element without escapable chars.
        // Boost: Client validation success.
        // Clean: Client validation success.
        if ($scenario == 1) {
            $mform->addElement('text', 'text3', 'My text element - normal');
            $mform->setType('text3', PARAM_TEXT);
            $mform->addRule('text3', null, 'required', null, 'client');
        }

        // Normal element with escapable char (underscore).
        // Boost: Client validation failure - escaped name mismatch.
        // Clean: Client validation success.
        if ($scenario == 2) {
            $mform->addElement('text', 'text[4]', 'My text element - normal');
            $mform->setType('text[4]', PARAM_TEXT);
            $mform->addRule('text[4]', null, 'required', null, 'client');
        }

        // A group of 2 elements without escapable chars, using the per-group rule (group validated as one).
        // Clean: Client validation failure, though onblur works.
        // Boost: Client validation failure, console error on page load relating to assigning blur/change events.
        if ($scenario == 3) {
            $group = [];
            $group[] = $mform->createElement('checkbox', 'text1', "My grouped text element", 'blue');
            $group[] = $mform->createElement('checkbox', 'text2', "My grouped text element", 'red');

            $mform->addGroup($group, 'testgroup', 'A group of elements');

            $mform->addRule('testgroup', 'An element from this group is required', 'required', null, 'client');
        }

        // A group of 2 elements without escapable chars, using the per-group rule (group validated as one) with escapable char.
        // Clean: Client validation failure, though onblur works.
        // Boost: Client validation failure, console error on page load relating to assigning blur/change events.
        if ($scenario == 4) {
            $group = [];
            $group[] = $mform->createElement('checkbox', 'text1', "My grouped text element");
            $group[] = $mform->createElement('checkbox', 'text2', "My grouped text element");

            $mform->addGroup($group, 'test_group', 'A group of elements');

            $mform->addRule('test_group', 'This group is required', 'required', null, 'client');
        }

        // A group of 2 elements using escapable chars, using the per-group rule (group validated as one) without escapable char.
        // Clean: Client validation failure, though onblur works.
        // Boost: Client validation failure, console error on page load relating to assigning blur/change events.
        if ($scenario == 5) {
            $group = [];
            $group[] = $mform->createElement('checkbox', 'text_1', "My grouped text element");
            $group[] = $mform->createElement('checkbox', 'text_2', "My grouped text element");

            $mform->addGroup($group, 'testgroup', 'A group of elements');

            $mform->addRule('testgroup', 'This group is required', 'required', null, 'client');
        }


        // A group of 2 elements without escapable chars, using the per-element group rule.
        // Clean: Client validation failure, no console errors.
        // Boost: Client validation failure, console error on page load relating to assigning blur/change events.
        if ($scenario == 6) {
            $group = [];
            $group[] = $mform->createElement('checkbox', 'text1', "My grouped text element", 'Red');
            $group[] = $mform->createElement('checkbox', 'text2', "My grouped text element", 'Blue');

            $mform->addGroup($group, 'testgroup', 'A group of elements');

            //$grprules['testgroup'][] = [null, 'required', null, 'client'];
            $mform->addGroupRule('testgroup', 'At least one element required', 'required', null, 1, 'client');
        }

        // More involved, per element group rule validation scenario.
        // Clean: Client validation success on the required field.
        // Boost: Client validation success on the required field.
        // THIS IS THE REASON FOR THE _%2x hacks!! That replaces things like validation_xx_details[name]() with a workable name.
        if ($scenario == 7) {
            $group = [];
            $group[] = $mform->createElement('text', 'lastname', 'Name', array('size' => 30));
            $group[] = $mform->createElement('text', 'code', 'Code', array('size' => 5, 'maxlength' => 4));

            $mform->addGroup($group, 'testgroup2', 'advanced group validation test');

            $mform->setType('testgroup2[lastname]', PARAM_TEXT);
            $mform->setType('testgroup2[code]', PARAM_TEXT);

            // Complex rule for group's elements
            $mform->addGroupRule('testgroup2', array(
                'lastname' => array(
                    array('Name is letters only', 'lettersonly'),
                    array('Name is required', 'required', null, 'client')
                ),
                'code' => array(
                    array('Code must be numeric', 'numeric')
                )
            ));
        }

        // Another simple group test, this time with required text field elements.
        // Boost: Client side validation success.
        // Clean: Client side validation success.
        if ($scenario == 8) {
            $group = [];
            $group[] = $mform->createElement('text', 'text1', "My grouped text element", 'Red');
            $group[] = $mform->createElement('text', 'text2', "My grouped text element", 'Blue');

            $mform->addGroup($group, 'testgroup', 'A group of elements');

            $mform->setType('testgroup[text1]', PARAM_TEXT);
            $mform->setType('testgroup[text2]', PARAM_TEXT);

            //$grprules['testgroup'][] = [null, 'required', null, 'client'];
            $mform->addGroupRule('testgroup', [
                'text1' => [
                    ['Name is letters only', 'lettersonly', null, 'client'],
                    ['Max length of 4 chars', 'maxlength', 4, 'client'],
                    ['Name is required', 'required', null, 'client']
                ],
                'text2' => [
                    ['Text2 is numeric', 'numeric', null, 'client'],
                    ['Text2 is required', 'required', null, 'client']
                ]
            ]);
        }


        $this->add_action_buttons(true, 'Submit');
    }
}

$scenario = optional_param('scenario', 1, PARAM_INT);
$theme = optional_param('theme', 'boost', PARAM_TEXT);

$url = new moodle_url('/form_js_validation.php');

$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_heading("Testing multiple URL elements in an form");

$form = new mod_test_mod_form(null, ['scenario' => $scenario, 'theme' => $theme]);

if ($form->is_cancelled()) {
    echo "In here";
    redirect($url);
} else if ($data = $form->get_data()) {
    $result = "<pre>" . print_r($data, true) . "</pre>";
    $url = new moodle_url('/form_js_validation.php?scenario', ['theme' => $theme, 'scenario' => $scenario]);
    redirect($url, $result, null, \core\output\notification::NOTIFY_INFO);

} else {
    echo $OUTPUT->header();
    $form->display();
    echo $OUTPUT->footer();
}
