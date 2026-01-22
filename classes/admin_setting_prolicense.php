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
 * Plugin settings helpers for Pro licensing.
 *
 * NOTE: This plugin does NOT contact the licensing server during normal use.
 * The remote call happens only when an admin submits a new Pro key.
 *
 * @package    local_invoice
 * @copyright  2026 eLearn Solutions
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// We rely on helper functions for local cached validity checks.
require_once($CFG->dirroot . '/local/invoice/lib.php');

/**
 * Plain text setting (free) - no licensing checks.
 */
class local_invoice_admin_setting_prolicense_text_free extends admin_setting_configtext {
    public function write_setting($data) {
        return parent::write_setting($data);
    }
}

/**
 * Text setting that should only be editable while Pro is active.
 */
class local_invoice_admin_setting_prolicense_text extends admin_setting_configtext {
    public function write_setting($data) {
        if (!local_invoice_is_pro_active()) {
            // Prevent saving Pro-only values while expired (values remain stored, but not editable).
            return get_string('pro_license_required', 'local_invoice');
        }
        return parent::write_setting($data);
    }
}

/**
 * Stored file setting that should only be editable while Pro is active.
 */
class local_invoice_admin_setting_prolicense_file extends admin_setting_configstoredfile {
    public function write_setting($data) {
        if (!local_invoice_is_pro_active()) {
            // If Pro is not active, wipe the logo area to ensure invoices never render it.
            $fs = get_file_storage();
            $context = context_system::instance();
            $fs->delete_area_files($context->id, 'local_invoice', 'companylogo');
            return get_string('pro_license_required', 'local_invoice');
        }
        return parent::write_setting($data);
    }
}

/**
 * License key setting.
 *
 * - "free" keeps the plugin in free mode and clears local Pro validity.
 * - A NEW non-free key triggers a single remote validation/activation.
 * - If validation succeeds, the returned valid_until is cached locally in config.
 */
class local_invoice_admin_setting_prolicense_key extends admin_setting_configtext {

    /**
     * Validate, activate (if applicable), and cache valid_until.
     *
     * @param mixed $data
     * @return string empty on success, or a message on failure
     */
    public function write_setting($data) {
        $data = trim((string)$data);
        if ($data === '') {
            $data = 'free';
        }

        // Free mode.
        if ($data === 'free') {
            set_config('vu', 'free', 'local_invoice');

            // Ensure logo is removed when downgrading.
            $fs = get_file_storage();
            $context = context_system::instance();
            $fs->delete_area_files($context->id, 'local_invoice', 'companylogo');

            // Keep invoice prefix/start (audit), but disable VAT breakdown usage.
            set_config('taxnumber', '', 'local_invoice');
            set_config('showtaxbreakdown', 0, 'local_invoice');

            return parent::write_setting($data);
        }

        // If key did not change, we normally do not contact the licensing server again.
        // Exception: if the cached local validity has expired and an admin clicks Save,
        // do a single "check" call to the server so it can log "license expired".
        $existing = trim((string)get_config('local_invoice', 'license_key'));
        if ($existing !== '' && hash_equals($existing, $data)) {
            $vu = (string)get_config('local_invoice', 'vu');
            $expired = ($vu !== 'free' && ctype_digit($vu) && ((int)$vu <= time()));
            if ($expired) {
                // Best-effort: log expiry on the server. If the server indicates the key is valid again
                // (e.g. extended server-side), refresh the local cache.
                $this->ping_server_for_logging($data, 'expired_save');
            }
            return parent::write_setting($data);
        }

        $result = $this->validate_with_server($data);
        if (empty($result['ok'])) {
            return !empty($result['message']) ? $result['message'] : get_string('invalid_license', 'local_invoice');
        }

        // Cache the valid_until timestamp locally.
        set_config('vu', (string)$result['validuntil_ts'], 'local_invoice');

        return parent::write_setting($data);
    }

