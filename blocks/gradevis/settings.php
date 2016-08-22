<?php

$settings->add(new admin_setting_heading(
    'headerconfig',
    get_string('headerconfig', 'block_gradevis'),
    get_string('descconfig', 'block_gradevis')
));

$settings->add(new admin_setting_configcheckbox(
    'gradevis/Allow_HTML',
    get_string('labelallowhtml', 'block_gradevis'),
    get_string('descallowhtml', 'block_gradevis'),
    '0'
));
