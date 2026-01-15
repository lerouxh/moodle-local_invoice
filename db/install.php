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
 * Plugin install hook.
 * - Ensures Custom menu items exist.
 * - If archive tables exist (created during a prior uninstall), restores invoice history.
 */
function xmldb_local_invoice_install() {
    global $DB;

    $dbman = $DB->get_manager();

    // ---------------------------------------------------------------------
    // 0) OPTIONAL: Restore archived data (if uninstall archived it previously)
    // ---------------------------------------------------------------------
    if (!function_exists('local_invoice_restore_from_archive')) {
        /**
         * Restore archived data into live plugin tables if (and only if) live tables are empty.
         * Leaves archives in place unless a full restore succeeds, in which case it drops them.
	     *
	     * @param moodle_database $DB The Moodle database handle.
	     * @return void
         */
        function local_invoice_restore_from_archive(moodle_database $DB): void {
            $dbman = $DB->get_manager();

            $archinvoice = new xmldb_table('local_invoice_archive');
            $archuser    = new xmldb_table('local_invoice_user_archive');

            // Nothing to do if archives do not exist.
            if (!$dbman->table_exists($archinvoice) && !$dbman->table_exists($archuser)) {
                return;
            }

            // Only restore into empty live tables (prevents overwriting / duplication).
            $liveinvoicesempty = ($DB->count_records('local_invoice') === 0);
            $liveuserempty     = ($DB->count_records('local_invoice_user') === 0);

            // Helper: copy records from src to dst using only dst columns (excluding id).
            $copy = function(string $srctable, string $dsttable) use ($DB): array {
                $dstcolsinfo = $DB->get_columns($dsttable);
                $dstcols = array_keys($dstcolsinfo);
                $dstcols = array_flip($dstcols);

                $src = $DB->get_records($srctable);
                $total = count($src);
                $inserted = 0;

                foreach ($src as $r) {
                    $o = new stdClass();
                    foreach ($r as $k => $v) {
                        if ($k === 'id') {
                            continue;
                        }
                        if (isset($dstcols[$k])) {
                            $o->$k = $v;
                        }
                    }
                    // Skip empty objects (should not happen, but be safe).
                    if (empty((array)$o)) {
                        continue;
                    }
                    $DB->insert_record($dsttable, $o);
                    $inserted++;
                }

                return [$total, $inserted];
            };

            try {
                // Restore billing details first (local_invoice rows reference these fields in practice).
                $userok = true;
                if ($dbman->table_exists($archuser) && $liveuserempty) {
                    [$tot, $ins] = $copy('local_invoice_user_archive', 'local_invoice_user');
                    $userok = ($tot === $ins);
                }

                $invok = true;
                if ($dbman->table_exists($archinvoice) && $liveinvoicesempty) {
                    [$tot, $ins] = $copy('local_invoice_archive', 'local_invoice');
                    $invok = ($tot === $ins);
                }

                // If we fully restored what we intended to restore, drop archives to avoid duplicates.
                // (If a table was not restored because live table was not empty, we do not drop it.)
                if ($liveuserempty && $dbman->table_exists($archuser) && $userok) {
                    $dbman->drop_table($archuser);
                }
                if ($liveinvoicesempty && $dbman->table_exists($archinvoice) && $invok) {
                    $dbman->drop_table($archinvoice);
                }

            } catch (Throwable $e) {
                // Never fail installation because of restore problems.
                // Leave archives in place for manual intervention.
                if (function_exists('debugging')) {
                    debugging('local_invoice: archive restore failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }
    }

    // Attempt restore if archives exist (safe no-op otherwise).
    local_invoice_restore_from_archive($DB);

    // --------------------------------------------------------
    // 1) Existing Custom menu setup (kept as-is from your plugin)
    // --------------------------------------------------------
    $custommenu = (string) get_config('core', 'custommenuitems');

    // Public menu.
    $publicparent = 'My invoices';
    $viewline     = '-View invoices|/local/invoice/invoice.php';
    $billline     = '-Billing details|/local/invoice/billing.php';

    // Admin-only menu (to be hidden for non-config users via JS).
    $adminparent  = 'Invoices (admins)';
    $adminline    = '-Settings|/admin/settings.php?section=local_invoice_settings';

    if ($custommenu === '') {
        $custommenu =
            $publicparent . "\n" . $viewline . "\n" . $billline . "\n\n" .
            $adminparent  . "\n" . $adminline;

        set_config('custommenuitems', $custommenu);
        return;
    }

    // -------------------------
    // Ensure public block exists
    // -------------------------
    if (strpos($custommenu, $publicparent) === false) {
        $custommenu .= "\n\n" . $publicparent . "\n" . $viewline . "\n" . $billline;
    } else {
        if (strpos($custommenu, $viewline) === false) {
            $custommenu = preg_replace(
                '/^' . preg_quote($publicparent, '/') . '$/m',
                $publicparent . "\n" . $viewline,
                $custommenu,
                1
            );
        }

        if (strpos($custommenu, $billline) === false) {
            if (strpos($custommenu, $viewline) !== false) {
                $custommenu = str_replace($viewline, $viewline . "\n" . $billline, $custommenu);
            } else {
                $custommenu = preg_replace(
                    '/^' . preg_quote($publicparent, '/') . '$/m',
                    $publicparent . "\n" . $billline,
                    $custommenu,
                    1
                );
            }
        }
    }

    // -------------------------
    // Ensure admin block exists
    // -------------------------
    if (strpos($custommenu, $adminparent) === false) {
        $custommenu .= "\n\n" . $adminparent . "\n" . $adminline;
    } else {
        if (strpos($custommenu, $adminline) === false) {
            $custommenu = preg_replace(
                '/^' . preg_quote($adminparent, '/') . '$/m',
                $adminparent . "\n" . $adminline,
                $custommenu,
                1
            );
        }
    }

    set_config('custommenuitems', $custommenu);
}
