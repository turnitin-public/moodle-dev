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
 * Output the HTML elements for tertiary nav for this activity.
 *
 * @package   core_question
 * @copyright 2021 Sujith Haridasan <sujith@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_question\output;

use moodle_url;
use renderer_base;
use templatable;
use renderable;
use url_select;

/**
 * Rendered HTML elements for tertiary nav for Question bank.
 *
 * @copyright 2021 Sujith Haridasan <sujith@moodle.com>
 * @package core_question
 */
class qbank_actionbar implements templatable, renderable {
    /** @var array */
    private $id;

    /** @var moodle_url */
    private $currenturl;

    /**
     * qbank_actionbar constructor.
     *
     * @param array $id The course module id or courseid as key and value.
     * @param moodle_url $currenturl The current URL.
     */
    public function __construct(array $id, moodle_url $currenturl) {
        $this->id = $id;
        $this->currenturl = $currenturl;
    }

    /**
     * Provides the data for the template.
     *
     * @param renderer_base $output renderer_base object.
     * @return array data for the template
     */
    public function export_for_template(renderer_base $output):array {
        $idargname = array_key_first($this->id);
        $questionslink = new moodle_url('/question/edit.php', [$idargname => $this->id[$idargname]]);
        if (\core\plugininfo\qbank::is_plugin_enabled("qbank_managecategories")) {
            $categorylink = new moodle_url('/question/bank/managecategories/category.php', [$idargname => $this->id[$idargname]]);
        }
        $importlink = new moodle_url('/question/bank/importquestions/import.php', [$idargname => $this->id[$idargname]]);
        $exportlink = new moodle_url('/question/bank/exportquestions/export.php', [$idargname => $this->id[$idargname]]);

        $menu = [
            $questionslink->out(false) => get_string('questions', 'question'),
        ];

        if (\core\plugininfo\qbank::is_plugin_enabled("qbank_managecategories")) {
            $menu[$categorylink->out(false)] = get_string('categories', 'question');
        }
        $menu[$importlink->out(false)] = get_string('import', 'question');
        $menu[$exportlink->out(false)] = get_string('export', 'question');

        $urlselect = new url_select($menu, $this->currenturl, null, 'questionbankaction');
        $urlselect->set_label('questionbankactionselect', ['class' => 'accesshide']);

        $data = [
            'questionbankselect' => $urlselect->export_for_template($output),
        ];
        return $data;
    }

    /**
     * Rendered HTML elements for tertiary nav in the Qestion bank.
     *
     * @return string rendered HTML for tertiary nav in the Question bank
     */
    public function get_qbank_action():string {
        global $PAGE;
        $renderer = $PAGE->get_renderer('core_question', 'bank');
        return $renderer->qbank_action_menu($this);
    }
}