    /**
     * Calls the licensing server once to validate and (if needed) activate this key.
     *
     * Expected JSON response (minimum):
     *   {"status":"valid","valid_until":"YYYY-MM-DD"}
     *
     * @param string $key
     * @return array{ok:bool, validuntil_ts?:int, message?:string}
     */
    protected function validate_with_server(string $key): array {
        global $CFG, $SITE;

        if ($key === 'free' || $key === '') {
            return ['ok' => false, 'message' => get_string('invalid_license', 'local_invoice')];
        }

        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $siteurl = $CFG->wwwroot;
        $sitename = $SITE->fullname;

        // MUST match the token expected by the licensing server.
        $token = 'KKJFRJDFGGKDEERPQZZATTF';

        $url = 'https://moodle.elso.co.za/licensing.php'
            . '?key=' . urlencode($key)
            . '&siteurl=' . urlencode($siteurl)
            . '&sitename=' . urlencode($sitename)
            . '&token=' . urlencode($token);

        $response = $curl->get($url);
        if ($response === false || trim((string)$response) === '') {
            return ['ok' => false, 'message' => get_string('license_server_error', 'local_invoice')];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || empty($decoded['status'])) {
            return ['ok' => false, 'message' => get_string('license_server_error', 'local_invoice')];
        }

        if ((string)$decoded['status'] !== 'valid') {
            $msg = !empty($decoded['message']) ? (string)$decoded['message'] : get_string('invalid_license', 'local_invoice');
            return ['ok' => false, 'message' => $msg];
        }

        // Accept a few possible field names to be robust.
        $rawvaliduntil = $decoded['valid_until'] ?? ($decoded['validuntil'] ?? ($decoded['validUntil'] ?? ''));
        $rawvaliduntil = trim((string)$rawvaliduntil);

        $ts = 0;
        if ($rawvaliduntil !== '') {
            // If the server returns a timestamp.
            if (ctype_digit($rawvaliduntil)) {
                $ts = (int)$rawvaliduntil;
            } else {
                $parsed = strtotime($rawvaliduntil);
                if ($parsed !== false) {
                    $ts = (int)$parsed;
                }
            }
        }

        // Safety: a "valid" response with no usable future valid_until is treated as expired.
        if ($ts <= time()) {
            return ['ok' => false, 'message' => get_string('pro_license_expired', 'local_invoice')];
        }

        return ['ok' => true, 'validuntil_ts' => $ts];
    }

    /**
     * Best-effort server call used only to create a server-side audit trail (e.g. "license expired").
     *
     * This MUST NOT be called during normal invoice generation.
     *
     * @param string $key
     * @param string $event
     * @return void
     */
    protected function ping_server_for_logging(string $key, string $event): void {
        global $CFG, $SITE;

        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $siteurl = $CFG->wwwroot;
        $sitename = $SITE->fullname;
        $token = 'KKJFRJDFGGKDEERPQZZATTF';

        $url = 'https://moodle.elso.co.za/licensing.php'
            . '?key=' . urlencode($key)
            . '&siteurl=' . urlencode($siteurl)
            . '&sitename=' . urlencode($sitename)
            . '&token=' . urlencode($token)
            . '&event=' . urlencode($event)
            . '&action=check';

        $response = $curl->get($url);
        if ($response === false || trim((string)$response) === '') {
            return;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || empty($decoded['status'])) {
            return;
        }

        // If the server indicates it is valid again (e.g. extended), refresh local cache.
        if ((string)$decoded['status'] === 'valid') {
            $rawvaliduntil = $decoded['valid_until'] ?? ($decoded['validuntil'] ?? ($decoded['validUntil'] ?? ''));
            $rawvaliduntil = trim((string)$rawvaliduntil);

            $ts = 0;
            if ($rawvaliduntil !== '') {
                if (ctype_digit($rawvaliduntil)) {
                    $ts = (int)$rawvaliduntil;
                } else {
                    $parsed = strtotime($rawvaliduntil);
                    if ($parsed !== false) {
                        $ts = (int)$parsed;
                    }
                }
            }
            if ($ts > time()) {
                set_config('vu', (string)$ts, 'local_invoice');
            }
        }
    }
}
