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
 
// /local/invoice/invoice.php

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER;

$userid   = (int)$USER->id;
$filepath = '/Invoices/'; // Private files folder where PDFs live.

if (!function_exists('local_invoice_prefix_for_storage')) {
    /**
     * Prefix stored in DB and printed on invoice.
     * Keep empty string if not configured (FREE invoices typically had no printed prefix).
     */
    function local_invoice_prefix_for_storage(string $prefix): string {
        $prefix = trim($prefix);
        // Avoid path separators in stored prefix (also used in filenames later).
        $prefix = preg_replace('/[\/\\\\:]/', '-', $prefix);
        return $prefix; // may be ''
    }
}

if (!function_exists('local_invoice_prefix_for_filename')) {
    /**
     * Prefix used in the PDF filename.
     * Legacy behaviour: if stored prefix is empty, filename still uses "Invoice-" as technical prefix.
     */
    function local_invoice_prefix_for_filename(?string $storedprefix): string {
        $prefix = trim((string)$storedprefix);
        $prefix = preg_replace('/[\/\\\\:]/', '-', $prefix);
        if ($prefix === '') {
            $prefix = 'Invoice-';
        }
        return $prefix;
    }
}

if (!function_exists('local_invoice_pdf_filename')) {
    function local_invoice_pdf_filename(int $invoicenumber, string $fileprefix): string {
        return $fileprefix . $invoicenumber . '.pdf';
    }
}

if (!function_exists('local_invoice_currency_for_method')) {
    function local_invoice_currency_for_method(string $method): string {
        return (strtolower($method) === 'paypal') ? '$' : 'R'; // ZAR for Payfast/others
    }
}

// 1) Ensure per-user billing exists; if not, capture first then return here.
$billing = $DB->get_record('local_invoice_user', ['userid' => $userid]);
if (!$billing) {
    $return = (new moodle_url('/local/invoice/invoice.php'))->out_as_local_url(false);
    redirect(new moodle_url('/local/invoice/billing.php', ['return' => $return]));
}

// 2) Fetch Payfast/PayPal enrolments for this user.
$sql = "SELECT e.id AS enrolid, c.fullname AS coursename, e.cost, e.enrol
          FROM {user_enrolments} ue
          JOIN {enrol} e ON ue.enrolid = e.id
          JOIN {course} c ON e.courseid = c.id
         WHERE ue.userid = :userid
           AND e.enrol IN ('payfast', 'paypal')";
$enrolments = $DB->get_records_sql($sql, ['userid' => $userid]);

if (empty($enrolments)) {
    redirect(new moodle_url('/local/invoice/no_invoice.php'));
}

// 3) Ensure /Invoices/ exists in Private files.
$fs = get_file_storage();
$ucontext = context_user::instance($userid);

if (!$fs->file_exists($ucontext->id, 'user', 'private', 0, $filepath, '.')) {
    $fs->create_directory($ucontext->id, 'user', 'private', 0, $filepath);
}

// 4) Read configured invoice start number (fallback to 1001 if not set/invalid).
$cfgstart = (int) get_config('local_invoice', 'invoicestart');
$cfgstart = $cfgstart > 0 ? $cfgstart : 900001;

// 5) Read current configured prefix (this applies only to NEW invoices from now on).
$cfgprefix = (string) get_config('local_invoice', 'invoiceprefix');
$seriesprefix = local_invoice_prefix_for_storage($cfgprefix);

// Cache next numbers per prefix series for this request (efficient if multiple invoices created).
$nextByPrefix = [];

/**
 * Get next invoice number for a given prefix series.
 * Uses: max(existing)+1, but never below configured start.
 */
$getNextNumber = function(string $prefix) use ($DB, $cfgstart, &$nextByPrefix): int {
    if (!array_key_exists($prefix, $nextByPrefix)) {
        $maxno = $DB->get_field_sql(
            "SELECT MAX(invoicenumber) FROM {local_invoice} WHERE invoiceprefix = ?",
            [$prefix]
        );
        $maxno = $maxno ? (int)$maxno : 0;
        $nextByPrefix[$prefix] = max($cfgstart, $maxno + 1);
    }
    $next = $nextByPrefix[$prefix];
    $nextByPrefix[$prefix] = $next + 1;
    return $next;
};

foreach ($enrolments as $enrol) {
    $inv = $DB->get_record('local_invoice', ['userid' => $userid, 'enrolid' => $enrol->enrolid]);

    if (!$inv) {
        $nextno = $getNextNumber($seriesprefix);

        $inv = (object)[
            'userid'         => $userid,
            'enrolid'        => $enrol->enrolid,

            // NEW: store prefix used at time of issue (do not retroactively change).
            'invoiceprefix'  => $seriesprefix,

            'invoicenumber'  => $nextno,
            'coursename'     => $enrol->coursename,
            'cost'           => (string)$enrol->cost,
            'paymentmethod'  => ucfirst($enrol->enrol),
            'currencysymbol' => local_invoice_currency_for_method($enrol->enrol),
            'timecreated'    => time(),

            // Snapshot buyer details.
            'company'        => trim($billing->company ?? ''),
            'vatnumber'      => trim($billing->vatnumber ?? ''),
            'address'        => trim($billing->address ?? ''),
            'city'           => trim($billing->city ?? ''),
            'province'       => trim($billing->province ?? ''),
            'postalcode'     => trim($billing->postalcode ?? ''),
        ];

        $inv->id = $DB->insert_record('local_invoice', $inv);

        // Generate the PDF for this invoice.
        redirect(new moodle_url('/local/invoice/generate_invoice.php', ['enrolid' => $enrol->enrolid]));
    }

    // If an invoice row exists, ensure the PDF exists; if not, regenerate it.
    // IMPORTANT: use the prefix stored on the invoice (prevents duplicates after settings changes).
    $fileprefix = local_invoice_prefix_for_filename($inv->invoiceprefix ?? '');
    $filename   = local_invoice_pdf_filename((int)$inv->invoicenumber, $fileprefix);

    $pdf = $fs->get_file($ucontext->id, 'user', 'private', 0, $filepath, $filename);
    if (!$pdf) {
        redirect(new moodle_url('/local/invoice/generate_invoice.php', ['enrolid' => $enrol->enrolid]));
    }
}

// 6) Nothing to do → open Private files at /Invoices/.
redirect(new moodle_url('/user/files.php', ['folder' => $filepath]));
