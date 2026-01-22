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
 
// /local/invoice/generate_invoice.php
// Generate (or re-generate) a PDF for a single invoice and save it to the user's Private files.
// Keeps background template and absolute positions unchanged.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib.php');
require_login();

global $DB, $CFG, $USER;

$enrolid = required_param('enrolid', PARAM_INT);
$userid  = (int)$USER->id;

// Page context (prevents "$PAGE->context not set" warnings in format_string etc).
$PAGE->set_url(new moodle_url('/local/invoice/generate_invoice.php', ['enrolid' => $enrolid]));
$PAGE->set_context(context_user::instance($userid));
$PAGE->set_pagelayout('embedded');

// Load invoice row (belongs to current user).
$inv = $DB->get_record('local_invoice', ['userid' => $userid, 'enrolid' => $enrolid], '*', MUST_EXIST);

if (!function_exists('local_invoice_prefix_for_filename')) {
    /**
     * Prefix used in the PDF filename.
     * IMPORTANT: Legacy behaviour — if stored prefix is empty, use "Invoice-" as TECHNICAL prefix
     * so previously-generated FREE invoices are still found and not duplicated.
	     *
	     * @param string|null $storedprefix The prefix stored on the invoice record (may be null).
	     * @return string The safe prefix to use in filenames.
     */
    function local_invoice_prefix_for_filename(?string $storedprefix): string {
        $prefix = trim((string)$storedprefix);
        // Avoid path separators in filenames.
        $prefix = preg_replace('/[\/\\\\:]/', '-', $prefix);
        if ($prefix === '') {
            $prefix = 'Invoice-';
        }
        return $prefix;
    }
}

if (!function_exists('local_invoice_pdf_filename')) {
    function local_invoice_pdf_filename(int $invoicenumber, string $prefix): string {
        return $prefix . $invoicenumber . '.pdf';
    }
}

// Config and site objects.
$cfg  = get_config('local_invoice');
$site = get_site();

// Use the prefix stored ON THE INVOICE RECORD (do not retroactively change old invoices).
$storedprefix = (string)($inv->invoiceprefix ?? '');
$storedprefix = trim($storedprefix);
$displaynumber = ($storedprefix !== '' ? $storedprefix : '') . (int)$inv->invoicenumber;

// Currency symbol (row wins, otherwise infer from payment method).
$currencysymbol = !empty($inv->currencysymbol) ? $inv->currencysymbol :
    (strtolower((string)$inv->paymentmethod) === 'paypal' ? '$' : 'R');

// Payment method
$paymentmethod = $inv->paymentmethod ?? '';

// Seller details from settings (names as per your existing code).
$companyname = !empty($cfg->companyname) ? format_string($cfg->companyname) : format_string($site->fullname);
$companyaddr = [];
if (!empty($cfg->address))    { $companyaddr[] = $cfg->address; }
if (!empty($cfg->city))       { $companyaddr[] = $cfg->city; }
if (!empty($cfg->province))   { $companyaddr[] = $cfg->province; }
if (!empty($cfg->postalcode)) { $companyaddr[] = $cfg->postalcode; }
$taxnumber = !empty($cfg->taxnumber) ? s($cfg->taxnumber) : '';

// Pro gating: if Pro is not active, never render Pro-only elements.
$proactive = local_invoice_is_pro_active();
if (!$proactive) {
    $taxnumber = '';
}

// Company block (line per part)
$companyaddrstr = implode("\n", array_map('s', $companyaddr));

// Buyer details (snapshotted on invoice row) — break into lines
$buyername  = s($inv->company ?? '');
$buyervat   = s($inv->vatnumber ?? '');
$buyeraddrparts = [];
if (!empty($inv->address))    { $buyeraddrparts[] = $inv->address; }
if (!empty($inv->city))       { $buyeraddrparts[] = $inv->city; }
if (!empty($inv->province))   { $buyeraddrparts[] = $inv->province; }
if (!empty($inv->postalcode)) { $buyeraddrparts[] = $inv->postalcode; }
$buyeraddrstr = implode("\n", array_map('s', $buyeraddrparts));

