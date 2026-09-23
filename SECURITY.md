# Security Policy

InvoiceMuse handles sensitive financial data, so we take security reports seriously and
appreciate the work of the researchers who help keep our users safe.

## Supported Versions

Security fixes are provided for the latest stable release. Older releases receive fixes only at
the maintainers' discretion. Version numbers are InvoiceMuse release versions; for InvoicePlane
releases, see [InvoicePlane's security policy](https://github.com/InvoicePlane/InvoicePlane/security/policy).

| Version | Supported          |
|---------|--------------------|
| 1.0.x   | :white_check_mark: |

## Reporting a Vulnerability

**Please report vulnerabilities privately.** Use the private channel below and keep security
problems out of public issues, pull requests, and forum posts.

Report through a **private GitHub Security Advisory**:

1. Go to the [**Security Advisories**](https://github.com/dnp-g2/InvoiceMuse/security/advisories) page.
2. Click **“Report a vulnerability”** ([direct link](https://github.com/dnp-g2/InvoiceMuse/security/advisories/new)).
3. Fill in the details (see below). Only you and the maintainers can see the draft advisory.

Reporting through an advisory lets us collaborate on the fix privately, request a CVE, and credit
you accurately when the advisory is published.

If the vulnerability is in upstream InvoicePlane code, please also report it to the InvoicePlane
project through [its security policy](https://github.com/InvoicePlane/InvoicePlane/security/policy).

### What to include

To help us triage quickly, please provide:

- A description of the vulnerability and its impact.
- The affected version(s) and component (controller, helper, view, endpoint, …).
- Step-by-step reproduction instructions or a proof of concept.
- Any suggested remediation, if you have one.

## Disclosure Process

1. **Acknowledgement** — we aim to confirm receipt within a few days.
2. **Assessment** — we validate the report, determine severity (CVSS), and assign a CWE.
3. **Fix** — we develop and test a fix on a private branch.
4. **Release & credit** — we publish a release, then make the advisory (and any CVE) public,
   crediting the reporter unless anonymity is requested.

Please give us a reasonable opportunity to release a fix before any public disclosure.

## Published Advisories

Formal advisories and CVE request material inherited from InvoicePlane releases live in
[`.github/security/`](.github/security/), and every fixed vulnerability is tracked with its GHSA
link, CWE, CVSS score, and reporter in the [CHANGELOG](.github/CHANGELOG.md).
