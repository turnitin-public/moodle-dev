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
 * A javascript module to enhance the advduration form element.
 *
 * @copyright  2019 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = (elementId) => {
    // Get the element id prefix for all inputs belonging to this element.
    // If used as a normal (non-grouped) element, elementId will be of the form: "fitem_id_ELEMENTNAME".
    // All inputs within this container have ids like 'id_ELEMENTNAME_weeks', 'id_ELEMENTNAME_days', etc.
    // If used in a group, there is no container element beginning with 'fitem_' and the elementId will be of the form:
    // "id_ELEMENTNAME".
    const idPrefix = (elementId.substr(0, 6) === "fitem_") ? elementId.substring(6) : elementId;
    const elements = [
        {
            name: 'weeks',
            id: idPrefix + '_w',
            rolloverUpperLimit: null
        },
        {
            name: 'days',
            id: idPrefix + '_d',
            rolloverUpperLimit: 7
        },
        {
            name: 'hours',
            id: idPrefix + '_h',
            rolloverUpperLimit: 24
        },
        {
            name: 'minutes',
            id: idPrefix + '_i',
            rolloverUpperLimit: 60
        },
        {
            name: 'seconds',
            id: idPrefix + '_s',
            rolloverUpperLimit: 60
        }
    ];

    // For each potential element, check to see if it's in the DOM and bind event handlers.
    let highestOrderUnit = null; // This records the highest unit in the current duration widget.
    let parentElements = []; // Contains all elements we've already seen in the loop (parents of the current element).
    for (let key in elements) {
        let elementInfo = elements[key];
        let element = document.getElementById(elementInfo.id);
        if (element) {
            // Don't add an event handler for the highest order unit being displayed.
            // This field has no parent to affect.
            if (!highestOrderUnit) {
                highestOrderUnit = elementInfo.name;
                element.setAttribute('min', '0'); // Disable negative rollover for the highest order element.
                continue;
            }

            // At this point, the highest order unit has been found in the DOM,
            // so every element is guaranteed to have a parent at the -1 position.
            const parentElementInfo = elements[key - 1];
            const parentElement = document.getElementById(parentElementInfo.id);
            parentElements.push(parentElement);
            const allParentElements = parentElements.slice(); // The current element's parents.
            element.addEventListener('input', eventHandler.bind(null, elementInfo, parentElement, allParentElements), false);
        }
    }
};

const eventHandler = (elementInfo, parentElement, allParentElements, event) => {
    const target = event.target;

    // Adjust the parent element on rollover.
    if (target.value >= elementInfo.rolloverUpperLimit) {
        // Incrementing is unconditional rollover. Always rollover and increment the parent value.
        target.value = 0;
        parentElement.value = parseInt(parentElement.value) + 1;
        parentElement.dispatchEvent(new Event('input'));
    } else if (target.value < 0) {
        // Decrementing is conditional rollover. We can't decrement beyond 0.
        if (parentElement.value > 0) {
            // If the parent has a positive value, allow rollover of target and decrement the parent value.
            target.value = elementInfo.rolloverUpperLimit - 1;
            parentElement.value = parseInt(parentElement.value) - 1;
            parentElement.dispatchEvent(new Event('input'));
        } else if (parentElement.value <= 0) {
            // If the parent has a zero value, only rollover target and reduce the parent value
            // if a non-zero-value ancestor of the parent exists.
            let allZero = true;
            for (let key in allParentElements) {
                if (parseInt(allParentElements[key].value) != 0) {
                    allZero = false;
                    break;
                }
            }
            if (allZero) {
                target.value = 0;
            } else {
                parentElement.value = parseInt(parentElement.value) - 1;
                parentElement.dispatchEvent(new Event('input'));
                target.value = elementInfo.rolloverUpperLimit - 1;
            }
        }
    }
};
