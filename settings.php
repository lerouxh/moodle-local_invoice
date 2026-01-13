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

require_once(__DIR__ . '/classes/admin_setting_prolicense.php');

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_invoice_settings', get_string('pluginname', 'local_invoice'));

    // Always: License Key field.
    $settings->add(new local_invoice_admin_setting_prolicense_key(
        'local_invoice/license_key',
        get_string('license_key', 'local_invoice'),
        get_string('license_key_desc', 'local_invoice'),
        'free',
        PARAM_TEXT
    ));

    // Always: Free fields.
    $settings->add(new local_invoice_admin_setting_prolicense_text_free(
        'local_invoice/companyname',
        get_string('companyname', 'local_invoice'),
        get_string('companyname_desc', 'local_invoice'),
        '',
        PARAM_TEXT
    ));
    $settings->add(new local_invoice_admin_setting_prolicense_text_free(
        'local_invoice/address',
        get_string('address', 'local_invoice'),
        get_string('address_desc', 'local_invoice'),
        '',
        PARAM_TEXT
    ));
    $settings->add(new local_invoice_admin_setting_prolicense_text_free(
        'local_invoice/city',
        get_string('city', 'local_invoice'),
        get_string('city_desc', 'local_invoice'),
        '',
        PARAM_TEXT
    ));
    $settings->add(new local_invoice_admin_setting_prolicense_text_free(
        'local_invoice/postalcode',
        get_string('postalcode', 'local_invoice'),
        get_string('postalcode_desc', 'local_invoice'),
        '',
        PARAM_TEXT
    ));

    // Check the license key value and conditionally add pro fields.
    $licensekey = get_config('local_invoice', 'license_key');
    $licensevalid = ($licensekey && $licensekey !== 'free'); // Key present and not 'free'

    if ($licensevalid) {

        // Only add these fields if Pro is active.
        $settings->add(new local_invoice_admin_setting_prolicense_file(
            'local_invoice/companylogo',
            get_string('companylogo', 'local_invoice'),
            get_string('companylogo_desc', 'local_invoice'),
            'companylogo'
        ));

        $settings->add(new local_invoice_admin_setting_prolicense_text(
            'local_invoice/taxnumber',
            get_string('taxnumber', 'local_invoice'),
            get_string('taxnumber_desc', 'local_invoice'),
            '',
            PARAM_TEXT
        ));

        $settings->add(new local_invoice_admin_setting_prolicense_text(
            'local_invoice/invoiceprefix',
            get_string('invoiceprefix', 'local_invoice'),
            get_string('invoiceprefix_desc', 'local_invoice'),
            '',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_configtext(
            'local_invoice/invoicestart',
            get_string('invoicestart', 'local_invoice'),
            get_string('invoicestart_desc', 'local_invoice'),
            900001,
            PARAM_INT
        ));

        // NEW (Pro): Tax display controls (default hides VAT breakdown).
        $settings->add(new admin_setting_heading(
            'local_invoice/taxsettings',
            get_string('taxsettings_heading', 'local_invoice'),
            get_string('taxsettings_desc', 'local_invoice')
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_invoice/showtaxbreakdown',
            get_string('showtaxbreakdown', 'local_invoice'),
            get_string('showtaxbreakdown_desc', 'local_invoice'),
            0
        ));

        $settings->add(new admin_setting_configtext(
            'local_invoice/taxpercent',
            get_string('taxpercent', 'local_invoice'),
            get_string('taxpercent_desc', 'local_invoice'),
            15,
            PARAM_FLOAT
        ));
    }

    $settings->add(new admin_setting_heading(
        'local_invoice/profeatures',
        get_string('profeatures_heading', 'local_invoice'),
        get_string('profeatures_desc', 'local_invoice')
    ));

    $ADMIN->add('localplugins', $settings);
}
