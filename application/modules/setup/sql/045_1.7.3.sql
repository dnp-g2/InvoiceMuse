-- InvoiceMuse rebrand: the bundled templates were renamed from InvoicePlane* to InvoiceMuse*.
-- Point saved template settings and email template PDF choices at the new file names.
-- Custom template names are left untouched. On a fresh install this matches nothing.

UPDATE `ip_settings`
   SET `setting_value` = CASE `setting_value`
           WHEN 'InvoicePlane' THEN 'InvoiceMuse'
           WHEN 'InvoicePlane - paid' THEN 'InvoiceMuse - paid'
           WHEN 'InvoicePlane - overdue' THEN 'InvoiceMuse - overdue'
           WHEN 'InvoicePlane_Web' THEN 'InvoiceMuse_Web'
       END
 WHERE `setting_key` IN ('pdf_invoice_template', 'pdf_invoice_template_paid', 'pdf_invoice_template_overdue',
                         'pdf_quote_template', 'public_invoice_template', 'public_quote_template')
   AND `setting_value` IN ('InvoicePlane', 'InvoicePlane - paid', 'InvoicePlane - overdue', 'InvoicePlane_Web');

UPDATE `ip_email_templates`
   SET `email_template_pdf_template` = CASE `email_template_pdf_template`
           WHEN 'InvoicePlane' THEN 'InvoiceMuse'
           WHEN 'InvoicePlane - paid' THEN 'InvoiceMuse - paid'
           WHEN 'InvoicePlane - overdue' THEN 'InvoiceMuse - overdue'
       END
 WHERE `email_template_pdf_template` IN ('InvoicePlane', 'InvoicePlane - paid', 'InvoicePlane - overdue');
