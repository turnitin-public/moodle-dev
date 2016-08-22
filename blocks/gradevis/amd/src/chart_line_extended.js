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
 * Subclass of the chart_line.js class, adding a few extra features (known to be supported by the chart js output engine).
 *
 * @module     block_gradevis/chart_line_extended
 * @package    core
 * @copyright  2016 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/chart_line', 'block_gradevis/chart_series_extended'], function(Line, SeriesExt) {
    /**
     * Line chart with some additional features.
     *
     * @alias module:block_gradevis/chart_line_extended
     * @extends {module:core/chart_line}
     * @class
     */
    function LineExt() {
        Line.prototype.constructor.apply(this, arguments);
    }
    LineExt.prototype = Object.create(Line.prototype);
    LineExt.prototype.constructor = LineExt;

    /** @override */
    LineExt.prototype.TYPE = 'lineext';

    /** @override */
    LineExt.prototype.create = function(Klass, data) {
        var chart = Line.prototype.create.apply(this, arguments);

        // Let's use our own implementation of chart_series, instead of core.
        // Our series will pick up additional properties like 'fill' and 'backgroundColor' for two-tone line charts.
        chart._series = []; //unset the default series which has already been created.
        data.series.forEach(function(seriesData) {
            chart.addSeries(new SeriesExt(seriesData));
        });

        return chart;
    };

    return LineExt;
});
