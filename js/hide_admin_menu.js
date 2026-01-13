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
 * Plugin version
 * @package    local_invoice
 * @copyright  2026 eLearn Solutions
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
// local/invoice/js/hide_admin_menu.js

(function () {
    function textIncludes(el, needle) {
        if (!el) { return false; }
        var t = (el.textContent || "").trim().toLowerCase();
        return t.indexOf(needle.toLowerCase()) !== -1;
    }

    function hideInvoicesAdminsMenu() {
        var needleHref = "/admin/settings.php?section=local_invoice_settings";
        var links = document.querySelectorAll('a[href*="' + needleHref + '"]');

        links.forEach(function (a) {
            // Try to find the top-level menu <li> that contains the "Invoices (admins)" label.
            var li = a.closest("li");
            if (li && textIncludes(li, "Invoices (admins)")) {
                li.style.display = "none";
                return;
            }

            // Fallback: hide just the link (prevents hiding other menus like "My invoices").
            a.style.display = "none";
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", hideInvoicesAdminsMenu);
    } else {
        hideInvoicesAdminsMenu();
    }
})();
