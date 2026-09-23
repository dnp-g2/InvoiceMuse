# Customer service properties

Customers retain their billing details. Properties appear as numbered address cards. Use **Edit** to open one form, **Save changes** to save, or **Cancel** to discard edits. **Add property** opens a blank form. **Archive** asks for confirmation and moves the card into **Archived properties**; **Restore** makes it active again. Property numbers stay the same when archived or restored. Open a customer and choose **Service properties** to add, edit, archive or restore service addresses. Create one customer for a person with several service properties.

On a new invoice or quote, select the service property above each line, or use **Assign to unassigned lines**. A sole active property is selected automatically. Mixed-property documents print grouped service addresses and property line totals. Overall discounts, taxes, payments and balances use InvoicePlane's existing calculations. Property line totals are not a separate invoice balance.

Drafts may be incomplete. **Preview draft** prints an explicit incomplete warning; normal export, sending and public viewing require complete assignments. Normal export locks the saved addresses even when the invoice is still Draft. Issued addresses remain unchanged after customer/property edits. Use **Refresh saved billing and property addresses** only on an unissued draft, or copy an issued document to a new draft. Archived properties remain in historical documents; new work requires active properties.

Copies, credits and quote-to-invoice conversion retain address snapshots for the same customer. Changing a draft customer clears property assignments. Recurring invoices require valid active properties and do not advance the schedule when validation fails. Existing documents predating installation retain their legacy behavior. Copy a legacy invoice to a new draft and assign properties before making it recurring.

This release supports the configured InvoicePlane PDF (including paid/overdue wrappers), InvoicePlane_Web public templates, and guest views. Other custom templates and electronic-invoice formats need explicit integration before use with service properties.

## Installation and recovery

1. Back up application code and the full database with table locks (existing tables use MyISAM). Restrict backup access and keep backups outside the web root.
2. Deploy the maintenance-capable `index.php`, then create `application/config/.service-properties-maintenance` to pause HTTP requests. CLI remains available. Deploy all production files together.
3. Run `php index.php service_properties cli install`. It can be rerun; the original document cutoffs remain unchanged. It adds three metadata tables and nullable property/snapshot columns to invoice and quote items.
4. For a separately reviewed customer manifest outside the web root, set `PROPERTY_IMPORT_FILE`, run `php index.php service_properties cli import dry-run`, review, then use `apply`. Imports validate every source/customer first, are resumable, and resolve an approved request only after its customer, properties and source note are saved. No email is sent.
5. Verify existing customer and financial records against the backup, new properties and pending submissions, PHP lint, routes and login. Remove the maintenance marker to reopen.

Before reopening, rollback can restore the backed-up code and database together. After new property documents have been created, preserve those records and review recovery before restoring an older database. Saves use advisory locks and a persistent in-progress marker because legacy MyISAM writes cannot be rolled back; incomplete saves are blocked from publication until reviewed and saved again.

## Isolated verification

Use the Dockerfile in `tools/Dockerfile.properties-test` with a disposable MariaDB database and an isolated `ipconfig.php`. Never point the fixture at production. Bind the web server only to localhost:18888. Install Composer dev dependencies. Symlink `application/controllers/Property_test.php` to `../../tests/integration/Property_test.php` in that disposable checkout only.

Run with `CI_ENV=testing`:

```
php index.php property_test install
php index.php service_properties cli install
php index.php clients cli install_note_archive
PROPERTY_INTEGRATION=1 php tests/run-integration.php
```

The fixture writes a random password to `/tmp/ip-test-login` without displaying it. Browser checks require Playwright with Chrome, compiled application assets, and the two synthetic fixture customers. Run `tests/browser/properties.cjs` with `PROPERTY_TEST_LOGIN_FILE` pointing to that password file. `PROPERTY_BROWSER_OUTPUT` selects the artifact directory. Fixture invoice/quote groups must be IDs 3/4 (adjust test-only setup if IDs differ). For styled PDF rendering at localhost:18888, Apache inside the container also needs to listen on 18888.

Coverage includes ownership, duplicate properties, archival/history, draft refresh, stale saves/deletions, partial-save recovery, customer changes, native copy/credit totals, tax/discount/partial payments, legacy behavior, resumable imports, browser editing, quote conversion, incomplete-export blocking, validation retries, PDF generation and unauthenticated access. Visually inspect generated invoice, quote and incomplete-preview PDFs. Do not deploy fixtures, test credentials, dev dependencies or the test controller symlink.

## Customer notes

Every customer uses the shared notes view. Archive hides a note under **Archived notes**; Restore returns it to the active list. Text and original dates are retained. The former delete endpoint returns HTTP 410. Deploy the repeatable `php index.php clients cli install_note_archive` migration before reopening the application. Existing notes default to active.

## Customer overview

All customers share the card-based Overview: contact and billing details, inline service properties, unchanged account totals, notes, and collapsed additional details. Property changes refresh only the property section and retain unfinished notes. The standalone Service Properties page remains available. Invoices, Quotes and Payments retain their existing routes and pagination. Existing customer deletion is under More actions with its confirmation. Long notes can be expanded; their full stored text is unchanged.

Verify the overview with `tests/browser/customer-page.cjs` using the same isolated login environment variables as the other browser checks.

## Invoice workspace

Standard invoices now have a readable Summary and an explicit Edit mode. Drafts open in Edit; issued invoices open in Summary. Saving returns to Summary. Charges are grouped by service property and saved address, with the balance, due date and next action visible at the top. Optional charge details and invoice settings remain available in expandable sections. SUMEX, quotes, PDFs and customer-facing templates keep their existing layouts.

**Save changes** applies charge edits, ordering and removals together. **Cancel** discards pending changes, including removals. Amounts shown while editing are labeled as last saved; the existing server calculation updates them after saving. Payments, attachments, customer changes and invoice taxes are separate actions on Summary. Address locks and read-only rules still apply; use **More actions → Copy to new draft** to change issued property assignments.

The existing invoice URL accepts `mode=summary` or `mode=edit`. The save endpoint accepts an optional JSON `deleted_item_ids` array; it validates the complete item set, ownership, permissions and property revision before applying removals. Omitting an existing line without explicitly removing it is still rejected. No database migration is needed. MyISAM partial-save recovery remains in place; an unconfirmed save requires reloading and reviewing the saved invoice before saving again.

For isolated browser verification, run `php index.php property_test invoice_workspace_fixture` with `CI_ENV=testing` to create synthetic product/task entries and set the test theme/read-only behavior. Then run `tests/browser/invoice-workspace.cjs` with the same login/output environment variables as the other browser tests. This covers summary/edit, staged removals and Cancel, validation, read-only metadata edits, catalog/task dialogs, ordering, empty invoices, payment dialogs, PDF generation and responsive widths. `tests/browser/properties.cjs` additionally covers quote conversion, customer-link copying/public rendering and incomplete draft restrictions.
