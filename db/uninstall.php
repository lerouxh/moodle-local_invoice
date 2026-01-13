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
 
// local/invoice/db/uninstall.php

defined('MOODLE_INTERNAL') || die();

/**
 * Pre-uninstall hook.
 *
 * Strategy:
 * 1) Archive invoice history to tables NOT defined in install.xml (so Moodle won't drop them).
 * 2) Remove custom menu items added by this plugin.
 *
 * Notes:
 * - This prevents losing invoice numbering history on uninstall/reinstall.
 * - Your db/install.php (as previously shared) can restore from these archive tables on reinstall.
 */
function xmldb_local_invoice_uninstall(): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // ---------------------------------------------------------------------
    // 1) Archive data BEFORE Moodle drops plugin tables
    // ---------------------------------------------------------------------
    try {
        local_invoice_archive_before_uninstall($DB, $dbman);
    } catch (Throwable $e) {
        // Do not hard-fail uninstall by default.
        // If you prefer to BLOCK uninstall when archiving fails, replace this with:
        // throw $e;
        if (function_exists('debugging')) {
            debugging('local_invoice: archive failed during uninstall: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // ---------------------------------------------------------------------
    // 2) Remove custom menu items created by install.php
    // ---------------------------------------------------------------------
    $custommenu = (string) get_config('core', 'custommenuitems');
    if ($custommenu === '') {
        return true;
    }

    $publicparent = 'My invoices';
    $viewline     = '-View invoices|/local/invoice/invoice.php';
    $billline     = '-Billing details|/local/invoice/billing.php';

    $adminparent  = 'Invoices (admins)';
    $adminline    = '-Settings|/admin/settings.php?section=local_invoice_settings';

    // Legacy line from earlier attempt (remove if present, exact match only).
    $legacyline   = '-Settings (admins only)|/admin/settings.php?section=local_invoice_settings';

    $normalized = preg_replace("/\r\n|\r/", "\n", $custommenu);
    $lines = explode("\n", $normalized);

    $removeChildren = [$viewline, $billline, $adminline, $legacyline];

    $out = [];
    $count = count($lines);

    for ($i = 0; $i < $count; $i++) {
        $line = $lines[$i];
        $trim = trim($line);

        // Remove our known child lines anywhere they appear (exact match only).
        if (in_array($trim, $removeChildren, true)) {
            continue;
        }

        // Handle parent blocks: drop parent if it ends up with no children.
        if ($trim === $publicparent || $trim === $adminparent) {
            $childrenkept = [];
            $j = $i + 1;

            while ($j < $count && preg_match('/^\s*-/', $lines[$j])) {
                $childtrim = trim($lines[$j]);
                if (!in_array($childtrim, $removeChildren, true)) {
                    $childrenkept[] = $lines[$j];
                }
                $j++;
            }

            if (!empty($childrenkept)) {
                $out[] = $line;
                foreach ($childrenkept as $c) {
                    $out[] = $c;
                }
            }

            $i = $j - 1;
            continue;
        }

        $out[] = $line;
    }

    $newcustommenu = rtrim(implode("\n", $out));
    if ($newcustommenu !== rtrim($normalized)) {
        set_config('custommenuitems', $newcustommenu);
    }

    return true;
}

/**
 * Create archive tables (if missing) and copy invoice history into them.
 *
 * Archive tables are intentionally NOT listed in install.xml so they survive uninstall.
 */
function local_invoice_archive_before_uninstall(moodle_database $DB, database_manager $dbman): void {
    // Only archive if the live tables exist.
    $livetbl1 = new xmldb_table('local_invoice');
    $livetbl2 = new xmldb_table('local_invoice_user');

    if (!$dbman->table_exists($livetbl1) && !$dbman->table_exists($livetbl2)) {
        return;
    }

    // Create archive tables if needed.
    local_invoice_ensure_archive_tables($dbman);

    // If archive tables already contain data, do not append again (avoids duplicates).
    $already = false;
    if ($dbman->table_exists(new xmldb_table('local_invoice_archive')) && $DB->count_records('local_invoice_archive') > 0) {
        $already = true;
    }
    if ($dbman->table_exists(new xmldb_table('local_invoice_user_archive')) && $DB->count_records('local_invoice_user_archive') > 0) {
        $already = true;
    }
    if ($already) {
        return;
    }

    // Copy local_invoice_user -> local_invoice_user_archive
    if ($dbman->table_exists($livetbl2)) {
        $rs = $DB->get_recordset('local_invoice_user');
        foreach ($rs as $r) {
            unset($r->id);
            $DB->insert_record('local_invoice_user_archive', $r);
        }
        $rs->close();
    }

    // Copy local_invoice -> local_invoice_archive
    if ($dbman->table_exists($livetbl1)) {
        $rs = $DB->get_recordset('local_invoice');
        foreach ($rs as $r) {
            unset($r->id);

            // Forward-compatible: if your live table later adds invoiceprefix, it will be present and copied.
            // If not present, the archive table default '' applies.

            $DB->insert_record('local_invoice_archive', $r);
        }
        $rs->close();
    }
}

/**
 * Ensure archive tables exist with a schema compatible with current plugin data.
 * Includes invoiceprefix for forward-compatibility with your planned upgrades.
 */
function local_invoice_ensure_archive_tables(database_manager $dbman): void {
    // ---------------------------------------------------------------------
    // local_invoice_archive
    // ---------------------------------------------------------------------
    $t = new xmldb_table('local_invoice_archive');
    if (!$dbman->table_exists($t)) {
        $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $t->add_field('enrolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Forward-compatible: prefix used at time of issue (blank for FREE invoices).
        $t->add_field('invoiceprefix', XMLDB_TYPE_CHAR, '20', null, null, null, null);

        $t->add_field('invoicenumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $t->add_field('coursename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $t->add_field('cost', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);

        $t->add_field('company', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $t->add_field('vatnumber', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $t->add_field('address', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $t->add_field('city', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $t->add_field('province', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $t->add_field('postalcode', XMLDB_TYPE_CHAR, '20', null, null, null, null);

        $t->add_field('paymentmethod', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $t->add_field('currencysymbol', XMLDB_TYPE_CHAR, '5', null, XMLDB_NOTNULL, null, null);
        $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Keep the same uniqueness model as live data (one invoice per user+enrolid).
        $t->add_key('uniqueuserenrol', XMLDB_KEY_UNIQUE, ['userid', 'enrolid']);

        $dbman->create_table($t);
    } else {
        // If the archive table exists but lacks invoiceprefix (older archive), add it.
        $field = new xmldb_field('invoiceprefix', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        if (!$dbman->field_exists($t, $field)) {
            $dbman->add_field($t, $field);
        }
    }

    // ---------------------------------------------------------------------
    // local_invoice_user_archive
    // ---------------------------------------------------------------------
    $u = new xmldb_table('local_invoice_user_archive');
    if (!$dbman->table_exists($u)) {
        $u->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $u->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $u->add_field('company', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $u->add_field('vatnumber', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $u->add_field('address', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $u->add_field('city', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $u->add_field('province', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $u->add_field('postalcode', XMLDB_TYPE_CHAR, '20', null, null, null, null);

        $u->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $u->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $u->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $u->add_key('uniq_user', XMLDB_KEY_UNIQUE, ['userid']);

        $dbman->create_table($u);
    }
}
