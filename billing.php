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

// /local/invoice/billing.php
// Create/edit a user's saved billing details used for future invoices.
// Requires table {local_invoice_user} (unique row per userid).

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $CFG, $PAGE, $OUTPUT, $USER;

$userid = $USER->id;
$return = optional_param('return', '', PARAM_LOCALURL);

$context = context_user::instance($userid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/invoice/billing.php', ['return' => $return]));
$PAGE->set_pagelayout('standard');

$pluginname = get_string('pluginname', 'local_invoice');
$heading    = get_string('billing_heading', 'local_invoice');
$PAGE->set_title($pluginname . ': ' . $heading);
$PAGE->set_heading($pluginname);

require_once($CFG->libdir . '/formslib.php');

class local_invoice_billing_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        $label_company = get_string('billing_company', 'local_invoice');
        $label_vat     = get_string('billing_vatnumber', 'local_invoice');
        $label_address = get_string('billing_address', 'local_invoice');
        $label_city    = get_string('billing_city', 'local_invoice');
        $label_prov    = get_string('billing_province', 'local_invoice');
        $label_post    = get_string('billing_postalcode', 'local_invoice');

        $mform->addElement('text', 'company', $label_company);
        $mform->setType('company', PARAM_TEXT);
        $mform->addRule('company', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'vatnumber', $label_vat);
        $mform->setType('vatnumber', PARAM_TEXT);

        $mform->addElement('text', 'address', $label_address);
        $mform->setType('address', PARAM_TEXT);

        $mform->addElement('text', 'city', $label_city);
        $mform->setType('city', PARAM_TEXT);

        $mform->addElement('text', 'province', $label_prov);
        $mform->setType('province', PARAM_TEXT);

        $mform->addElement('text', 'postalcode', $label_post);
        $mform->setType('postalcode', PARAM_TEXT);

        $mform->addElement('hidden', 'return');
        $mform->setType('return', PARAM_LOCALURL);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}

$mform = new local_invoice_billing_form(null, null, 'post', '', ['autocomplete' => 'on']);
$mform->set_data((object)['return' => $return]);

// Load existing record if any to prefill BEFORE any output.
$existing = $DB->get_record('local_invoice_user', ['userid' => $userid]);
if ($existing) {
    $mform->set_data($existing);
}

// Handle cancel/save BEFORE output (prevents "redirect before output" warning).
if ($mform->is_cancelled()) {
    $dest = !empty($return) ? new moodle_url($return) : new moodle_url('/local/invoice/invoice.php');
    redirect($dest);
} else if ($data = $mform->get_data()) {
    require_sesskey();
    $now = time();

    if ($existing) {
        $update = (object)[
            'id'          => $existing->id,
            'company'     => trim($data->company ?? ''),
            'vatnumber'   => trim($data->vatnumber ?? ''),
            'address'     => trim($data->address ?? ''),
            'city'        => trim($data->city ?? ''),
            'province'    => trim($data->province ?? ''),
            'postalcode'  => trim($data->postalcode ?? ''),
            'timemodified'=> $now
        ];
        $DB->update_record('local_invoice_user', $update);
        $flash = get_string('changessaved');
    } else {
        $insert = (object)[
            'userid'      => $userid,
            'company'     => trim($data->company ?? ''),
            'vatnumber'   => trim($data->vatnumber ?? ''),
            'address'     => trim($data->address ?? ''),
            'city'        => trim($data->city ?? ''),
            'province'    => trim($data->province ?? ''),
            'postalcode'  => trim($data->postalcode ?? ''),
            'timecreated' => $now,
            'timemodified'=> $now
        ];
        $DB->insert_record('local_invoice_user', $insert);
        $flash = get_string('changessaved');
    }

    $notify = class_exists('\\core\\output\\notification') ? \core\output\notification::NOTIFY_SUCCESS : null;
    $dest = !empty($return) ? new moodle_url($return) : new moodle_url('/local/invoice/invoice.php');
    redirect($dest, $flash, 0, $notify);
}

// From here on we may output safely.
echo $OUTPUT->header();

// Permanent notice (billing page helper).
echo $OUTPUT->notification(get_string('billing_regenerate_note', 'local_invoice'), 'info');

// Intro note only when first capturing.
if (!$existing) {
    echo $OUTPUT->notification(get_string('billing_intro_note', 'local_invoice'), 'info');
}

echo $OUTPUT->heading($heading);
$mform->display();
echo $OUTPUT->footer();
