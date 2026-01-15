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
 * @package    local_invoice
 * @copyright  2026 eLearn Solutions
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function() {
    /**
     * Check whether an element's visible text contains a given string.
     *
     * @param {HTMLElement|null} el The element to inspect.
     * @param {String} needle The text to look for.
     * @returns {Boolean} True if found, false otherwise.
     */
    const textIncludes = (el, needle) => {
        if (!el) {
            return false;
        }

        const t = (el.textContent || '').trim().toLowerCase();
        return t.includes(String(needle).toLowerCase());
    };

    /**
     * Hide the "Invoices (admins)" block (or the settings link as a fallback).
     */
    const hideInvoicesAdminsMenu = () => {
        const needleHref = '/admin/settings.php?section=local_invoice_settings';
        const links = document.querySelectorAll(`a[href*="${needleHref}"]`);

        links.forEach((a) => {
            // Try to find the top-level menu <li> that contains the "Invoices (admins)" label.
            const li = a.closest('li');
            if (li && textIncludes(li, 'Invoices (admins)')) {
                li.style.display = 'none';
                return;
            }

            // Fallback: hide just the link (prevents hiding other menus like "My invoices").
            a.style.display = 'none';
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideInvoicesAdminsMenu);
    } else {
        hideInvoicesAdminsMenu();
    }
}());
