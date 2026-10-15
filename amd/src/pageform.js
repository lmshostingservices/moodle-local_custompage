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
 * Page form: a page outside any section always chooses its own audience.
 *
 * @module      local_custompage/pageform
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const OWN = '1';

/**
 * When "No section" is chosen, switch "Who can see this page" to its own audience so the choices show.
 *
 * @param {HTMLSelectElement} parent
 */
const sync = parent => {
    const mode = parent.form?.querySelector('select[name="audiencemode"]');
    if (mode && parent.value === '0' && mode.value !== OWN) {
        mode.value = OWN;
        mode.dispatchEvent(new Event('change', {bubbles: true}));
    }
};

let initialised = false;

/**
 * Initialise.
 */
export const init = () => {
    document.querySelectorAll('select[name="parent"]').forEach(sync);
    if (initialised) {
        return;
    }
    initialised = true;
    document.addEventListener('change', e => {
        if (e.target.matches?.('select[name="parent"]')) {
            sync(e.target);
        }
    });
};
