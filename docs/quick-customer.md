# Create a customer while starting an invoice

The Create Invoice dialog offers **Create new customer** beside its customer field and in the search dropdown. The short form requires a full name; email, phone, service address and separate billing address are optional. Started addresses must be completed. Country defaults to US. Saving creates an active customer and optional service property, selects that customer, and returns to the invoice fields without resetting them. No invoice or email is created by this step. Canceling invoice creation afterward leaves the explicitly saved customer in the directory.

The new `clients/ajax/quick_create` POST route is admin-only and CSRF protected. It returns either the saved customer ID/display name, field validation errors, or possible duplicates with a review fingerprint. Matches include inactive customers. Only active matches can be selected for an invoice; inactive records are never reactivated automatically. Creating a separate account requires explicit confirmation of the current matches. Matching rules are shared with customer-submission review through `Customer_match`.

The customer, service property, and all-customer user assignments commit in one transaction. Creation uses the existing InnoDB tables and the same customer-review advisory lock. Per-dialog session request IDs return the original result on retries. If that session receipt is unavailable after an interrupted request, a repeated submission must pass duplicate review again; an old duplicate confirmation cannot bypass newly created matches. Session receipts are bounded to the most recent 40 requests. No schema migration is needed.

Invoice creation retains its existing endpoint and draft behavior. Quote and other customer selectors do not expose the new action.

## Verification

Run `PROPERTY_INTEGRATION=1 php tests/run-integration.php` in the isolated app. `tests/browser/quick-customer.cjs` exercises all three invoice entry points, duplicate selection, input preservation, keyboard submission, mobile layout, CSRF rejection, and retry after a dropped successful response. It uses synthetic customers and invoices only against localhost port 18888.
