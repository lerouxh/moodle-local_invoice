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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Hide the admin-only invoice settings custom-menu item for non-admin users.
 *
 * @module local_invoice/hide_admin_menu
 * @copyright 2026 eLearn Solutions
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Check whether an element's visible text contains a given string.
 *
 * @param {HTMLElement|null} el
 * @param {string} needle
 * @returns {boolean}
 */
const textIncludes = (el, needle) => {
    if (!el) {
        return false;
    }

    const t = String(el.textContent || '').trim().toLowerCase();
    return t.includes(String(needle).toLowerCase());
};

/**
 * Hide the "Invoices (admins)" menu (or just the settings link as a fallback).
 */
const hideInvoicesAdminsMenu = () => {
    const needleHref = '/admin/settings.php?section=local_invoice_settings';
    const links = document.querySelectorAll(`a[href*="${needleHref}"]`);

    links.forEach((a) => {
        const li = a.closest('li');
        if (li && textIncludes(li, 'Invoices (admins)')) {
            li.style.display = 'none';
            return;
        }

        // Fallback: hide just the link.
        a.style.display = 'none';
    });
};

/**
 * Module init.
 */
export const init = () => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideInvoicesAdminsMenu);
    } else {
        hideInvoicesAdminsMenu();
    }
};
