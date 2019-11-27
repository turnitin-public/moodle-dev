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
    const container = document.getElementById(elementId);
    const FIELDS = [
        {
            name: 'weeks',
            id: elementId + '_w',
            rolloverUpperLimit: null
        },
        {
            name: 'days',
            id: elementId + '_d',
            rolloverUpperLimit: 7
        },
        {
            name: 'hours',
            id: elementId + '_h',
            rolloverUpperLimit: 24
        },
        {
            name: 'minutes',
            id: elementId + '_i',
            rolloverUpperLimit: 60
        },
        {
            name: 'seconds',
            id: elementId + '_s',
            rolloverUpperLimit: 60
        }
    ];
    const TOGGLER = elementId + '_toggle';

    let foundElements = [];

    // For each potential element, check to see if it's in the DOM and bind event handlers.
    let highestOrderUnit = null; // This records the highest unit in the current duration widget.
    let parentElements = []; // Contains all elements we've already seen in the loop (parents of the current element).
    for (let key in FIELDS) {
        let fieldInfo = FIELDS[key];
        let element = container.querySelector(`#${FIELDS[key].id}`);
        if (element) {
            foundElements.push(element);
            // Don't add an event handler for the highest order unit being displayed.
            // This field has no parent to affect.
            if (!highestOrderUnit) {
                highestOrderUnit = fieldInfo.name;
                element.setAttribute('min', '0'); // Disable negative rollover for the highest order element.
                continue;
            }

            // At this point, the highest order unit has been found in the DOM,
            // so every element is guaranteed to have a parent at the -1 position.
            const parentFieldInfo = FIELDS[key - 1];
            const parentElement = document.getElementById(parentFieldInfo.id);
            parentElements.push(parentElement);
            const allParentElements = parentElements.slice(); // The current element's parents.
            element.addEventListener('input', spinEventHandler.bind(null, fieldInfo, parentElement, allParentElements), false);
        }
    }

    // Assign the enable/disable handler to the toggler element.
    let togglerElement = container.querySelector(`#${TOGGLER}`);
    if (togglerElement) {
        //togglerElement.addEventListener('change', toggleEventHandler.bind(null, foundElements), false);
    }
};

/*const toggleEventHandler = (elements, event) => {
    const target = event.target;
    for (let key in elements) {
        elements[key].disabled = !target.checked;
    }
};
*/
const spinEventHandler = (fieldInfo, parentElement, allParentElements, event) => {
    const target = event.target;

    // Adjust the parent element on rollover.
    if (target.value >= fieldInfo.rolloverUpperLimit) {
        // Incrementing is unconditional rollover. Always rollover and increment the parent value.
        target.value = 0;
        parentElement.value = parseInt(parentElement.value) + 1;
        parentElement.dispatchEvent(new Event('input'));
    } else if (target.value < 0) {
        // Decrementing is conditional rollover. We can't decrement beyond 0.
        if (parentElement.value > 0) {
            // If the parent has a positive value, allow rollover of target and decrement the parent value.
            target.value = fieldInfo.rolloverUpperLimit - 1;
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
                target.value = fieldInfo.rolloverUpperLimit - 1;
            }
        }
    }
};
