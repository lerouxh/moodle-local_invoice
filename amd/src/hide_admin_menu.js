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

import {get_string as getString} from 'core/str';

/**
 * Check whether an element's visible text contains a given string.
 *
 * @param {HTMLElement|null} el The element to check.
 * @param {string} needle The string to look for.
 * @returns {boolean}
 */
const textIncludes = (el, needle) => {
    if (!el || !needle) {
        return false;
    }
    const t = String(el.textContent || '').trim().toLowerCase();
    return t.includes(String(needle).toLowerCase());
};

/**
 * Hide the "Invoices (admins)" custom menu for non-admins by:
 * 1) finding the settings link,
 * 2) hiding its <li>,
 * 3) hiding the parent dropdown <li> if its label matches the translated title.
 *
 * @param {string} menutitle The translated parent menu title.
 */
const hideInvoicesAdminsMenu = (menutitle) => {
    const settingsHref = '/admin/settings.php?section=local_invoice_settings';

    // Find the settings link in the custom menu.
    const links = document.querySelectorAll(`a[href*="${settingsHref}"]`);
    if (!links.length) {
        return;
    }

    links.forEach((a) => {
        // Hide the submenu item.
        const submenuLi = a.closest('li');
        if (submenuLi) {
            submenuLi.style.display = 'none';
        } else {
            // Fallback: hide just the link.
            a.style.display = 'none';
        }

        // Attempt to hide the parent menu item (the dropdown container).
        // This relies on the menu title, but it is localized via the lang string.
        const parentLi = (submenuLi && submenuLi.parentElement) ? submenuLi.parentElement.closest('li') : null;
        if (parentLi && textIncludes(parentLi, menutitle)) {
            parentLi.style.display = 'none';
        }
    });
};

/**
 * Initialise the module.
 */
export const init = () => {
    // Get the localized menu title rather than hard-coding English.
    getString('menuinvoicesadmins', 'local_invoice')
        .then((menutitle) => {
            hideInvoicesAdminsMenu(menutitle);
        })
        .catch(() => {
            // If the string lookup fails, do nothing (do not break navigation).
        });
};
