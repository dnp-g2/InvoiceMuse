# Commit Blockers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Clear the three items that block committing the local Slate template and quick-create prerequisite work: a failing test, a Pint style failure, and untracked scratch files that must stay out of the repository.

**Architecture:** Each blocker is an isolated one-file change. The test fix aligns an assertion with the rendered HTML (CSS uppercases the heading on screen; the markup holds `Quantity`). The style fix reformats one alignment line. The ignore rule keeps local artifacts (`.ua/`, `output/`, `.DS_Store`) out of `git status`.

**Tech Stack:** PHP 8.2 in Docker (`php:8.2-cli`, repo mounted at `/app`), PHPUnit, Laravel Pint, git.

**Spec:** The review summary from this session (chat), section "Blockers". There is no separate spec file.

## Global Constraints

- No PHP on the host. Run every PHP tool inside `docker run --rm -v "$PWD":/app -w /app php:8.2-cli sh -c '...'`.
- Run Pint only on the named file. A repo-wide `vendor/bin/pint` would reformat about 25 unrelated files.
- Do not commit. The user commits themselves. The commit steps below are written as commands for the user to run and are marked as such.
- Never stage with `git add -A` or `git add .`; stage files by name.
- Comments and commit messages follow the house writing rules: no em-dashes, no negative-contrast phrasing.

## Review Focus

- A future language pack that translates `slate_quantity` in uppercase would still pass the test, since the assertion only checks the English string. Acceptable; the test runs against the English fixture.
- `.gitignore` uses a root-anchored `/output/` so a future `application/output/` directory would not be hidden by accident. Task 3 pins this with `git check-ignore`.
- `.DS_Store` appears at the repo root and inside `output/`. An unanchored `.DS_Store` rule covers both. Task 3 verifies both paths.
- The Pint fix only changes whitespace. Task 2 confirms `git diff --stat` shows one line changed in one file.
- The failing test asserts on both PDF and web renders in a loop. Task 1 confirms the whole `SlateTemplateTest` class passes, so the change fixes both branches.

---

### Task 1: Fix the `QUANTITY` assertion in SlateTemplateTest

**Files:**
- Modify: `tests/SlateTemplateTest.php:192`

**Interfaces:**
- Consumes: `trans('slate_quantity')` from `application/language/english/ip_lang.php`, value `Quantity`.
- Produces: nothing used by later tasks.

- [ ] **Step 1: Run the failing test to confirm the current failure**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app php:8.2-cli sh -c 'vendor/bin/phpunit --filter it_displays_paid_reference_details_in_pdf_and_web_without_duplicate_locations'
```
Expected: FAIL with `Failed asserting that '...' contains "QUANTITY"` at `tests/SlateTemplateTest.php:192`.

- [ ] **Step 2: Change the assertion to the rendered string**

In `tests/SlateTemplateTest.php` line 192, replace:
```php
            self::assertStringContainsString('QUANTITY', $html);
```
with:
```php
            self::assertStringContainsString('Quantity', $html);
```

- [ ] **Step 3: Run the whole Slate test class**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app php:8.2-cli sh -c 'vendor/bin/phpunit --filter SlateTemplateTest'
```
Expected: `OK` with no failures.

- [ ] **Step 4: Run the full suite**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app php:8.2-cli sh -c 'vendor/bin/phpunit'
```
Expected: `Tests: 89, Assertions: 228` (or higher), `Failures: 0`. Skipped tests are integration tests that need a database and are expected.

- [ ] **Step 5: Commit (user runs this)**

```bash
git add tests/SlateTemplateTest.php
git commit -m "Assert on rendered Quantity heading in Slate test"
```

---

### Task 2: Fix Pint style in the Slate body view

**Files:**
- Modify: `application/views/slate/body.php:49`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing used by later tasks.

- [ ] **Step 1: Confirm the style failure**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app php:8.2-cli sh -c 'vendor/bin/pint --test application/views/slate/body.php'
```
Expected: `FAIL ... 1 file, 1 style issue` naming `application/views/slate/body.php`.

