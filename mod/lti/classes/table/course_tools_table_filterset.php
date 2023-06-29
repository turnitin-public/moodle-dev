<?php

namespace mod_lti\table;

use core_table\local\filter\filterset;

class course_tools_table_filterset  extends filterset {

    /**
     * Get the required filters
     *
     * @return array.
     */
    public function get_required_filters(): array {
        return [];
    }

}
