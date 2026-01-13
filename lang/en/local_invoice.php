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
 
// /local/invoice/lang/en/local_invoice.php

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Invoice Generator';

$string['license_key'] = 'Pro License Key';
$string['license_key_desc'] = "Enter your Invoice Pro license key here. Use the code 'free' if you don't have a license key.";
$string['free_version_notice'] = "You are using the free version. Enter a valid license key to unlock all features.";
$string['pro_license_required'] = 'You must enter and validate a valid Pro License Key before editing these settings.';
$string['invalid_license'] = 'Invalid license key. Please check and try again.';

$string['companylogo'] = 'Company Logo';
$string['companylogo_desc'] = 'Upload the company logo for invoices (recommended size: 250x100px, PNG/JPG)';

$string['companyname'] = 'Company Name';
$string['companyname_desc'] = 'Company name as it will appear on invoices';

$string['address'] = 'Address';
$string['address_desc'] = 'Company street address';

$string['city'] = 'City';
$string['city_desc'] = 'Company city';

$string['postalcode'] = 'Postal Code';
$string['postalcode_desc'] = 'Company postal code';

$string['taxnumber'] = 'Tax Number';
$string['taxnumber_desc'] = 'Company TAX/VAT number';

$string['invoiceprefix'] = 'Invoice Prefix';
$string['invoiceprefix_desc'] = 'Enter a prefix for all invoice numbers, e.g. INV- or 2025-, etc. Leave blank for no prefix.';

$string['invoicestart'] = 'Invoice Starting Number';
$string['invoicestart_desc'] = 'The first invoice number to use for the current prefix series (default: 1001).';

$string['taxsettings_heading'] = 'Tax settings';
$string['taxsettings_desc'] = 'Control whether tax/VAT is shown as a breakdown on the PDF.';

$string['showtaxbreakdown'] = 'Show tax/VAT breakdown';
$string['showtaxbreakdown_desc'] = 'If enabled, the invoice will show subtotal, tax/VAT, and total. If disabled, only the total amount is shown.';

$string['taxpercent'] = 'Tax/VAT percentage';
$string['taxpercent_desc'] = 'Percentage used for tax/VAT breakdown calculations (e.g. 15 for 15%). Only used when tax/VAT breakdown is enabled.';

$string['profeatures_heading'] = 'Upgrade to Pro';
$string['profeatures_desc'] = '
<ul>
    <li>Add a <strong>company logo</strong> to your invoice</li>
    <li>Add a <strong>tax number</strong> field</li>
    <li>Set a <strong>prefix for your invoice numbers</strong></li>
</ul>
<p>Visit our Online Shop at <a href="https://shop.elearnsolutions.co.za">shop.elearnsolutions.co.za</a> to purchase a Pro key and to unlock these features.</p>';