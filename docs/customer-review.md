# Customer submission review

Customer submissions stay in the review queue until an administrator chooses **Create customer and resolve**, **Update customer and resolve**, or **Dismiss without changes** with a reason. Resolving a legacy form no longer silently closes a submission.

Possible matches include inactive customers and use normalized full name, email, and phone. Matching never applies changes automatically. Administrators select the destination customer, then review it. Existing contact/billing fields change only when checked; new service addresses are added, and existing properties are preserved. New customers default to active, use the submitted mailing address for billing (or the primary service address when selected), and use US as the country. Full names are preserved without attempting to split them.

Successful saves retain `applied_client_id`, `applied_at`, and `applied_by` on the submission and preserve the original details in a customer note. Repeated saves or reopening an applied submission cannot create duplicate customers, properties, or notes. Previously resolved submissions without a recorded link remain reviewable; do not bulk-import them, because older submissions may have been handled manually.

## Installation

Back up the database and deploy in maintenance mode. Run:

```
php index.php client_updates cli upgrade_review
```

The repeatable migration adds the linkage columns and converts only `ip_clients`, `ip_client_notes`, `ip_user_clients`, `ip_client_update_requests`, and `ip_service_properties` to InnoDB if needed. Saving is disabled until transaction support and the linkage columns are present. Code rollback can leave these additive schema changes in place. No customer messages are sent by the review operation.

## Tests

Use only the isolated testing fixture, with the customer submission and service-property schemas installed and upgraded:

```
PROPERTY_INTEGRATION=1 php tests/run-integration.php
```

`tests/browser/client-review.cjs` exercises the actual admin forms against the isolated app on port 18888; it takes fixture IDs from `/private/tmp/ip-client-review-artifacts/ids.json` and the fixture login file from `PROPERTY_TEST_LOGIN_FILE`. `tests/review-concurrency.php` provides seed/apply/verify modes for parallel CLI requests against the testing database.
