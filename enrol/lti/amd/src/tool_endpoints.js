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
 * Module providing the 'copy to clipboard' action to URLs on the 'Tool registration' admin settings page.
 *
 * @module     enrol_lti/tool_endpoints
 * @package    enrol_lti
 * @copyright  2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import CustomEvents from 'core/custom_interaction_events';
import {add as notice} from 'core/toast';
import $ from 'jquery';

export const init = () => {
    // Add activation events for copy to clipboard.
    var container = document.querySelector('#lti_tool_endpoints');
    container = $(container);
    CustomEvents.define(container, [CustomEvents.events.activate]);

    container.on(CustomEvents.events.activate, "[role='button']", function(e, data) {
        data.originalEvent.preventDefault();

        var id = e.currentTarget.id;
        var val = document.getElementById("lti_tool_endpoint_url_" + id).innerText;
        var copyMessage = document.getElementById(id).dataset.copymessage;
        navigator.clipboard.writeText(val);

        // Let the user know the URL was copied to the clipboard.
        notice(copyMessage);
    });
};