// Line item values
$coursename = format_string($inv->coursename ?? '');
$qty        = 1;

// Amounts
$total_incl = round((float)$inv->cost, 2);

// Tax display toggle (default OFF -> total only, no VAT maths/fields).
$showtaxbreakdown = (bool)get_config('local_invoice', 'showtaxbreakdown');

// Pro gating: no VAT breakdown when Pro is not active.
if (!$proactive) {
    $showtaxbreakdown = false;
}

// Defaults for printing
$vatpercent_display = '';
$unit_display_value = $total_incl; // When hiding tax, unit == total
$subtotal_display   = '';
$vat_amount_display = '';
$total_display      = $total_incl;

if ($showtaxbreakdown) {
    // If enabled, compute inclusive VAT breakdown.
    // taxpercent setting optional; default to 15 if not set/invalid.
    $taxpercent = (float)get_config('local_invoice', 'taxpercent');
    if ($taxpercent <= 0) {
        $taxpercent = 15.0;
    }

    $vatpercent_display = rtrim(rtrim(number_format($taxpercent, 2, '.', ''), '0'), '.') . '%';

    $subtotal_ex = round($total_incl / (1 + ($taxpercent / 100)), 2);
    $vat_amount  = round($total_incl - $subtotal_ex, 2);

    // With qty=1, unit ex == subtotal ex
    $unit_display_value = $subtotal_ex;
    $subtotal_display   = $subtotal_ex;
    $vat_amount_display = $vat_amount;
    $total_display      = $total_incl;
}

// Display strings
$unit_str        = number_format($unit_display_value, 2, '.', ' ');
$total_str       = number_format($total_display, 2, '.', ' ');
$subtotal_str    = ($showtaxbreakdown ? number_format((float)$subtotal_display, 2, '.', ' ') : '');
$vat_amount_str  = ($showtaxbreakdown ? number_format((float)$vat_amount_display, 2, '.', ' ') : '');

// Optional company logo from file area local_invoice/companylogo (Pro only).
$companylogo = '';
$fs = get_file_storage();
$sysctx = context_system::instance();
if ($proactive) {
    $files = $fs->get_area_files($sysctx->id, 'local_invoice', 'companylogo', 0, 'itemid, filepath, filename', false);
    if ($files) {
        $file = reset($files);
        $logotemp = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($logotemp, $file->get_content());
        $companylogo = $logotemp;
    }
}

// Build PDF using your original absolute positions / background
require_once(__DIR__ . '/tcpdf/tcpdf.php');

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0, true);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();

// Background template.
// NOTE: stored with an unobvious name (still a PNG internally). This is a deterrent only.
$template = __DIR__ . '/assets/invoice_bg';
if (file_exists($template)) {
    $pdf->Image($template, 0, 0, 210, 297, 'PNG');
}

// Company logo (optional)
if ($companylogo && file_exists($companylogo)) {
    $pdf->Image($companylogo, 10, 12, 63, 0, '', '', '', false, 300);
}

// Right-hand company block (line breaks respected via MultiCell)
$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(138, 15);
$companyblock = $companyname;
if ($companyaddrstr !== '') {
    $companyblock .= "\n" . $companyaddrstr;
}
if ($taxnumber) {
    $companyblock .= "\n" . 'TAX/VAT: ' . $taxnumber;
}
$pdf->MultiCell(65, 6, $companyblock, 0, 'R');

