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
 * Output the overrides action tab.
 *
 * @package   mod_quiz
 * @copyright 2021 Sujith Haridasan <sujith@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quiz\output;

use moodle_url;
use renderable;
use renderer_base;
use templatable;
use url_select;

/**
 * Render overrides action
 *
 * @copyright 2021 Sujith Haridasan <sujith@moodle.com>
 * @package mod_quiz
 */
class overridesaction implements renderable, templatable {
    /** @var int */
    private $cmid;

    /** @var string */
    private $mode;

    /** @var string */
    private $addbutton;

    /**
     * overridesaction constructor.
     *
     * @param int $cmid The course module id.
     * @param string $mode The mode passed for the overrides url.
     * @param string $addbutton rendered HTML button.
     */
    public function __construct(int $cmid, string $mode, string $addbutton) {
        $this->cmid = $cmid;
        $this->mode = $mode;
        $this->addbutton = $addbutton;
    }

    /**
     * Get the data for the template
     *
     * @param renderer_base $output renderer_base object.
     * @return array data for the template.
     */
    public function export_for_template(renderer_base $output):array {
        global $PAGE;

        $useroverride = new moodle_url('/mod/quiz/overrides.php', ['cmid' => $this->cmid, 'mode' => 'user']);
        $groupoverride = new moodle_url('/mod/quiz/overrides.php', ['cmid' => $this->cmid, 'mode' => 'group']);

        $menu = [
            $useroverride->out(false) => get_string('useroverrides', 'quiz'),
            $groupoverride->out(false) => get_string('groupoverrides', 'quiz')
        ];

        $urlselect = new url_select($menu, $PAGE->url->out(false), null, 'quizoverrides');
        return [
            'overrides' => $urlselect->export_for_template($output),
            'useroverride' => $this->mode === 'user' ? true : false,
            'groupoverride' => $this->mode === 'group' ? true : false,
            'addbutton' => ($this->mode !== '') ? $this->addbutton : '',
        ];
    }

    /**
     * Get the HTML rendered string.
     *
     * @return string rendered HTML string.
     */
    public function get_response_overrides():string {
        global $PAGE;

        $renderer = $PAGE->get_renderer('mod_quiz');
        return $renderer->overrides_action($this);
    }
}
