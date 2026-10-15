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
 * Create, edit, reorder and delete custom pages and sections (management list and page header buttons).
 *
 * @module      local_custompage/manage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import Pending from 'core/pending';
import {getString} from 'core/str';

const FORM = 'local_custompage\\form\\page';

/**
 * Open the section/page form in a modal.
 *
 * @param {HTMLElement} trigger
 * @param {String} titleKey
 * @param {Object} args
 * @param {Function} onSaved Called with the saved page's URL
 */
const openForm = (trigger, titleKey, args, onSaved) => {
    const modal = new ModalForm({
        formClass: FORM,
        args,
        modalConfig: {title: getString(titleKey, 'local_custompage'), large: true},
        saveButtonText: getString('savechanges', 'moodle'),
        returnFocus: trigger,
    });
    modal.addEventListener(modal.events.FORM_SUBMITTED, e => onSaved(e.detail));
    modal.show();
};

/**
 * Call a web service, then reload (or go to a URL).
 *
 * @param {String} methodname
 * @param {Object} args
 * @param {String|null} gotoUrl
 */
const callAndReload = (methodname, args, gotoUrl = null) => {
    const pending = new Pending('local_custompage/manage:' + methodname);
    Ajax.call([{methodname, args}])[0]
        .then(() => {
            window.location.href = gotoUrl || window.location.href;
            return pending.resolve();
        })
        .catch(e => {
            pending.resolve();
            Notification.exception(e);
        });
};

/**
 * Confirm and delete a page or section.
 *
 * @param {HTMLElement} button
 */
const confirmDelete = async(button) => {
    // The name comes from the button itself: rows are table rows on the manage screen and list items in the hub wizard.
    const row = button.closest('tr, li');
    const name = (button.dataset.name || row?.querySelector('a')?.textContent || '').trim();
    const isSection = button.dataset.issection === '1';
    const children = parseInt(button.dataset.childcount || '0', 10);
    const bodyKey = isSection && children > 0 ? 'deletesectionconfirm' : 'deleteconfirm';
    try {
        await Notification.deleteCancelPromise(
            getString(isSection ? 'deletesection' : 'deletepage', 'local_custompage'),
            getString(bodyKey, 'local_custompage', {name, count: children}),
            getString('delete', 'core'),
            {triggerElement: button}
        );
    } catch (e) {
        return;
    }
    // A delete button on the page being deleted (data-onpage="1") leaves for the manage screen; elsewhere the list reloads.
    const onThisPage = button.dataset.onpage === '1';
    callAndReload('local_custompage_page_delete', {pageid: parseInt(button.dataset.id, 10)},
        onThisPage ? M.cfg.wwwroot + '/local/custompage/index.php' : null);
};

let initialised = false;

/**
 * Initialise click handling.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    document.addEventListener('click', e => {
        const trigger = e.target.closest('[data-action^="local_custompage-"]');
        if (!trigger) {
            return;
        }
        e.preventDefault();
        const action = trigger.dataset.action.replace('local_custompage-', '');

        if (action === 'new-section') {
            openForm(trigger, 'newsection', {type: 'section'}, url => {
                window.location.href = url;
            });
        } else if (action === 'new-page') {
            openForm(trigger, 'newpage', {type: 'page', parent: parseInt(trigger.dataset.parent || '0', 10)}, url => {
                window.location.href = url;
            });
        } else if (action === 'edit') {
            openForm(trigger, 'editsettings', {id: parseInt(trigger.dataset.id, 10)}, () => {
                window.location.reload();
            });
        } else if (action === 'move') {
            const method = trigger.dataset.direction === 'up' ? 'local_custompage_page_move_up' : 'local_custompage_page_move_down';
            callAndReload(method, {pageid: parseInt(trigger.dataset.id, 10)});
        } else if (action === 'delete') {
            confirmDelete(trigger);
        }
    });
};
