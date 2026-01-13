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

class local_invoice_admin_setting_prolicense_text extends admin_setting_configtext {
    public function write_setting($data) {
        $licensekey = get_config('local_invoice', 'license_key');
        if ($licensekey === 'free' || !$this->is_license_valid($licensekey)) {
            unset_config($this->name, 'local_invoice');
            return '';
        }
        return parent::write_setting($data);
    }

    protected function is_license_valid($key) {
        global $CFG, $SITE;
        if ($key === 'free' || empty($key)) {
            return false;
        }
        require_once($CFG->libdir.'/filelib.php');
        $curl = new \curl();
        $siteurl = $CFG->wwwroot;
        $sitename = $SITE->fullname;
        $token = 'KKJFRJDFGGKDEERPQZZATTF'; 

        $url = 'https://moodle.elso.co.za/licensing.php'
            . '?key=' . urlencode($key)
            . '&siteurl=' . urlencode($siteurl)
            . '&sitename=' . urlencode($sitename)
            . '&token=' . urlencode($token);

        $response = $curl->get($url);
        $result = json_decode($response);
        return ($result && isset($result->status) && $result->status === 'valid');
    }
}

class local_invoice_admin_setting_prolicense_text_free extends admin_setting_configtext {
    public function write_setting($data) {
        return parent::write_setting($data);
    }
}

class local_invoice_admin_setting_prolicense_file extends admin_setting_configstoredfile {
    public function write_setting($data) {
        $licensekey = get_config('local_invoice', 'license_key');
        if ($licensekey === 'free' || !$this->is_license_valid($licensekey)) {
            $fs = get_file_storage();
            $context = context_system::instance();
            $fs->delete_area_files($context->id, 'local_invoice', 'companylogo');
            unset_config($this->name, 'local_invoice');
            return '';
        }
        return parent::write_setting($data);
    }

    protected function is_license_valid($key) {
        global $CFG, $SITE;
        if ($key === 'free' || empty($key)) {
            return false;
        }
        require_once($CFG->libdir.'/filelib.php');
        $curl = new \curl();
        $siteurl = $CFG->wwwroot;
        $sitename = $SITE->fullname;
        $token = 'KKJFRJDFGGKDEERPQZZATTF';

        $url = 'https://moodle.elso.co.za/licensing.php'
            . '?key=' . urlencode($key)
            . '&siteurl=' . urlencode($siteurl)
            . '&sitename=' . urlencode($sitename)
            . '&token=' . urlencode($token);

        $response = $curl->get($url);
        $result = json_decode($response);
        return ($result && isset($result->status) && $result->status === 'valid');
    }
}

class local_invoice_admin_setting_prolicense_key extends admin_setting_configtext {
    public function write_setting($data) {
        if ($data === 'free') {
            return parent::write_setting($data);
        }
        if ($this->is_license_valid($data)) {
            return parent::write_setting($data);
        }
        return get_string('invalid_license', 'local_invoice');
    }

    protected function is_license_valid($key) {
        global $CFG, $SITE;
        if ($key === 'free' || empty($key)) {
            return false;
        }
        require_once($CFG->libdir.'/filelib.php');
        $curl = new \curl();
        $siteurl = $CFG->wwwroot;
        $sitename = $SITE->fullname;
        $token = 'KKJFRJDFGGKDEERPQZZATTF';

        $url = 'https://moodle.elso.co.za/licensing.php'
            . '?key=' . urlencode($key)
            . '&siteurl=' . urlencode($siteurl)
            . '&sitename=' . urlencode($sitename)
            . '&token=' . urlencode($token);

        $response = $curl->get($url);
        $result = json_decode($response);
        return ($result && isset($result->status) && $result->status === 'valid');
    }
}
