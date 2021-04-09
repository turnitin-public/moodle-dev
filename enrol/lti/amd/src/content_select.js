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
 * Module providing checkbox autoselection behaviour to the table on the select content deep linking view, launch_deeplink.php.
 *
 * @module     enrol_lti/content_select
 * @package    enrol_lti
 * @copyright  2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Register the checkbox change events allowing the automatic selection/deselection of the
// 'add to gradebook' and 'add to course' checkboxes when selecting an activity/resource.
const registerEventHandlers = () => {
    document.addEventListener('change', e => {
        let modcheckbox = e.target.closest("input[type='checkbox'][name^='modules']");
        let gradecheckbox = null;
        if (!modcheckbox) {
            gradecheckbox = e.target.closest("input[type='checkbox'][name^='grades']");
        }
        if (modcheckbox) {
            const id = modcheckbox.value;
            const gradecheckbox = document.querySelector("input[type='checkbox'][name^='grades'][value='" + id + "']");
            if (gradecheckbox) {
                gradecheckbox.checked = modcheckbox.checked;
            }
        } else if (gradecheckbox) {
            const id = gradecheckbox.value;
            const modcheckbox = document.querySelector("input[type='checkbox'][name^='modules'][value='" + id + "']");
            if (gradecheckbox.checked) {
                modcheckbox.checked = true;
            }
        } else {
            return;
        }
    });
};

export const init = () => {
    registerEventHandlers();
};
