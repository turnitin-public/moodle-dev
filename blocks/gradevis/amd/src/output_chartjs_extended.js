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
 * An extension of the core chartjs output module, providing support for extra features supported by the library.
 *
 * @module     block_gradevis/output_chartjs_extended
 * @package    core
 * @copyright  2016 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/chartjs',
    'core/chart_output_chartjs'
], function(Chartjs, BaseOutput) {

    /**
     * Extended output module for chartjs, with support for a more complete set of library features.
     *
     * @alias module:block_gradevis/ouput_chartjs_extended
     * @extends {module:core/chart_output_chartjs
     * @class
     */
    function Output() {
        BaseOutput.prototype.constructor.apply(this, arguments);
    }
    Output.prototype = Object.create(BaseOutput.prototype);
    Output.prototype.constructor = Output;

    /**
     * Extension to the chart config.
     *
     * @protected
     * @return {Object[]}
     */
    Output.prototype._makeConfig = function() {
        var config = BaseOutput.prototype._makeConfig.apply(this, arguments);
        //console.log(config);
        //console.log(this._chart);

        // Now, apply further extension to the config.
        this._chart.getSeries().forEach(function (element, index) {
            //console.log(element);

            // Don't need to apply the smooth as this is already applied by the parent class.
            // config.data.datasets[index].smooth = element.getSmooth();

            config.data.datasets[index].fill = element.getFill();
            config.data.datasets[index].backgroundColor = element.getFillColor();
        });

        return config;
    };

    /**
     * @override
     *
     * Output override, allowing us to plug in an extended line chart controller providing 'line at index' functionality.
     *
     * @protected
     */
    Output.prototype._build = function() {
        this._config = this._makeConfig();

        // Here we can extend the controller to support our new chart type.


        var baseController = Chartjs.controllers.line;
        Chartjs.defaults.lineext = Chartjs.defaults.line; // This is important!

        Chartjs.controllers.lineext = Chartjs.controllers.line.extend({
            draw: function (ease) {
                baseController.prototype.draw.apply(this, arguments);
                // or: originalLineController.prototype.draw.call(this, ease);

                // Draw a vertical line at index 2.
                var index = 2;
                var ctx = this.chart.chart.ctx;
                var xaxis = this.chart.scales['x-axis-0'];
                var yaxis = this.chart.scales['axis-y-0'];
                ctx.save();
                ctx.beginPath();
                ctx.moveTo(xaxis.getPixelForValue(undefined, index), yaxis.top);
                ctx.strokeStyle = '#ff0000';
                ctx.lineTo(xaxis.getPixelForValue(undefined, index), yaxis.bottom);
                ctx.stroke();
                ctx.restore();
            }
        });

        /*
        // Taken from here: https://github.com/chartjs/Chart.js/issues/2321
        Chartjs.defaults.lineext = Chartjs.defaults.line;
        var custom = Chartjs.DatasetController.extend(Chartjs.controllers.line.prototype);
        //console.log(custom);
        custom.linkScales = Chartjs.helpers.noop;
        Chartjs.controllers.lineext = custom;

        //Note: For some reason this is overriding the prototype method for both line and lineext.
        // I think something in extension is borken

        Chartjs.controllers.lineext.prototype.draw = function() {
            //this.__super__.draw.call(ease);
            console.log('here');
        };
        console.log(Chartjs.controllers.lineext.prototype.draw);
        console.log(Chartjs.controllers.line.prototype.draw);
        */

        this._chartjs = new Chartjs(this._canvas[0], this._config);
    };

    /**
     * I have to override this method because the base output implementation doesn't support other chart types.
     *
     * @override
     *
     * @param series
     * @private
     */
    Output.prototype._isSmooth = function(series) {
        var smooth = series.getSmooth();
        if (smooth === null) {
            smooth = this._chart.getSmooth();
        }
        return smooth;
    };



    return Output;
});