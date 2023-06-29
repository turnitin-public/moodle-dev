<?php

namespace mod_lti\output;

use core\output\notification;
use renderer_base;

class course_tools_page_header implements \templatable {

    public function __construct(protected int $courseid, protected int $toolcount, protected bool $canadd) {
    }

    public function export_for_template(renderer_base $output) {

        $context = (object) [];

        if ($this->canadd) {
            $context->addlink = (new \moodle_url('/mod/lti/coursetooledit.php', ['course' => $this->courseid]))->out();
        }

        if ($this->toolcount == 0) {
            $notification = new notification(get_string('nocourseexternaltoolsnotice', 'mod_lti'), notification::NOTIFY_INFO, true);
            $context->notoolsnotice = $notification->export_for_template($output);
        }

        return $context;
    }
}
