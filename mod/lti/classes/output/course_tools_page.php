<?php

namespace mod_lti\output;

use mod_lti\table\course_tools_table;

class course_tools_page implements \renderable {

    protected course_tools_table $coursetoolstable;

    protected course_tools_page_header $coursetoolspageheader;

    public function __construct(int $courseid, \moodle_url $pageurl) {
        global $DB;

        // Page intro, zero state and 'add new' button.
        $canadd = has_capability('mod/lti:addcoursetool', \context_course::instance($courseid));
        $toolcount = $DB->count_records_sql('SELECT COUNT(1) FROM {lti_types} tt WHERE tt.course in(:siteid, :courseid)',
            ['siteid' => get_site()->id, 'courseid' => $courseid]);
        $this->coursetoolspageheader = new course_tools_page_header($courseid, $toolcount, $canadd);

        // Table itself.
        $this->coursetoolstable = new course_tools_table(course_tools_table::ID_PREFIX . $courseid);
        $this->coursetoolstable->define_baseurl($pageurl);

    }

    public function get_header(): course_tools_page_header {
        return $this->coursetoolspageheader;
    }

    public function get_table(): course_tools_table {
        return $this->coursetoolstable;
    }

}
