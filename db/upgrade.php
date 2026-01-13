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
 
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_invoice.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_invoice_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // 2026010901: Add invoiceprefix (stored per invoice to avoid duplicate PDFs after prefix changes).
    if ($oldversion < 2026010901) {
        $table = new xmldb_table('local_invoice');

        // Step 1: Add field as NULLABLE first (no default).
        $field = new xmldb_field(
            'invoiceprefix',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            null,       // NOT NULL not enforced yet
            null,
            null,       // no DEFAULT (prevents the XMLDB warning)
            'enrolid'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Step 2: Backfill existing rows to empty string (meaning "no prefix").
        $DB->execute("UPDATE {local_invoice} SET invoiceprefix = '' WHERE invoiceprefix IS NULL");

        // Step 3: Enforce NOT NULL (still no default).
        $fieldnotnull = new xmldb_field('invoiceprefix', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null, 'enrolid');
        $dbman->change_field_notnull($table, $fieldnotnull);

        // Step 4: Add unique index per series (prefix + number). If duplicates exist, fall back to non-unique.
        $uniqueindex = new xmldb_index('invoice_series_unique', XMLDB_INDEX_UNIQUE, ['invoiceprefix', 'invoicenumber']);
        $plainindex  = new xmldb_index('invoice_series_idx', XMLDB_INDEX_NOTUNIQUE, ['invoiceprefix', 'invoicenumber']);

        if (!$dbman->index_exists($table, $uniqueindex) && !$dbman->index_exists($table, $plainindex)) {
            $dupes = $DB->get_records_sql(
                "SELECT invoiceprefix, invoicenumber, COUNT(1) AS c
                   FROM {local_invoice}
               GROUP BY invoiceprefix, invoicenumber
                 HAVING COUNT(1) > 1"
            );

            if (empty($dupes)) {
                $dbman->add_index($table, $uniqueindex);
            } else {
                // Do not break upgrade if historical data already has duplicates.
                $dbman->add_index($table, $plainindex);
            }
        }

        upgrade_plugin_savepoint(true, 2026010901, 'local', 'invoice');
    }

    return true;
}
