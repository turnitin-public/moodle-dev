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
 * Contains class block_gradevis\output\block_renderer_html
 *
 * @package   block_gradevis
 * @copyright 2016 Jake Dallimore
 * @author    Jake Dallimore <jrhdallimore@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_gradevis\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer for GradeVis block
 *
 * @package   block_gradevis
 * @copyright 2016 Jake Dallimore
 * @author    Jake Dallimore <jrhdallimore@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * @param \templatable $block
     * @return bool|string
     */
    public function render_block(\templatable $block) {
        // The 'export_from_template()' call will return the chart data (JSON encoded). \
        // All we need to do is pass the data in to the template renderer.
        $data = $block->export_for_template($this);
        return $this->render_from_template('block_gradevis/block', $data);
    }
}