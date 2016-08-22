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
 * Contains the block renderable.
 *
 * @package   block_gradevis
 * @copyright 2016 Jake Dallimore
 * @author    Jake Dallimore <jrhdallimore@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_gradevis\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to help display a GradeVis block.
 *
 * @package   block_gradevis
 * @copyright 2016 Jake Dallimore
 * @author    Jake Dallimore <jrhdallimore@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block implements \renderable, \templatable {

    /**
     * An array of grade information to visualise.
     *
     * @var array
     */
    protected $gradedata;

    /**
     * Constructor.
     *
     * @param array $gradedata An array of grade data.
     */
    public function __construct(array $gradedata = array()) {
        $this->gradedata = $gradedata;
    }

    /**
     * Prepare data for use in a template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        // Fetch the grade data from the gradebook API. Might want a helper class here.
        $data = [0 => 17, 1 => 15, 2 => 5, 3 => 8];

        // Create the chart.
        $chart = new \core\chart_line();
        // Note: There is really no need to extend the php class here - it only serves as a JSON string generator in the end.
        // This was just an avenue of exploration, that has now been abandoned in favour generating the JSON with a helper.
        //$chart = new \block_gradevis\chart\chart_line();


        //$series = new \core\chart_series(get_string("hits"), $data);
        $series = new \core\chart_series('grades', $data);
        $series->set_color("#4572ee");
        $series->set_smooth(true);
        $chart->add_series($series);
        $chart->set_title("Course grade info");
        $chart->set_labels(array('Mon','Tue','Wed','Thu'));
        $yaxis = $chart->get_yaxis(0, true);
        //$yaxis->set_label(get_string("hits"));
        $yaxis->set_stepsize(max(1, round(max($data) / 4)));

        $chartdata = json_encode($chart);

        // JSON chart string for testing. Allows for a more transparent view of the data.
        $chartdata = '[{
            "type":"line",
            "series":[
                {
                    "fill": true,
                    "label":"Grades",
                    "labels":null,
                    "type":null,
                    "values":[17,15,5,8],
                    "colors":["green"],
                    "fillColor":"rgba(190,255,190,0.5)",
                    "axes":{"x":null,"y":null},
                    "smooth":true
                },
                {
                    "fill": true,
                    "label":"Another series",
                    "labels":null,
                    "type":null,
                    "values":[5,11,7,15],
                    "colors":["blue"],
                    "fillColor":"rgba(190,190,255,0.5)",
                    "axes":{"x":null,"y":null},
                    "smooth":true
                }
            ],
            "labels":["Mon","Tue","Wed","Thu"],
            "title":"Course grade info",
            "axes":{
                "x":[],
                "y":[
                    {"label":null,"labels":null,"max":null,"min":null,"position":null,"stepSize":4}
                ]
            },
            "smooth":true
        },
        {
            "type":"line",
            "series":[
                {
                    "fill": false,
                    "label":"Grades",
                    "labels":null,
                    "type":null,
                    "values":[3,18,20,21,4,4],
                    "colors":["red"],
                    "fillColor":"rgba(255,190,190,0.5)",
                    "axes":{"x":null,"y":null},
                    "smooth":true
                },
                {
                    "fill": true,
                    "label":"Another series",
                    "labels":null,
                    "type":null,
                    "values":[14,9,13,19,11,5],
                    "colors":["orange"],
                    "fillColor":"rgba(255,190,135,0.5)",
                    "axes":{"x":null,"y":null},
                    "smooth":true
                }
            ],
            "labels":["Mon","Tue","Wed","Thu","Fri","Sat"],
            "title":"Course grade info",
            "axes":{
                "x":[],
                "y":[
                    {"label":null,"labels":null,"max":null,"min":null,"position":null,"stepSize":4}
                ]
            },
            "smooth":true
        }]';

        // Return the serialised data.
        return array('chartdata' => $chartdata, 'uniqid' => uniqid());
    }
}
