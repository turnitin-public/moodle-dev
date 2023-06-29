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

namespace mod_lti\table;

use context;
use core_table\dynamic;
use renderable;
use table_sql;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * A table listing all LTI tools available in a course.
 *
 * This table will include both:
 * - Tools configured at the course level and;
 * - Tools configured at the site level which are available in the course
 *
 * Actions for each tool depend on the level at which the tool is defined:
 * - Edit (if the tool is configured at course level and the user has permissions)
 * - Delete (if the tool is configured at course level and the user has permissions)
 * - Registration details (for LTI 1.3 tools only and only when the tool is a course-level tool)
 *
 * Tools defined at system level but which are available for use in the course will be listed, but will have locked actions.
 *
 * @package mod_lti
 * @copyright 2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_tools_table extends table_sql implements dynamic, renderable {
    /** @var string prefix for the uniqueid, allowing the retrival of the course id. */
    const ID_PREFIX = 'course-external-lti-tools-';

    /** @var int the course id. */
    protected int $courseid;

    /**
     * Constructor.
     *
     * @param string $uniqueid a unique id for the table, which must be of the form 'ID_PREFIX-{courseid}' for dynamic features.
     */
    public function __construct(string $uniqueid) {
        $this->courseid = (int) substr($uniqueid, strlen(self::ID_PREFIX));
        parent::__construct($uniqueid);

        $this->sql = (object) [
            'fields' => 'tt.id, tt.name, tt.icon, tt.description, tt.course, COUNT(ti.id) AS usage',
            'from' => '{lti_types} tt LEFT JOIN {lti} ti ON (ti.typeid = tt.id)',
            'where' => 'tt.course IN(:siteid, :courseid) GROUP BY tt.id',
            'params' => ['siteid' => get_site()->id, 'courseid' => $this->courseid]
        ];

        $this->set_count_sql(
            'SELECT COUNT(1) FROM {lti_types} tt WHERE tt.course in(:siteid, :courseid)',
            ['siteid' => get_site()->id, 'courseid' => $this->courseid]
        );

        $columns = [
            ['name' => 'name', 'header' => get_string('name', 'core')],
            ['name' => 'description', 'header' => get_string('description', 'core')],
            ['name' => 'usage', 'header' => get_string('usage', 'mod_lti')],
            ['name' => 'actions', 'header' => get_string('actions', 'core')],
        ];

        $this->define_columns(array_column($columns, 'name'));
        $this->define_headers(array_column($columns, 'header'));
        $this->define_header_column('name');

        $this->sortable(true, 'name');
        $this->no_sorting('actions');
        $this->set_default_per_page(10);
        $this->set_filterset(new course_tools_table_filterset());

        $this->attributes['class'] = 'flexible table';
        $this->attributes['id'] = 'course-tools';
    }

    /**
     * Override, required for dynamic table behaviours to work.
     *
     * @return void
     */
    public function guess_base_url(): void {
        //TODO this seems like a hack
        $this->baseurl = new \moodle_url('/');
    }

    /**
     * Override, required for dynamic table behaviours to work.
     *
     * @return context
     */
    public function get_context(): context {
        return \context_course::instance($this->courseid);
    }

    /**
     * Override to make sure show/hide links are not used for the column headers in this table.
     *
     * @param string $column the column name, index into various names.
     * @param int $index numerical index of the column.
     * @return string HTML.
     */
    protected function show_hide_link($column, $index) {
        return '';
    }

    /**
     * Override, which does everything that flexible table does, less the 'no-overflow' class on the container.
     *
     * This override is required to ensure the table container DOES overflow, so that actions for the last item are visible.
     *
     * @return void
     */
    public function start_html() {
        global $OUTPUT;

        // Render the dynamic table header.
        echo $this->get_dynamic_table_html_start();

        // Render button to allow user to reset table preferences.
        echo $this->render_reset_button();

        // Do we need to print initial bars?
        $this->print_initials_bar();

        // Paging bar
        if ($this->use_pages) {
            $pagingbar = new \paging_bar($this->totalrows, $this->currpage, $this->pagesize, $this->baseurl);
            $pagingbar->pagevar = $this->request[TABLE_VAR_PAGE];
            echo $OUTPUT->render($pagingbar);
        }

        if (in_array(TABLE_P_TOP, $this->showdownloadbuttonsat)) {
            echo $this->download_buttons();
        }

        $this->wrap_html_start();
        // Start of main data table

        echo \html_writer::start_tag('div');
        echo \html_writer::start_tag('table', $this->attributes);
    }

    /**
     * Generate the name column.
     *
     * @param \stdClass $data the data for a single row in this column.
     * @return string the name HTML
     */
    protected function col_name($data): string {
        global $OUTPUT;

        // TODO move to an lti table renderer?
        $iconurl = $data->icon ?: $OUTPUT->image_url('monologo', 'lti')->out();
        $name = $data->name;
        $img = \html_writer::img($iconurl, get_string('courseexternaltooliconalt', 'mod_lti', $name), ['class' => 'coursetoolicon']);
        $name = \html_writer::span($name, 'align-self-center');
        return \html_writer::div(\html_writer::div($img, 'p-sm-2 mr-2') . $name, 'd-flex');
    }

    /**
     * Generate the actions column.
     *
     * @param \stdClass $data the data for a single row in this column.
     * @return string the name HTML
     */
    protected function col_actions($data): string {
        global $OUTPUT;

        // Lock actions for site-level preconfigured tools.
        if (get_site()->id == $data->course) {
            return \html_writer::div($OUTPUT->pix_icon('t/locked', get_string('sitetoolnocourseediting', 'mod_lti')),
                'tool-action-icon-container');
        }

        // Lock actions when the user can't add course tools.
        if (!has_capability('mod/lti:addcoursetool', \context_course::instance($data->course))) {
            return \html_writer::div($OUTPUT->pix_icon('t/locked', get_string('courseexternaltoolsnoaddpermissions', 'mod_lti')),
                'tool-action-icon-container');
        }

        // Build and display an action menu.
        $menu = new \action_menu();
        $menu->set_menu_trigger($OUTPUT->pix_icon('i/moremenu', get_string('actions', 'core')),
            'btn btn-icon d-flex align-items-center justify-content-center'); // TODO check 'actions' lang string with UX.

        $menu->add(new \action_menu_link(
            new \moodle_url('/mod/lti/coursetooledit.php', ['course' => $data->course, 'typeid' => $data->id]),
            null,
            get_string('edit', 'core'),
            null
        ));

        return $OUTPUT->render($menu);
    }
}
