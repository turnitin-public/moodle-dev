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
 * Chart series.
 *
 * @package    core
 * @copyright  2016 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @module     block_gradevis/chart_series_extended
 */
define(['core/chart_series'], function(BaseSeries) {

    /**
     * @alias module:block_gradevis/chart_series_extended
     * @extends {module:core/chart_series
     */
    function SeriesExt(data) {
        // Before we chain the constructor calls, we need to split out the label and values.
        BaseSeries.prototype.constructor.apply(this, [data.label, data.values]);
        this.init(data);
    }
    SeriesExt.prototype = Object.create(BaseSeries.prototype);
    SeriesExt.prototype.constructor = SeriesExt;

    /**
     * Whether the series should be an area chart (shading between line and x axis).
     *
     * By default a series does not add a fill.
     *
     * @type {Bool}
     * @protected
     */
    SeriesExt.prototype._fill = false;

    /**
     *
     * @type {null}
     * @protected
     */
    SeriesExt.prototype._fillColor = null;

    /**
     * Init our custom series with the data supplied.
     * @param seriesData
     */
    SeriesExt.prototype.init = function(data) {
        this.setSmooth(data.smooth);
        this.setFill(data.fill);
        if (data.colors && data.colors.length > 1) {
            this.setColors(data.colors);
        } else {
            this.setColor(data.colors[0]);
        }
        this.setFillColor(data.fillColor);
    };

    /**
     * Return the current fill color.
     * @returns {null}
     */
    SeriesExt.prototype.getFillColor = function() {
        return this._fillColor;
    };

    /**
     * Set the fill color.
     * @param color
     */
    SeriesExt.prototype.setFillColor = function(color) {
        this._fillColor = color;
    };

    /**
     * Get whether the chart should be filled or not.
     *
     * @method getFill
     * @returns {Bool}
     */
    SeriesExt.prototype.getFill = function() {
        return this._fill;
    };

    /**
     * Set whether the chart should be filled in or not.
     *
     * @method setFill
     * @param {Bool} filled True if the line chart should be filled in, false otherwise.
     */
    SeriesExt.prototype.setFill = function(fill) {
        this._fill = Boolean(fill);
    };

    return SeriesExt;
});
