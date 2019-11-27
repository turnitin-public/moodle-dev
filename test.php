<?php

require_once('config.php');
require_once ($CFG->dirroot.'/lib/formslib.php');

class test_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        // Adv duration - optional.
        $mform->addElement('advduration', 'duration', 'Adv duration', ['units' => ['w','h', 'i'], 'optional' => true]);
        $mform->setType('duration', PARAM_RAW);

        if ($this->_customdata['freeze'] == 1) {
            $mform->freeze('duration');
        } else if ($this->_customdata['freeze'] == 2) {
            $mform->hardFreeze('duration');
        }

        // Inline version.
        $groupitems = [];
        $groupitems[] = $mform->createElement('advduration', 'durationinline', '', ['units' => ['w', 'd', 'h', 'i', 's']]);
        $groupitems[] = $mform->createElement('static', 'durationinline_break', null, ' after the start date');
        $mform->addGroup($groupitems, 'expirydategr', "Adv duration inline", array(' '), false);

        // Inline with optional.
        // TODO implement optional for inline element.
        $groupitems = [];
        $groupitems[] = $mform->createElement('advduration', 'durationinline2', '', ['optional' => true]);
        $groupitems[] = $mform->createElement('static', 'durationinline_break', null, ' after the start date');
        $mform->addGroup($groupitems, 'inlinegroup2', "Adv duration inline optional", array(' '), false);

        // Adv duration - mandatory and linked to disabledIf.
        $mform->addElement('checkbox', 'advcheck', 'Mandatory disabledIf - check me to enable');
        $mform->setType('advcheck', PARAM_BOOL);
        //$mform->disabledIf('advduration2[d]', 'testcheck');
        //$mform->disabledIf('advduration2[h]', 'testcheck');
        $mform->disabledIf('advduration2', 'advcheck');
        $mform->addElement('advduration', 'advduration2', 'Mandatory disabledIf');
        $mform->setType('advduration2', PARAM_RAW);
        if ($this->_customdata['freeze'] == 1) {
            $mform->freeze('advduration2');
        } else if ($this->_customdata['freeze'] == 2) {
            $mform->hardFreeze('advduration2');
        }

        // Adv duration - optional and linked to disabledIf.
        $mform->addElement('checkbox', 'advcheck2', 'check me to enable');
        $mform->setType('advcheck2', PARAM_BOOL);
        //$mform->disabledIf('advduration2[d]', 'testcheck');
        //$mform->disabledIf('advduration2[h]', 'testcheck');
        $mform->disabledIf('advduration3', 'advcheck2');
        $mform->addElement('advduration', 'advduration3', 'optional disabledif test', ['optional' => true]);
        $mform->setType('advduration3', PARAM_RAW);

        // Classic duration element - mandatory and linked to disabledif.
        $mform->addElement('checkbox', 'testcheck2', 'check me to enable');
        $mform->setType('testcheck2', PARAM_BOOL);
        $mform->disabledIf('duration2', 'testcheck2');
        $mform->addElement('duration', 'duration2', 'Classic duration element', ['optional' => false]);
        $mform->setType('duration2', PARAM_RAW);

        // Classic duration element - optional and linked to disabledif.
        $mform->addElement('checkbox', 'testcheck3', 'check me to enable');
        $mform->setType('testcheck3', PARAM_BOOL);
        $mform->disabledIf('duration3', 'testcheck3');
        $mform->addElement('duration', 'duration3', 'Classic duration element (optional)', ['optional' => true]);
        $mform->setType('duration3', PARAM_RAW);

        // Date selector dependant disabledif test.
        $mform->addElement('checkbox', 'dateselcheck', 'date sel check me to enable');
        $mform->setType('dateselcheck', PARAM_BOOL);
        $mform->disabledIf('datesel', 'dateselcheck');
        $mform->addElement('date_selector', 'datesel', '');


        // Date selector + checkbox in a wrapper group disabledif test.
        // This case is BROKEN as the element group 'datesel2' does not disable when the checkbox is toggled.
        $els[] = $mform->createElement('checkbox', 'dateselcheck2', 'known limitation: group within group');
        $mform->setType('dateselcheck', PARAM_BOOL);
        $els[] = $mform->createElement('date_selector', 'datesel2', '');

        $mform->addGroup($els, 'mygroup', '', '<br/>', false);
        $mform->disabledIf('datesel2', 'dateselcheck2');

        // Trying a disabledIf on the 'mygroup' instead.
        $mform->addElement('checkbox', 'groupcheck', 'disabledIf attached to group element instead');
        $mform->setType('groupcheck', PARAM_BOOL);
        $mform->disabledIf('mygroup', 'groupcheck');

        // Text input that is frozen, and uses disabledIf.
        $mform->addElement('text', 'mytext', 'Text disabledIf and frozen');
        $mform->setType('mytext', PARAM_RAW);
        $mform->addElement('checkbox', 'textcheck', 'Text input check me to enable');
        $mform->setType('textcheck', PARAM_BOOL);
        $mform->disabledIf('mytext', 'textcheck');
        if ($this->_customdata['freeze'] == 1) {
            $mform->freeze('mytext');
        } else if ($this->_customdata['freeze'] == 2) {
            $mform->hardFreeze('mytext');
        }

        //$mform->addElement('checkbox', 'testcheck', 'check');
        //$mform->setType('testcheck', PARAM_BOOL);
        //$mform->disabledIf('sg', 'testcheck');
        //$mform->addElement('selectgroups', 'sg', 'sg label', ['colors' => ['red' ,'green'], 'animals' => ['cat', 'dog']]);
        //$mform->setType('sg', PARAM_RAW);



        $this->add_action_buttons();
    }
}
$seconds = optional_param('seconds', 0, PARAM_INT);
$freeze = optional_param('freeze', 0, PARAM_INT);
$hardfreeze = optional_param('hardfreeze', 0, PARAM_INT);

$PAGE->set_pagelayout('admin');
$url = new moodle_url('/test.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());

$PAGE->set_heading('Test form');
$PAGE->set_title('Auto complete');

$customdata = [
    'freeze' => $freeze,
    'hardfreeze' => $hardfreeze
];

$form = new test_form(null, $customdata);

$form->set_data((object)[
    'duration' => $seconds,
    'durationinline' => $seconds
]);

$result = '';
if ($form->is_cancelled()) {
    redirect($url);
} else if ($data = $form->get_data()) {
    $result = "<pre>" . print_r($data, true) . "</pre>";
}

echo $OUTPUT->header();
echo $result;
$form->display();
echo $OUTPUT->footer();
