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
 * Reading custom pages: filter a hub's pages, outline long pages, collapse the hub list on phones,
 * and answer "Was this page helpful?".
 *
 * @module      local_custompage/reader
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

/** @var {number} Below this width (of the page area) the hub list is a box above the page that starts closed. */
const NARROW = 760;

/** @var {number} An outline needs at least this many headings to be worth showing. */
const MIN_HEADINGS = 3;

/**
 * Make the text of a filter target comparable.
 *
 * @param {string} text
 * @returns {string}
 */
const normalise = text => (text || '').toLocaleLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();

/**
 * Filter boxes: hide items whose text does not contain every word typed.
 *
 * @param {HTMLElement} root
 */
const initFilters = root => {
    root.querySelectorAll('[data-lcp-filter]').forEach(input => {
        const list = root.querySelector(input.dataset.lcpFilter);
        if (!list) {
            return;
        }
        const items = [...list.querySelectorAll('[data-filter-text]')];
        const empty = list.parentElement.querySelector('.lcp-filter-empty');
        input.addEventListener('input', () => {
            const words = normalise(input.value).split(/\s+/).filter(Boolean);
            let shown = 0;
            items.forEach(item => {
                const text = normalise(item.dataset.filterText);
                const match = words.every(word => text.includes(word));
                item.hidden = !match;
                shown += match ? 1 : 0;
            });
            // Hide a group (for example a section on the home page) once nothing in it matches.
            list.querySelectorAll('section').forEach(group => {
                group.hidden = ![...group.querySelectorAll('[data-filter-text]')].some(item => !item.hidden);
            });
            if (empty) {
                empty.hidden = shown > 0;
            }
        });
        input.addEventListener('keydown', e => {
            if (e.key === 'Escape' && input.value) {
                input.value = '';
                input.dispatchEvent(new Event('input'));
            }
        });
    });
};

/**
 * On phones the hub list starts closed so the page comes first. When the page area grows wide enough for the
 * sidebar (a drawer closed, a tablet turned) the list opens again, because the sidebar has no open/close control.
 *
 * @param {HTMLElement} root
 */
const initHubNav = root => {
    const details = root.querySelector('[data-region="lcp-hubnav"]');
    if (!details) {
        return;
    }
    if (root.classList.contains('lcp-sidebar-top')) {
        details.open = false;
        return;
    }
    let wide = root.clientWidth >= NARROW;
    details.open = wide;
    if (!('ResizeObserver' in window)) {
        return;
    }
    new ResizeObserver(() => {
        const nowwide = root.clientWidth >= NARROW;
        if (nowwide !== wide) {
            wide = nowwide;
            details.open = wide;
        }
    }).observe(root);
};

/**
 * "On this page": list the page's headings and highlight the one being read.
 *
 * @param {HTMLElement} root
 */
const initOutline = root => {
    const body = root.querySelector('[data-region="lcp-body"]');
    // Two places for the same list: a column beside the page, or a jump bar above it when there is no room.
    const places = [...root.querySelectorAll('[data-region="lcp-outline"], [data-region="lcp-jump"]')];
    if (!body || !places.length) {
        return;
    }
    const headings = [...body.querySelectorAll('h2, h3')].filter(h => h.textContent.trim() !== '' && h.offsetParent !== null);
    if (headings.length < MIN_HEADINGS) {
        return;
    }
    headings.forEach((heading, index) => {
        if (!heading.id) {
            heading.id = 'lcp-section-' + (index + 1);
        }
    });
    const links = [];
    places.forEach(place => {
        const list = place.querySelector('.lcp-outline-list');
        headings.forEach(heading => {
            const item = document.createElement('li');
            item.className = 'lcp-outline-item lcp-outline-' + heading.tagName.toLowerCase();
            const link = document.createElement('a');
            link.href = '#' + heading.id;
            link.textContent = heading.textContent.trim().replace(/\s+/g, ' ');
            item.appendChild(link);
            list.appendChild(item);
            links.push([heading, link]);
        });
        place.hidden = false;
    });
    root.classList.add('lcp-has-outline');

    if (!('IntersectionObserver' in window)) {
        return;
    }
    const visible = new Set();
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => (entry.isIntersecting ? visible.add(entry.target) : visible.delete(entry.target)));
        // The current section is the first heading on screen, or the last one scrolled past.
        let current = headings.find(h => visible.has(h));
        if (!current) {
            current = [...headings].reverse().find(h => h.getBoundingClientRect().top < 0) || headings[0];
        }
        links.forEach(([heading, link]) => {
            if (heading === current) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }, {rootMargin: '0px 0px -60% 0px'});
    headings.forEach(h => observer.observe(h));
};

/**
 * "Was this page helpful?": save the answer straight away, then offer a comment box.
 *
 * @param {HTMLElement} root
 */
const initFeedback = root => {
    const box = root.querySelector('[data-region="lcp-feedback"]');
    if (!box) {
        return;
    }
    const pageid = parseInt(box.dataset.pageid, 10);
    const buttons = [...box.querySelectorAll('[data-helpful]')];
    const form = box.querySelector('.lcp-feedback-more');
    const textarea = form.querySelector('textarea');
    const status = box.querySelector('.lcp-feedback-status');
    let helpful = box.dataset.helpful === '1';

    const send = comment => Ajax.call([{
        methodname: 'local_custompage_feedback_submit',
        args: comment === undefined ? {pageid, helpful} : {pageid, helpful, comment},
    }])[0];

    buttons.forEach(button => button.addEventListener('click', async() => {
        if (box.dataset.busy === '1') {
            return;
        }
        const previous = helpful;
        helpful = button.dataset.helpful === '1';
        box.dataset.busy = '1';
        buttons.forEach(b => {
            b.disabled = true;
        });
        try {
            await send();
            buttons.forEach(b => b.setAttribute('aria-pressed', b === button ? 'true' : 'false'));
            box.dataset.answered = '1';
            status.textContent = await getString(helpful ? 'feedback_thanks' : 'feedback_sorry', 'local_custompage');
            form.hidden = false;
            textarea.focus();
        } catch (e) {
            helpful = previous;
            Notification.exception(e);
        } finally {
            box.dataset.busy = '0';
            buttons.forEach(b => {
                b.disabled = false;
            });
        }
    }));

    form.addEventListener('submit', async e => {
        e.preventDefault();
        try {
            await send(textarea.value);
            form.hidden = true;
            status.textContent = await getString('feedback_sent', 'local_custompage');
        } catch (err) {
            Notification.exception(err);
        }
    });
};

/**
 * Set up a reader page or hub.
 *
 * @param {string} selector The page's outer element
 */
export const init = selector => {
    const root = document.querySelector(selector);
    if (!root) {
        return;
    }
    initFilters(root);
    initHubNav(root);
    initOutline(root);
    initFeedback(root);
};
