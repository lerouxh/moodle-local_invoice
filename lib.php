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

// -----------------------------------------------------------------------------
// Pro licensing helpers (cached locally; no remote calls on normal operation).
// -----------------------------------------------------------------------------

/**
 * Return the configured Pro "valid until" value as a UNIX timestamp.
 *
 * Stored in mdl_config_plugins as local_invoice / valid_until.
 * - 'free' (or empty) means no Pro license.
 * - numeric value is treated as a UNIX timestamp.
 * - otherwise, we attempt to parse as a date/time string.
 *
 * @return int 0 when no Pro validity is stored.
 */
function local_invoice_pro_valid_until_ts(): int {
    // Store Pro validity under a short key to reduce obvious tampering.
    // 'vu' = valid until.
    $raw = get_config('local_invoice', 'vu');
    if ($raw === false || $raw === null) {
        return 0;
    }

    $raw = trim((string)$raw);
    if ($raw === '' || strtolower($raw) === 'free') {
        return 0;
    }

    // Prefer numeric (timestamp) storage.
    if (ctype_digit($raw)) {
        return (int)$raw;
    }

    // Fall back to parsing a date/datetime string.
    $ts = strtotime($raw);
    return ($ts === false) ? 0 : (int)$ts;
}

/**
 * Whether Pro features should be enabled right now.
 *
 * @return bool
 */
function local_invoice_is_pro_active(): bool {
    $ts = local_invoice_pro_valid_until_ts();
    return ($ts > 0 && $ts > time());
}

/**
 * Returns a small HTML snippet (red) describing the Pro license status.
 * Intended for display on the admin settings page.
 *
 * @return string
 */
function local_invoice_pro_status_html(): string {
    $raw = get_config('local_invoice', 'vu');
    $ts  = local_invoice_pro_valid_until_ts();

    if ($ts > time()) {
        $date = userdate($ts, get_string('strftimedate', 'langconfig'));
        return html_writer::tag(
            'span',
            get_string('pro_license_valid_until', 'local_invoice', $date),
            ['style' => 'color: #b00000; font-weight: 700;']
        );
    }

    // "free" or never activated.
    if ($raw === false || $raw === null || trim((string)$raw) === '' || strtolower(trim((string)$raw)) === 'free') {
        return html_writer::tag(
            'span',
            get_string('pro_license_free', 'local_invoice'),
            ['style' => 'color: #b00000; font-weight: 700;']
        );
    }

    // Expired.
    return html_writer::tag(
        'span',
        get_string('pro_license_expired', 'local_invoice'),
        ['style' => 'color: #b00000; font-weight: 700;']
    );
}

/**
 * Hide the admin-only custom menu block for users who do not have moodle/site:config.
 *
 * @param global_navigation $navigation
 */
function local_invoice_extend_navigation(global_navigation $navigation): void {
    global $PAGE;

    if (!has_capability('moodle/site:config', context_system::instance())) {
        // Load as a JavaScript module (ESM source transpiled to AMD for deployment).
        $PAGE->requires->js_call_amd('local_invoice/hide_admin_menu', 'init');
    }
}
