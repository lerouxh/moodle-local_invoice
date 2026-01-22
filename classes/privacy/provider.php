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
 * Privacy API implementation for local_invoice.
 *
 * @package    local_invoice
 * @copyright  2026 eLearn Solutions
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_invoice\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_user_data_provider;
use core_privacy\local\request\writer;

/**
 * Provider.
 */
class provider implements metadata_provider, core_user_data_provider {

    /**
     * Describe the personal data stored by the plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_invoice', [
            'userid' => 'privacy:metadata:local_invoice:userid',
            'enrolid' => 'privacy:metadata:local_invoice:enrolid',
            'courseid' => 'privacy:metadata:local_invoice:courseid',
            'amount' => 'privacy:metadata:local_invoice:amount',
            'currency' => 'privacy:metadata:local_invoice:currency',
            'invoice_no' => 'privacy:metadata:local_invoice:invoice_no',
            'invoice_date' => 'privacy:metadata:local_invoice:invoice_date',
            'timecreated' => 'privacy:metadata:local_invoice:timecreated',
            'timemodified' => 'privacy:metadata:local_invoice:timemodified',
        ], 'privacy:metadata:local_invoice');

        $collection->add_database_table('local_invoice_user', [
            'userid' => 'privacy:metadata:local_invoice_user:userid',
            'company' => 'privacy:metadata:local_invoice_user:company',
            'vatnumber' => 'privacy:metadata:local_invoice_user:vatnumber',
            'address' => 'privacy:metadata:local_invoice_user:address',
            'city' => 'privacy:metadata:local_invoice_user:city',
            'province' => 'privacy:metadata:local_invoice_user:province',
            'postalcode' => 'privacy:metadata:local_invoice_user:postalcode',
            'timecreated' => 'privacy:metadata:local_invoice_user:timecreated',
            'timemodified' => 'privacy:metadata:local_invoice_user:timemodified',
        ], 'privacy:metadata:local_invoice_user');

        // Licensing server communication: no user personal data is transmitted.
        $collection->add_external_location_link('license_server', [
            'license_key' => 'privacy:metadata:license_server:license_key',
            'siteurl' => 'privacy:metadata:license_server:siteurl',
        ], 'privacy:metadata:license_server');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $hasinvoices = $DB->record_exists('local_invoice', ['userid' => $userid]);
        $hasbilling = $DB->record_exists('local_invoice_user', ['userid' => $userid]);
        if ($hasinvoices || $hasbilling) {
            $contextlist->add_context(context_user::instance($userid));
        }

        return $contextlist;
    }

    /**
     * Export all user data for the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || (int)$context->instanceid !== (int)$userid) {
                continue;
            }

            // Billing details.
            if ($billing = $DB->get_record('local_invoice_user', ['userid' => $userid])) {
                $data = (object)[
                    'company' => $billing->company,
                    'vatnumber' => $billing->vatnumber,
                    'address' => $billing->address,
                    'city' => $billing->city,
                    'province' => $billing->province,
                    'postalcode' => $billing->postalcode,
                    'timecreated' => $billing->timecreated,
                    'timemodified' => $billing->timemodified,
                ];
                writer::with_context($context)->export_data(['billing_details'], $data);
            }

            // Invoice records.
            $invoices = $DB->get_records('local_invoice', ['userid' => $userid], 'timecreated ASC');
            if ($invoices) {
                $out = [];
                foreach ($invoices as $inv) {
                    $out[] = (object)[
                        'courseid' => $inv->courseid,
                        'enrolid' => $inv->enrolid,
                        'amount' => $inv->amount,
                        'currency' => $inv->currency,
                        'invoice_no' => $inv->invoice_no,
                        'invoice_date' => $inv->invoice_date,
                        'timecreated' => $inv->timecreated,
                        'timemodified' => $inv->timemodified,
                    ];
                }
                writer::with_context($context)->export_data(['invoices'], (object)['items' => $out]);
            }
        }
    }

    /**
     * Delete all data in the supplied context.
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_user) {
            return;
        }

        $userid = (int)$context->instanceid;
        $DB->delete_records('local_invoice', ['userid' => $userid]);
        $DB->delete_records('local_invoice_user', ['userid' => $userid]);
    }

    /**
     * Delete all data for the user in the supplied contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_data_for_all_users_in_context($context);
        }
    }
}
