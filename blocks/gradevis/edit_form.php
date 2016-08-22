<?php

class block_gradevis_edit_form extends block_edit_form {

    protected function specific_definition($mform) {

        // Section header title according to language file.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Title.
        $mform->addElement('text', 'config_title', get_string('blocktitle', 'block_gradevis'));
        $mform->setDefault('config_title', get_string('defaulttitle', 'block_gradevis'));
        $mform->setType('config_title', PARAM_TEXT);

        // A sample string variable with a default value.
        $mform->addElement('text', 'config_text', get_string('blockstring', 'block_gradevis'));
        $mform->setDefault('config_text', get_string('defaulttext', 'block_gradevis'));
        $mform->setType('config_text', PARAM_RAW);
    }
}