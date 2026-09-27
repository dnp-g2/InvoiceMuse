# Slate templates

Slate is an additional invoice and estimate design with a charcoal header, shaded service rows, payment details, and a company footer. PDF output uses US Letter and repeats the header and numbered footer on each page. Long item descriptions continue at a readable font size.

## Select the design

In **Settings → Invoices**, choose **Slate** for the PDF template. Select **Slate** in the paid and overdue PDF fields as well if you want the same design for those states. Choose **Slate_Web** for the public template.

In **Settings → Quotes**, choose **Slate** for the PDF template and **Slate_Web** for the public template. The new customer documents say **Estimate**; the administrative Quotes workflow is unchanged.

Existing selections are preserved when these files are installed. A PDF downloaded from a Slate public page explicitly uses Slate. Other PDF generation follows the existing settings and email-template selections.

## Document content

- Business name, address, contact information, and logo come from the existing user/company and logo settings.
- Customer details, service properties, taxes, discounts, and totals come from the existing document data. Invoice payment history includes dates, amounts, and payment method names; internal payment notes are excluded.
- Invoice terms and estimate notes appear below the totals. Configured PDF footer text is sanitized and included after the notes; the company footer repeats on every PDF page.
- The template-specific English labels are `slate_*` entries in `application/language/english/ip_lang.php`. Other languages use the existing English fallback, and can supply their own translations.
- Public pages retain payment links, attachments, and authorized estimate approval/rejection forms. Service-property publication restrictions remain enforced.

## Developer verification

Run `vendor/bin/phpunit --filter 'SlateTemplateTest|TemplateRenameTest|BrandingTest'` with PHP 8.2+ and the Composer development dependencies.

Run `php tests/render-slate.php /tmp/slate-previews` to generate isolated example PDFs and public HTML through the real templates and mPDF helper, without connecting to the database. The fixtures cover payment states, taxes/discounts, property groups, missing logos, many items, long terms, and long descriptions. The business and customer data are fictional.

For visual browser checks, serve the generated HTML at `/previews/` alongside the application's `/assets/` directory. Set `SLATE_PREVIEW_URL` to that URL, install Playwright in your test environment, and run `node tests/browser/slate.cjs`. It checks desktop and mobile layouts, asset loading, download targets, payment controls, and estimate form structure. These fixture checks do not submit real payments or send approval emails.

Optional: `SLATE_ASSET_ROOT` may point to a temporary site directory ending in `/`, containing `assets/core/css/slate.css`, `assets/core/css/slate-web.css`, and `uploads/slate-fixture.png`, to exercise logo rendering without writing to the application's uploads folder.
