Invoice Generator for PayPal and Payfast Enrolments
===================================================

Overview
--------
This local plugin provides seamless, auditable PDF invoice generation for Moodle users enrolled in courses via either the PayPal or Payfast enrolment methods.

Key features:
-------------
- **Automatic detection of all user enrolments** via PayPal or Payfast methods, including existing enrolments at install time.
- **Professional PDF invoices** for each enrolment, showing:
    - Course name, enrolment cost, VAT breakdown, invoice number (with optional prefix), date, and company details.
    - User-supplied billing details (company name, VAT number, address, city, province, postal code) prompted for and stored per invoice.
- **Invoices are static, audit-proof documents** — each always displays the original price and details, regardless of later course setting changes.
- **PDFs are stored in each user's Private files area** under the "Invoices" folder for easy re-download.
- **"My Invoices" menu item** added to Moodle's custom menu for quick access.
- **No need for custom profile fields** — all invoice data is stored per enrolment.
- **No external Composer dependencies** — the TCPDF library is bundled for immediate use.
- **Supports both PayPal and Payfast** — works out of the box with either payment method enabled.

Pro Features
------------
Unlock Pro features by purchasing a Pro license key:
- Add a **company logo** to your invoices
- Display your **company tax/VAT number** on invoices
- Add a custom **invoice prefix** (e.g., INV-, 2025-, etc.)
- Optional tax/VAT breakdown showing subtotal, tax/VAT, and total when enabled in settings.
- Remove the **"Plugin provided by eLearn Solutions" watermark image** from generated invoices.

Visit shop.elearnsolutions.co.za to purchase a Pro key to unlock these features. The Pro license key is validated remotely and can be changed in the plugin settings at any time.


License Key Logic
-----------------
- By default, the plugin runs in "Free" mode—no license key needed.
- **Free mode invoices include a watermark image** at the bottom of generated invoices.
- To enable Pro features, enter a valid Pro license key (provided on purchase) in the settings page. The key is validated with the remote licensing server.
- **Pro keys are valid for 1 year (365 days) from activation.** After expiry, invoices are generated in Free mode (no logo, no tax/VAT number, no tax breakdown, and the watermark image returns).
- Only the company logo, tax/VAT number, invoice prefix, and tax/VAT breakdown are Pro features; invoice numbering (starting number) remains available in Free mode.

Requirements
------------
- Moodle 4.3 or higher
- The PayPal or Payfast enrolment method enabled on your site
- PHP 8.1 or later
- No Composer installation required (TCPDF is bundled)

Installation
------------

1. **Copy the plugin:**
    - Copy or extract the plugin folder to `/local/invoice/` in your Moodle installation.

2. **No Composer required:**
    - The TCPDF library is included. No extra dependencies are required.

3. **Visit Site administration > Notifications:**
    - Moodle will install the plugin and required database tables.
    - The plugin will automatically add the following Custom Menu item in Moodle for all users: "My invoices > View invoices".
    - Users can access invoices directly via this menu.
    - The plugin will also add a Custom Menu item in Moodle for site admins: "Invoices (admin) > Settings".


4. **Configure Invoice Settings:**
    - Go to *Site administration > Plugins > Local plugins > Invoice Generator*.
    - Enter your company details and upload a company logo (Pro only).
    - Fill in your company’s name, address, city, postal code. Tax/VAT number and invoice prefix require a Pro license key.
    - All values appear on every new invoice.

How it works
------------
- **Automatic detection:**  
  The plugin finds all courses where a user is enrolled via PayPal or Payfast. No manual action is required for existing or new enrolments.

- **First use:**  
  When a user goes to "My Invoices," they are prompted for billing details if needed. These are stored per invoice and reused for all future invoices.

- **Invoice numbers:**  
  Each invoice gets a unique, sequential number. A prefix (e.g. "INV-") can be set in the plugin settings (Pro only).

- **Invoice storage:**  
  PDFs are stored in the user's Private files area, under the "Invoices" directory. If deleted, invoices can be regenerated with the same details.

- **Audit-proof:**  
  Invoice data (price, course, billing info) is stored at creation and is never changed, even if the course or enrolment is later updated.

- **No custom profile fields needed:**  
  All user billing info is stored per-invoice. There is no need to add custom user profile fields.

License Key and Upgrading
-------------------------
- To unlock Pro features, purchase a license key from shop.elearnsolutions.co.za.
- The Pro key is validated remotely when entered or changed.
- All invoices and user data remain in the database if you upgrade or migrate to Pro.

Support
-------
- For documentation, updates, and support, please contact support@elso.co.za or visit the project repository - https://github.com/lerouxh/moodle-local_invoice.git
- eLearn Solutions offers paid support and customisation for this plugin and other Moodle services - https://elearnsolutions.co.za

License
-------
This plugin is licensed under the GNU GPL v3 or later.

Privacy
-------
To validate a Pro key, the plugin contacts the eLearn Solutions licensing server (https://moodle.elso.co.za) and sends the license key and site identifier. 
No user invoice data is transmitted.

Credits
-------
This plugin includes a bundled copy of the TCPDF library (https://tcpdf.org/), which is open-source under the LGPL v3 license.
