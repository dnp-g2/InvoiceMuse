<?php

if ( ! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
 * InvoicePlane
 *
 * @author		InvoicePlane Developers & Contributors
 * @copyright	Copyright (c) 2012 - 2018 InvoicePlane.com
 * @license		https://invoiceplane.com/license.txt
 * @link		https://invoiceplane.com
 */

#[AllowDynamicProperties]
class Mdl_Templates extends CI_Model
{
    /**
     * Static whitelist of allowed invoice templates.
     *
     * Security: This is a hardcoded list to prevent RCE vulnerability.
     * Templates are NEVER loaded dynamically from the filesystem.
     * Only templates in this list can be used, even if other PHP files exist in the templates directory.
     *
     * To add a new template:
     * 1. Add the template file to the appropriate directory
     * 2. Add the template name (without .php extension) to this array
     * 3. Deploy both changes together
     *
     * @var array
     */
    private const ALLOWED_INVOICE_TEMPLATES = [
        'pdf' => [
            'InvoiceMuse',
            'InvoiceMuse - paid',
            'InvoiceMuse - overdue',
        ],
        'public' => [
            'InvoiceMuse_Web',
        ],
    ];

    /**
     * Static whitelist of allowed quote templates.
     *
     * Security: This is a hardcoded list to prevent RCE vulnerability.
     * Templates are NEVER loaded dynamically from the filesystem.
     *
     * @var array
     */
    private const ALLOWED_QUOTE_TEMPLATES = [
        'pdf' => [
            'InvoiceMuse',
        ],
        'public' => [
            'InvoiceMuse_Web',
        ],
    ];

    /**
     * Pre-rebrand names of the bundled templates, mapped to their current names.
     *
     * @var array
     */
    private const LEGACY_TEMPLATE_RENAMES = [
        'InvoicePlane'           => 'InvoiceMuse',
        'InvoicePlane - paid'    => 'InvoiceMuse - paid',
        'InvoicePlane - overdue' => 'InvoiceMuse - overdue',
        'InvoicePlane_Web'       => 'InvoiceMuse_Web',
    ];

    /**
     * Current name for a saved pre-rebrand bundled template name, or null to keep the name.
     *
     * A legacy name is kept when it identifies a custom template: listed in the matching
     * CUSTOM_*_TEMPLATES allowlist, or present in CUSTOM_TEMPLATES_FOLDER, whose copy takes
     * precedence over a bundled template of the same name.
     *
     * @param string $template_name Saved template name
     * @param string $subpath       e.g. 'invoice_templates/pdf'
     */
    public function get_legacy_template_rename(string $template_name, string $subpath): ?string
    {
        $renamed = self::LEGACY_TEMPLATE_RENAMES[$template_name] ?? null;
        if ($renamed === null) {
            return null;
        }

        if (in_array($template_name, $this->_merge_custom($subpath, []), true)) {
            return null;
        }

        $this->load->helper('template');

        return custom_template_file($subpath . '/' . $template_name) === null ? $renamed : null;
    }

    /**
     * Get the list of allowed invoice templates.
     *
     * Security: Built-in templates are returned from the static whitelist only — the
     * application's own template directories are NEVER scanned to prevent RCE.
     * The returned list is the built-in whitelist plus any custom template names explicitly
     * enumerated in the CUSTOM_INVOICE_TEMPLATES_PDF / CUSTOM_INVOICE_TEMPLATES_PUBLIC
     * allowlist constants (see _merge_custom()). CUSTOM_TEMPLATES_FOLDER is NOT scanned to
     * build this list; it only provides the file's location on disk at render time.
     *
     * @param string $type Template type ('pdf' or 'public')
     *
     * @return array List of allowed template names (without .php extension)
     */
    public function get_invoice_templates($type = 'pdf')
    {
        if ($type === 'pdf') {
            $built_in = self::ALLOWED_INVOICE_TEMPLATES['pdf'];
        } elseif ($type === 'public') {
            $built_in = self::ALLOWED_INVOICE_TEMPLATES['public'];
        } else {
            return [];
        }

        return $this->_merge_custom('invoice_templates/' . $type, $built_in);
    }

    /**
     * Get the list of allowed quote templates.
     *
     * Security: Built-in templates are returned from the static whitelist only — the
     * application's own template directories are NEVER scanned to prevent RCE.
     * The returned list is the built-in whitelist plus any custom template names explicitly
     * enumerated in the CUSTOM_QUOTE_TEMPLATES_PDF / CUSTOM_QUOTE_TEMPLATES_PUBLIC
     * allowlist constants (see _merge_custom()). CUSTOM_TEMPLATES_FOLDER is NOT scanned to
     * build this list; it only provides the file's location on disk at render time.
     *
     * @param string $type Template type ('pdf' or 'public')
     *
     * @return array List of allowed template names (without .php extension)
     */
    public function get_quote_templates($type = 'pdf')
    {
        if ($type === 'pdf') {
            $built_in = self::ALLOWED_QUOTE_TEMPLATES['pdf'];
        } elseif ($type === 'public') {
            $built_in = self::ALLOWED_QUOTE_TEMPLATES['public'];
        } else {
            return [];
        }

        return $this->_merge_custom('quote_templates/' . $type, $built_in);
    }

    /**
     * Check if template directories have insecure permissions.
     *
     * Security: Warns administrators if template directories are writable by the web server.
     * This is a defense-in-depth measure - the static whitelist already prevents exploitation,
     * but writable template directories are still a security misconfiguration that should be fixed.
     *
     * @return array Array of warnings (empty if no issues found)
     */
    public function check_template_directory_permissions(): array
    {
        $warnings = [];

        $directories = [
            APPPATH . 'views/invoice_templates/pdf',
            APPPATH . 'views/invoice_templates/public',
            APPPATH . 'views/quote_templates/pdf',
            APPPATH . 'views/quote_templates/public',
        ];

        foreach ($directories as $dir) {
            // Check if directory is writable
            if (is_writable($dir)) {
                $warnings[] = [
                    'directory'      => $dir,
                    'message'        => 'Template directory is writable by web server. This is a security risk.',
                    'recommendation' => 'Set directory permissions to read-only (e.g., chmod 555)',
                ];
            }
        }

        return $warnings;
    }

    /**
     * Find selected template settings that are not available in the current allowlists.
     *
     * This helps administrators upgrading from versions that discovered template files
     * automatically. If a saved template name is not built in and not listed in
     * ipconfig.php, it will not appear in the UI until it is added to the matching
     * CUSTOM_*_TEMPLATES setting. Email template PDF choices are checked too, because
     * recurring invoice emails use them as saved. Pre-rebrand bundled names are skipped
     * when migration 045 renames them (see get_legacy_template_rename()).
     *
     * @return array<string, array<int, string>>
     */
    public function get_missing_allowlisted_template_settings(): array
    {
        $checks = [
            'CUSTOM_INVOICE_TEMPLATES_PDF' => [
                'subpath'  => 'invoice_templates/pdf',
                'allowed'  => $this->get_invoice_templates('pdf'),
                'settings' => [
                    'pdf_invoice_template',
                    'pdf_invoice_template_paid',
                    'pdf_invoice_template_overdue',
                ],
            ],
            'CUSTOM_INVOICE_TEMPLATES_PUBLIC' => [
                'subpath'  => 'invoice_templates/public',
                'allowed'  => $this->get_invoice_templates('public'),
                'settings' => [
                    'public_invoice_template',
                ],
            ],
            'CUSTOM_QUOTE_TEMPLATES_PDF' => [
                'subpath'  => 'quote_templates/pdf',
                'allowed'  => $this->get_quote_templates('pdf'),
                'settings' => [
                    'pdf_quote_template',
                ],
            ],
            'CUSTOM_QUOTE_TEMPLATES_PUBLIC' => [
                'subpath'  => 'quote_templates/public',
                'allowed'  => $this->get_quote_templates('public'),
                'settings' => [
                    'public_quote_template',
                ],
            ],
        ];

        $selected = [];
        foreach ($checks as $ipconfig_key => $check) {
            foreach ($check['settings'] as $setting_key) {
                $selected[] = [$ipconfig_key, (string) get_setting($setting_key)];
            }
        }

        $this->db->select('email_template_type, email_template_pdf_template');
        foreach ($this->db->get('ip_email_templates')->result() as $email_template) {
            $ipconfig_key = $email_template->email_template_type === 'quote' ? 'CUSTOM_QUOTE_TEMPLATES_PDF' : 'CUSTOM_INVOICE_TEMPLATES_PDF';
            $selected[]   = [$ipconfig_key, (string) $email_template->email_template_pdf_template];
        }

        $missing = [];
        foreach ($selected as [$ipconfig_key, $template_name]) {
            $check = $checks[$ipconfig_key];

            if ($template_name === ''
                || in_array($template_name, $check['allowed'], true)
                || $this->get_legacy_template_rename($template_name, $check['subpath']) !== null) {
                continue;
            }

            $missing[$ipconfig_key][] = $template_name;
        }

        foreach ($missing as $ipconfig_key => $template_names) {
            $missing[$ipconfig_key] = array_values(array_unique($template_names));
        }

        return $missing;
    }

    /**
     * Merge built-in templates with any custom templates explicitly allowlisted in the
     * CUSTOM_INVOICE_TEMPLATES_PDF / CUSTOM_INVOICE_TEMPLATES_PUBLIC /
     * CUSTOM_QUOTE_TEMPLATES_PDF / CUSTOM_QUOTE_TEMPLATES_PUBLIC constants.
     *
     * Security:
     * - The filesystem is NEVER scanned to discover templates (prevents RCE).
     * - Only names present in the explicit config constants are added to the list.
     * - Each name is validated against a strict allowlist regex before use.
     *   Any name that does not match is skipped and logged.
     * - Custom templates are prepended so admins can shadow built-in names;
     *   array_unique() deduplicates the merged list.
     *
     * To expose a custom template, add its name (without .php) to the appropriate
     * constant in ipconfig.php, e.g.:
     *   CUSTOM_INVOICE_TEMPLATES_PDF=MyTemplate,AnotherTemplate
     *
     * @param string $subpath  Relative sub-path key, e.g. 'invoice_templates/pdf'
     * @param array  $built_in Hardcoded whitelist entries from the class constants
     *
     * @return array
     */
    private function _merge_custom(string $subpath, array $built_in): array
    {
        // Map subpath to the corresponding ipconfig constant name
        $const_map = [
            'invoice_templates/pdf'    => 'CUSTOM_INVOICE_TEMPLATES_PDF',
            'invoice_templates/public' => 'CUSTOM_INVOICE_TEMPLATES_PUBLIC',
            'quote_templates/pdf'      => 'CUSTOM_QUOTE_TEMPLATES_PDF',
            'quote_templates/public'   => 'CUSTOM_QUOTE_TEMPLATES_PUBLIC',
        ];

        if ( ! isset($const_map[$subpath])) {
            return $built_in;
        }

        $const_name = $const_map[$subpath];

        // Read the explicit allowlist from ipconfig.php (empty string / undefined = no custom templates)
        $raw = defined($const_name) ? constant($const_name) : '';

        if (empty($raw)) {
            return $built_in;
        }

        $custom_names = [];

        foreach (explode(',', $raw) as $entry) {
            $name = trim($entry);

            if ($name === '') {
                continue;
            }

            // Strict validation: only alphanumeric characters, spaces, hyphens and underscores.
            // Rejects path traversal sequences, null bytes, and any other special characters.
            if (preg_match('/^[a-zA-Z0-9 _-]+$/', $name)) {
                $custom_names[] = $name;
            } else {
                // Sanitize before logging: strip control characters to prevent log injection.
                $safe_name = preg_replace('/[\x00-\x1f\x7f]/', '', mb_substr($name, 0, 64));
                log_message('warning', 'Mdl_Templates: skipping invalid custom template name: ' . $safe_name);
            }
        }

        return array_values(array_unique(array_merge($custom_names, $built_in)));
    }
}
