<?php
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
 
// local/invoice/lib.php

defined('MOODLE_INTERNAL') || die();

/**
 * Hide the admin-only custom menu block for users who do not have moodle/site:config.
 *
 * @param global_navigation $navigation
 */
function local_invoice_extend_navigation(global_navigation $navigation): void {
    global $PAGE;

    if (!has_capability('moodle/site:config', context_system::instance())) {
        $PAGE->requires->js(new moodle_url('/local/invoice/js/hide_admin_menu.js'));
    }
}
