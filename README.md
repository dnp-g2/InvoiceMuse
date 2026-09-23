<img align="right" alt="InvoiceMuse logo" src="/assets/core/img/favicon.svg" width="64" height="64">

# _InvoiceMuse_

_A libre self-hosted web application designed to help you manage invoices, clients, and payments efficiently._

## Acknowledgment

InvoiceMuse is an independent project based on [InvoicePlane](https://www.invoiceplane.com/).
We sincerely thank the InvoicePlane developers and contributors for their work and for making
the project available as open-source software. InvoiceMuse is not affiliated with or endorsed by
the InvoicePlane project. Upstream copyright and license notices are preserved in
[`LICENSE.txt`](LICENSE.txt).

---

## Release Notes

Project changes are documented in [CHANGELOG.md](.github/CHANGELOG.md). Historical security
advisories inherited from upstream remain in [`.github/security/`](.github/security/), and
step-by-step upgrade instructions are in [UPGRADE.md](.github/docs/UPGRADE.md).

> **Security releases matter here.** Check the changelog before upgrading.

---

## Key Features

- **Invoice & Quote Management:** Effortlessly create, send, and manage professional invoices and quotes.
- **Client Management:** Maintain detailed client records, including contact information and transaction history.
- **Product Management:** Maintain products to add to your Invoices.
- **Project & Tasks Management:** Maintain tasks to add to your Invoices.
- **Payment Tracking:** Monitor payments, set up reminders, and integrate with multiple payment gateways.
- **Customization:** Tailor templates, themes, and settings to match your brand preferences.
- **Reporting:** Generate insightful reports to track your financial performance.

---

## Getting Started

### Quick Start with Docker (Recommended for Development)

```bash
# Clone the repository
git clone https://github.com/dnp-g2/InvoiceMuse.git
cd InvoiceMuse

# Build and start the app + database — dependencies, assets, and
# configuration are all handled inside the container.
docker compose up -d --build

# Access the application
# InvoiceMuse: http://localhost:4895
```

See [Installation instructions](.github/docs/INSTALLATION.md)

> `compose.yml` is for local development/testing only — it ships a fixed `ENCRYPTION_KEY` and
> database password (uploads, storage, and the database do persist across restarts via named
> volumes). For a real deployment, see
> [Container (Docker) Deployment Instructions](.github/docs/CONTAINER_DEPLOYMENT.md) for the environment variables
> to set instead.

### Production Installation

1. Download the latest release from [InvoiceMuse Releases](https://github.com/dnp-g2/InvoiceMuse/releases).
2. Extract and upload the files to your web server.
3. Copy `ipconfig.php.example` to `ipconfig.php` and set your base URL and database credentials.
4. Navigate to `http://your-domain.com/index.php/setup` to run the installer.

For a detailed installation guide, see [Installation instructions](.github/docs/INSTALLATION.md).

---

## Removing `index.php` from URLs

To remove `index.php` from your URLs:

1. Enable `mod_rewrite` on your web server.
2. Set `REMOVE_INDEXPHP=true` in `ipconfig.php`.
3. Rename the `htaccess` file in the root directory to `.htaccess`.

> **Note:** If you experience issues after making these changes, revert to the default settings by undoing the steps above.

---

## Custom Invoice & Quote Templates

Since version 1.7.2, **custom template names** are added through an **allowlist** in `ipconfig.php` —
the filesystem is never scanned, which is what keeps the template system safe from remote code
execution. See [CUSTOM_TEMPLATES.md](.github/docs/CUSTOM_TEMPLATES.md) for the how-to, and
[UPGRADE.md](.github/docs/UPGRADE.md) before upgrading an installation that already uses custom
templates.

---

## Session Storage

Session files are stored in PHP's default session save path (`sys_get_temp_dir()`) unless
overridden. Set `SESS_SAVE_PATH` in `ipconfig.php` to an absolute path to store sessions
elsewhere, e.g. outside the document root for additional security:

```
SESS_SAVE_PATH=/var/lib/invoicemuse/storage/framework/sessions
```

> **Do not leave `SESS_SAVE_PATH` set to an empty value.** An empty `SESS_SAVE_PATH=`
> line is passed to PHP as an empty `session.save_path`, overriding any value from
> `php.ini` / `php-fpm.d` / your vhost. Sessions then cannot be written — login fails and
> the installer stays stuck on `.../setup/language`. Either give it a real absolute path
> or remove/comment the line entirely so the `sys_get_temp_dir()` fallback applies. On
> systemd distros avoid `/tmp` (services run with `PrivateTmp=true` and it gets wiped).

If you mount a volume in Docker, include the configured path in your persistent volumes
(see [Container (Docker) Deployment Instructions](.github/docs/CONTAINER_DEPLOYMENT.md)).

---

## Container Deployment

The container image builds from `resources/docker/Containerfile` and is configured entirely
through environment variables, so no `ipconfig.php` file is needed. The entrypoint generates the
configuration and runs any pending database migrations automatically on startup.

See [Container (Docker) Deployment Instructions](.github/docs/CONTAINER_DEPLOYMENT.md) for the full list of
required/optional environment variables, default admin user setup, and persistent volumes.

---

## Upstream Resources

The [InvoicePlane wiki](https://wiki.invoiceplane.com/) and
[community forum](https://community.invoiceplane.com/) may still be useful because InvoiceMuse
is derived from InvoicePlane. These are upstream resources and do not provide official
InvoiceMuse support.

---

## Contributing

Contributions are welcome. Use this repository's issue tracker and pull-request interface.
For contribution and translation guidance, see [CONTRIBUTING.md](.github/CONTRIBUTING.md) and
[TRANSLATIONS.md](.github/TRANSLATIONS.md).

### Developer Resources

- **[Development Guidelines](.junie/guidelines.md)** — Security patterns and code review checklist
- **[Agent / AI Instructions](AGENTS.md)** — Guide for AI coding assistants working on this codebase
- **[Copilot Instructions](.github/copilot-instructions.md)** — GitHub Copilot context
- **[Docker Setup](resources/docker/README.md)** — Docker configuration guide

---

## Security

If you discover a security vulnerability, follow the private reporting process described in
[SECURITY.md](SECURITY.md) before disclosing it publicly.

Historical upstream advisories and per-version security notes are retained in
[`.github/security/`](.github/security/) for traceability.

---

## License & Copyright

InvoiceMuse includes software originally released as InvoicePlane under the
[MIT License](LICENSE.txt). The original InvoicePlane copyright notice and permission notice
remain in that file as required by the license.

The **InvoicePlane name** and **logo** belong to their respective owners. InvoiceMuse uses its
own name and original visual identity; upstream trademarks are referenced only for attribution
and compatibility documentation.

### Compatibility names

Some internal identifiers keep their historical InvoicePlane names for upgrade and
saved-setting compatibility: database tables, configuration constants, and the `invoiceplane`
and `invoiceplane_blue` theme folders.