// Invoice number + ISO date (YYYY-MM-DD)
$pdf->SetFont('helvetica', '', 11);
$pdf->SetXY(115, 63);
$pdf->Cell(35, 7, '' . $displaynumber, 0, 0, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(168, 63);
$pdf->Cell(25, 7, date('Y-m-d', $inv->timecreated ?: time()), 0, 0, 'C');

// Bill-to block (line per part)
$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(12, 88);
$billto  = ($buyername ? $buyername . "\n" : '');
$billto .= ($buyervat ? 'VAT/Tax: ' . $buyervat . "\n" : '');
$billto .= $buyeraddrstr;
$pdf->MultiCell(80, 5, $billto, 0, 'L');

// Single line item
$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(12, 138);
$pdf->Cell(68, 8, $coursename, 0, 0, 'L');
$pdf->Cell(29, 8, (string)$qty, 0, 0, 'C');

// VAT% column: print only if tax breakdown enabled.
$pdf->Cell(22, 8, ($showtaxbreakdown ? $vatpercent_display : ''), 0, 0, 'C');

// Unit and Total
$pdf->Cell(38, 8, $currencysymbol . ' ' . $unit_str, 0, 0, 'R');
$pdf->Cell(26, 8, $currencysymbol . ' ' . $total_str, 0, 0, 'R');

// Totals box (right-aligned):
// - If tax breakdown enabled: print subtotal, VAT, total.
// - Otherwise: print blanks for first two lines and print total on the last line.
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetXY(168, 162);
$pdf->Cell(32, 7, ($showtaxbreakdown ? $currencysymbol . ' ' . $subtotal_str : ''), 0, 2, 'R');
$pdf->SetX(168);
$pdf->Cell(32, 7, ($showtaxbreakdown ? $currencysymbol . ' ' . $vat_amount_str : ''), 0, 2, 'R');
$pdf->SetX(168);
$pdf->Cell(32, 7, $currencysymbol . ' ' . $total_str, 0, 2, 'R');

// Payment method
$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(15, 169);
$pdf->Cell(40, 8, $paymentmethod, 0, 0, 'L');

// Free-version watermark (deterrent).
// Use a PNG image stored without an extension (assets/wm).
if (!$proactive) {
    $wm = __DIR__ . '/assets/wm';
    if (file_exists($wm)) {
        // Place near bottom with margins.
        // Scale by width (190mm) and preserve aspect ratio.
        $pdf->Image($wm, 20, 275, 170, 0, 'PNG');
    } else {
        // Fallback (should not happen): simple text watermark.
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(160, 160, 160);
        $pdf->SetXY(10, 286);
        $pdf->MultiCell(190, 5, 'Invoice generation plugin provided by eLearn Solutions - www.elearnsolutions.co.za', 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
    }
}

// Save into Private files and redirect back (no output before redirect)
$ucontext = context_user::instance($userid);

// Ensure /Invoices/ exists.
if (!$fs->file_exists($ucontext->id, 'user', 'private', 0, '/Invoices/', '.')) {
    $fs->create_directory($ucontext->id, 'user', 'private', 0, '/Invoices/');
}

// Filename prefix MUST use the STORED prefix, not current settings.
// (Legacy: stored blank -> "Invoice-" technical prefix).
$fileprefix = local_invoice_prefix_for_filename($inv->invoiceprefix ?? '');
$filename   = local_invoice_pdf_filename((int)$inv->invoicenumber, $fileprefix);

// Replace existing copy if present.
if ($old = $fs->get_file($ucontext->id, 'user', 'private', 0, '/Invoices/', $filename)) {
    $old->delete();
}

// Store PDF
$pdfcontent = $pdf->Output('', 'S'); // bytes
$filerecord = [
    'contextid' => $ucontext->id,
    'component' => 'user',
    'filearea'  => 'private',
    'itemid'    => 0,
    'filepath'  => '/Invoices/',
    'filename'  => $filename
];
$fs->create_file_from_string($filerecord, $pdfcontent);

// Clean temp logo
if ($companylogo && file_exists($companylogo)) {
    @unlink($companylogo);
}

// Back to controller
redirect(new moodle_url('/local/invoice/invoice.php'));
