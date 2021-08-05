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
 * Output the preview and edit action area for this activity.
 *
 * @package   mod_quiz
 * @copyright 2021 Sujith Haridasan <sujith@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quiz\output;

use moodle_url;
use renderer_base;
use templatable;
use renderable;

/**
 * Render view action with preview and edit buttons
 *
 * @copyright 2021 Sujith Haridasan <sujith@moodle.com>
 * @package mod_quiz
 */
class previeweditaction implements templatable, renderable {
    /** @var int */
    private $cmid;

    /** @var bool */
    private $canedit;

    /** @var bool */
    private $canattempt;

    /** @var bool */
    private $canpreview;

    /** @var bool */
    private $quizhasquestions;

    /**
     * previeweditaction constructor.
     *
     * @param int $cmid The course module id.
     * @param bool $canedit Can edit the quiz.
     * @param bool $canattempt can attempt the quiz.
     * @param bool $canpreview Can preview the quiz.
     * @param bool $quizhasquestions If quiz has questions.
     * @param int $attempts The attempts made.
     */
    public function __construct(int $cmid, bool $canedit, bool $canattempt,
            bool $canpreview, bool $quizhasquestions, int $attempts) {
        $this->cmid = $cmid;
        $this->canedit = $canedit;
        $this->canattempt = $canattempt;
        $this->canpreview = $canpreview;
        $this->quizhasquestions = $quizhasquestions;
        $this->attempts = $attempts;
    }

    /**
     * Provide data for the template
     *
     * @param renderer_base $output renderer_base objects.
     * @return array data for template
     */
    public function export_for_template(renderer_base $output):array {
        $data = [];
        if ($this->quizhasquestions) {
            if ($this->canpreview) {
                $data['previewlink'] = new moodle_url(
                    '/mod/quiz/startattempt.php',
                    ['attempt' => $this->attempts, 'cmid' => $this->cmid, 'sesskey' => sesskey()]);
            }

            if ($this->canedit) {
                $data['editlink'] = new moodle_url('/mod/quiz/edit.php', ['cmid' => $this->cmid]);
            }

            if ($this->canattempt && !$this->canpreview) {
                $data['attemptlink'] = new moodle_url(
                    '/mod/quiz/startattempt.php',
                    ['cmid' => $this->cmid, 'sesskey' => sesskey()]);
            }
        } else {
            $data['addquestionlink'] = new moodle_url('/mod/quiz/edit.php', ['cmid' => $this->cmid]);
        }

        return $data;
    }

    /**
     * Get the preview and edit quiz buttons rendered for action area.
     *
     * @return string rendered HTML string
     */
    public function get_preview_edit_action():string {
        global $PAGE;
        $renderer = $PAGE->get_renderer('mod_quiz');
        return $renderer->preview_edit_action($this);
    }
}
