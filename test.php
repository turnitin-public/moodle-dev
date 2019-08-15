<?php

require_once('config.php');
require_once ($CFG->dirroot.'/lib/formslib.php');

class test_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        // Client side rules verify change reapplied from MDL-52826.
        // Using the multibyte chars as per testing instructions verifies change reapplied from MDL-40267.
        $mform->addElement('advduration', 'duration', 'Adv duration', ['units' => ['w','h', 'i']]);
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


        // Mandatory duration classic.
        $mform->addElement('duration', 'duration2', 'Classic duration element', ['optional' => false]);
        $mform->setType('duration2', PARAM_RAW);

        // Optional duration classic.
        $mform->addElement('duration', 'duration3', 'Classic duration element (optional)', ['optional' => true]);
        $mform->setType('duration3', PARAM_RAW);

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