- [ ] **Step 2: Apply Pint to that one file**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app php:8.2-cli sh -c 'vendor/bin/pint application/views/slate/body.php'
```
Expected: `PASS ... 1 file, 1 style issue fixed`. The only change is the alignment of `=` on line 49 so it lines up with the destructuring assignment on line 50:

```php
    $slate_shading                            = $slate_row_index++ % 2 === 0 ? 'slate-shaded' : '';
    [$service_description, $service_location] = slate_service_details($item, $property_render);
```

- [ ] **Step 3: Verify Pint passes and the diff is whitespace only**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app php:8.2-cli sh -c 'vendor/bin/pint --test application/views/slate/body.php && php -l application/views/slate/body.php'
git diff --stat application/views/slate/body.php
```
Expected: `PASS`, `No syntax errors detected`. The file is untracked, so `git diff --stat` prints nothing; inspect the line visually with `sed -n 47,51p application/views/slate/body.php`.

- [ ] **Step 4: Re-run the Slate tests**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app php:8.2-cli sh -c 'vendor/bin/phpunit --filter SlateTemplateTest'
```
Expected: `OK`.

- [ ] **Step 5: Commit (user runs this, together with the rest of the Slate files)**

```bash
git add application/views/slate application/helpers/slate_template_helper.php \
  application/views/invoice_templates application/views/quote_templates \
  assets/core/css/slate.css assets/core/css/slate-web.css \
  application/modules/invoices/models/Mdl_templates.php \
  application/language/english/ip_lang.php .github/docs/SLATE-TEMPLATES.md \
  tests/SlateTemplateTest.php tests/fixtures/slate_render.php tests/render-slate.php tests/browser/slate.cjs
git commit -m "Add Slate invoice and estimate templates"
```

---

### Task 3: Ignore local scratch artifacts

**Files:**
- Modify: `.gitignore` (append at the end, after the `/dist/` block)

**Interfaces:**
- Consumes: nothing.
- Produces: nothing used by later tasks.

- [ ] **Step 1: Confirm the artifacts show as untracked**

Run:
```bash
git status --porcelain --untracked-files=all | grep -E '^\?\? (\.DS_Store|output/|\.ua/)' | wc -l
```
Expected: a number above 70 (the `.ua/` tree alone holds dozens of JSON files).

- [ ] **Step 2: Append the ignore rules**

Append to `.gitignore`:
```gitignore

# macOS metadata
.DS_Store

# Local preview output from tests/render-slate.php and similar scripts
/output/

# understand-anything knowledge graph cache
/.ua/
```

- [ ] **Step 3: Verify each path is ignored and nothing else changed**

Run:
```bash
git check-ignore -v .DS_Store output/.DS_Store output/pdf/Slate-invoice-preview.pdf .ua/config.json
git status --porcelain --untracked-files=all | grep -E '^\?\? (\.DS_Store|output/|\.ua/)' | wc -l
git status --short
```
Expected: `git check-ignore` prints a matching `.gitignore` line for all four paths; the count is `0`; `git status --short` lists only the source files, tests, docs and `.gitignore`.

- [ ] **Step 4: Commit (user runs this)**

```bash
git add .gitignore
git commit -m "Ignore local preview output and analysis caches"
```

---

## After the blockers

Once these three tasks are done, `git status` shows only intended files, `phpunit` is green, and Pint passes on every new file. The remaining review findings (the `Location:` regex, the short service address, the Docker upgrade step, the footer height, unused language keys, the `logo` translation key, and the duplicated guard checks in `Mdl_client_quick_create.php`) are separate work and can be planned after the commit. The quick-create changes (`resources/docker/entrypoint.sh`, `tests/DockerFeatureSetupTest.php`, `application/modules/clients/models/Mdl_client_quick_create.php`, `docs/quick-customer.md`) belong in their own commit.
