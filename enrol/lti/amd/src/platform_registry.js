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
 * Module to enhance the platform registrations page with custom interaction events.
 *
 * @module     enrol_lti/platform_registry
 * @package    enrol_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import CustomEvents from 'core/custom_interaction_events';
import {add as notice} from 'core/toast';
import $ from 'jquery';

export const init = () =>  {
    // Add activation events for copy to clipboard.
    var container = document.querySelector('#lti_platform_registrations');
    container = $(container);
    CustomEvents.define(container, [CustomEvents.events.activate]);

    container.on(CustomEvents.events.activate, "[role='button']", function(e) {
        var id = e.currentTarget.id;
        var val = document.querySelector("#lti_platform_registry_" + id).value;
        var title = document.querySelector("#lti_platform_registry_" + id).title;
        navigator.clipboard.writeText(val);

        // Confirm copy.
        notice(title+ " copied to clipboard");
    });
};
