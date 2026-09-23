# GitHub Actions Workflows

This directory contains GitHub Actions workflows for automated CI/CD tasks.

## Composite Actions

### Setup PHP with Composer (`.github/actions/setup-php-composer`)

A reusable composite action that sets up PHP and installs Composer dependencies with intelligent caching.

**Benefits:**
- Reduces Composer install time from 8-12 seconds to 2-4 seconds (with cache hit)
- Consistent PHP and Composer setup across all workflows
- Centralized cache management

**Usage:**
```yaml
- name: Setup PHP with Composer
  uses: ./.github/actions/setup-php-composer
  with:
    php-version: '8.2'  # Optional, defaults to 8.2
    php-extensions: 'mbstring, xml, json'  # Optional
    composer-flags: '--no-dev --optimize-autoloader'  # Optional
```

**Used by:**
- `phpunit.yml` - Test execution
- `phpstan.yml` - Static analysis
- `pint.yml` - Code formatting
- `composer-update.yml` - Dependency updates
- `yarn-update.yml` - Frontend dependency updates

## Available Workflows

### 1. Release (`release-tag.yml`)

**Trigger:** Pushing a version tag (`v1.0.0`, or `v1.1.0-rc.1` for a pre-release), or running it manually

**Purpose:** Builds the InvoiceMuse release package with `resources/release/build-package.sh` and attaches it to a draft GitHub Release

**What it does:**
1. **Checks the version** - The tag must match `INVOICEMUSE_VERSION` in `application/config/constants.php` and the version in `package.json`
2. **Installs PHP dependencies** - `composer install --no-dev --optimize-autoloader`
3. **Builds frontend assets** - `yarn install --frozen-lockfile && yarn build`
4. **Packages the release** - Writes `invoicemuse-v<version>.zip` and a SHA-256 checksum file
5. **Creates a draft release** - For tag pushes only, with notes taken from the matching `CHANGELOG.md` section

Manual runs build the package as a workflow artifact without creating a release, which is useful for checking a build before tagging.

**Releasing a version:**
1. Set the new version in `application/config/constants.php` (`INVOICEMUSE_VERSION`) and `package.json`
2. Add a `## [<version>] - <date>` section to `.github/CHANGELOG.md`
3. Merge to `main`, then tag and push: `git tag v1.0.0 && git push origin v1.0.0`
4. Review the draft release on GitHub and publish it

The build runs the same script you can run locally: `resources/release/build-package.sh 1.0.0 dist/`.

### 2. Composer Dependency Update (`composer-update.yml`)

**Trigger:**
- Scheduled: Weekly on Mondays at 9:00 AM UTC
- Manual dispatch with update type selection

**Purpose:** Automates Composer (PHP) dependency updates with security checks

**What it does:**
1. **Runs security audit** - Checks for known vulnerabilities
2. **Updates dependencies** - Based on selected update type
3. **Runs smoke tests** - Fast verification after updates (if changes detected)
4. **Creates pull request** - Automated PR with update details

**Update Types:**
- `security-patch` - Security and patch updates (default for scheduled runs)
- `patch-minor` - Patch and minor version updates
- `all-dependencies` - All updates including major versions with `composer bump`

**Smoke Tests:**

After dependencies are updated, the workflow automatically runs smoke tests to verify core functionality:
- Only runs if `composer.lock` has changes
- Uses `phpunit.smoke.xml` configuration
- Executes tests marked with `#[Group('smoke')]`
- Continues even if tests fail (with `continue-on-error: true`)
- Typically completes in 10-30 seconds

**Required Secrets:**

This workflow requires a Personal Access Token (PAT) to create pull requests:

- `PAT_TOKEN` - A GitHub Personal Access Token with `repo` and `workflow` scopes

To create and configure the PAT:
1. Go to [GitHub Settings > Developer settings > Personal access tokens (classic)](https://github.com/settings/tokens)
2. Click "Generate new token (classic)"
3. Give it a descriptive name like "InvoicePlane Automation"
4. Select the `repo` and `workflow` scopes
5. Generate and copy the token
6. Go to your repository Settings > Secrets and variables > Actions
7. Click "New repository secret"
8. Name: `PAT_TOKEN`, Value: paste your token
9. Click "Add secret"

**Why is a PAT required?**

The default `GITHUB_TOKEN` has restricted permissions and cannot create pull requests that trigger other workflows (like CI tests). This is a GitHub security measure. Using a PAT with appropriate scopes allows the workflow to create PRs that will trigger other workflows.

**Required Permissions:**
- `contents: write` - For creating branches and commits
- `pull-requests: write` - For creating pull requests

### 3. Yarn Dependency Update (`yarn-update.yml`)

**Trigger:**
- Scheduled: Weekly on Mondays at 10:00 AM UTC
- Manual dispatch with update type selection

**Purpose:** Automates Yarn (JavaScript) dependency updates with security checks

**What it does:**
1. **Runs security audit** - Checks for known vulnerabilities
2. **Updates dependencies** - Based on selected update type
3. **Builds assets** - Verifies frontend builds correctly
4. **Creates pull request** - Automated PR with update details

**Update Types:**
- `security-only` - Only security fixes (default for scheduled runs)
- `patch-minor` - Patch and minor version updates
- `all-dependencies` - All updates including major versions

**Required Secrets:**

This workflow requires a Personal Access Token (PAT) to create pull requests:

- `PAT_TOKEN` - A GitHub Personal Access Token with `repo` and `workflow` scopes

To create and configure the PAT:
1. Go to [GitHub Settings > Developer settings > Personal access tokens (classic)](https://github.com/settings/tokens)
2. Click "Generate new token (classic)"
3. Give it a descriptive name like "InvoicePlane Automation"
4. Select the `repo` and `workflow` scopes
5. Generate and copy the token
6. Go to your repository Settings > Secrets and variables > Actions
7. Click "New repository secret"
8. Name: `PAT_TOKEN`, Value: paste your token
9. Click "Add secret"

**Why is a PAT required?**

The default `GITHUB_TOKEN` has restricted permissions and cannot create pull requests that trigger other workflows (like CI tests). This is a GitHub security measure. Using a PAT with appropriate scopes allows the workflow to create PRs that will trigger other workflows.

**Required Permissions:**
- `contents: write` - For creating branches and commits
- `pull-requests: write` - For creating pull requests

### 4. PHPUnit Tests (`phpunit.yml`)

**Trigger:** Manual dispatch only

Runs the PHPUnit test suite against a MySQL database.

### 5. Laravel Pint (`pint.yml`)

**Trigger:** 
- Automatically on pull requests targeting `master` or `develop` branches
- Manual dispatch

**Purpose:** Ensures consistent PHP code style across the codebase using Laravel Pint (PSR-12 standard)

**What it does:**
1. **Checks out the PR branch** - Gets the latest code from the pull request
2. **Sets up PHP environment** - Installs PHP 8.2 with required extensions
3. **Installs dependencies** - Runs `composer install`
4. **Runs Laravel Pint** - Automatically fixes code style issues
5. **Commits changes** - Pushes formatted code back to the PR (if changes were made)
6. **Reports parse errors** - Identifies files with syntax errors that couldn't be formatted

**Best Practices:**

**When to use Pint:**
- **Before committing** - Run `vendor/bin/pint` locally before pushing code
- **On every PR** - The workflow automatically runs on PRs to master/develop
- **Manual cleanup** - Use workflow_dispatch to format the entire codebase
- **After merging** - Run manually if formatting conflicts occur

**Local Development:**
```bash
# Format all files
vendor/bin/pint

# Format specific files/directories
vendor/bin/pint app/Models
vendor/bin/pint Modules/Invoices

# Check without modifying files (dry-run)
vendor/bin/pint --test

# See what changes Pint would make
vendor/bin/pint --test -v
```

**Pre-commit Hook (Recommended):**

To automatically format code before each commit, add this to `.git/hooks/pre-commit`:

```bash
#!/bin/sh
# Run Laravel Pint on staged PHP files
php vendor/bin/pint $(git diff --cached --name-only --diff-filter=ACM | grep '\.php$')
```

Make it executable: `chmod +x .git/hooks/pre-commit`

**IDE Integration:**

- **PHPStorm/IntelliJ:** Configure as External Tool or File Watcher
- **VS Code:** Install "Laravel Pint" extension for automatic formatting
- **Sublime Text:** Use "Laravel Pint" package

**Configuration:**

Pint uses the configuration in `pint.json` which follows PSR-12 with custom rules:
- Short array syntax
- Single quotes for strings
- Aligned operators (=, =>)
- Ordered imports and class elements
- Strict null coalescing
- And more...

See `pint.json` for complete rule set.

**Handling Parse Errors:**

If Pint reports parse errors:
1. Review the workflow output to identify files with syntax errors (marked with `!`)
2. Fix the syntax errors manually
3. Re-run Pint locally or via the workflow
4. Formatted files are still committed even if some files have errors

**Why Run on PRs:**

Running Pint automatically on PRs ensures:
- Consistent code style across all contributions
- No style-related review comments needed
- Cleaner git history (style fixes separate from logic changes)
- Reduced merge conflicts related to formatting
- Faster code reviews (focus on logic, not style)

**Workflow Permissions:**
- `contents: write` - Required to commit and push formatting changes

**Note:** The workflow only runs on PRs targeting `master` or `develop` branches to avoid unnecessary runs on feature branches. You can always trigger it manually for any branch using workflow_dispatch.

### 6. PHPStan Static Analysis (`phpstan.yml`)

**Trigger:** Manual dispatch only

**Purpose:** Runs static analysis on PHP code to detect type errors, bugs, and potential issues before runtime

**What it does:**
1. **Runs PHPStan analysis** - Analyzes code with JSON output format
2. **Parses and formats results** - Converts verbose JSON into actionable markdown
3. **Groups errors by category** - Type errors, method errors, property errors, etc.
4. **Generates checklists** - Creates ready-to-use task lists for fixing errors
5. **Uploads artifacts** - Saves JSON and markdown reports for later review
6. **Comments on PRs** (if triggered from PR) - Posts formatted report as PR comment

**Analysis Features:**

The workflow includes smart error formatting:
- **Type Errors** - Type mismatches and expectations
- **Method Errors** - Undefined or incorrect method calls
- **Property Errors** - Property access issues
- ↩️ **Return Type Errors** - Incorrect return types
- **Other Errors** - Miscellaneous issues

**Local Development:**

```bash
# Run PHPStan with standard output
vendor/bin/phpstan analyse --memory-limit=1G

# Generate JSON output for parsing
vendor/bin/phpstan analyse --error-format=json > phpstan.json

# Parse results into actionable format
php .github/scripts/parse-phpstan-results.php phpstan.json > phpstan-report.md

# View the formatted report
cat phpstan-report.md
```

**Best Practices:**

**When to run PHPStan:**
- **Before major refactoring** - Identify potential issues early
- **After adding new features** - Ensure type safety
- **When upgrading dependencies** - Catch compatibility issues
- **Before releases** - Final quality check

**Working with Results:**

The parser script generates three sections:
1. **Error Summary** - Quick overview by category
2. **Detailed Errors** - Grouped by file with line numbers
3. **Actionable Checklist** - Track fixes systematically

**Integration with Copilot:**

The formatted output is optimized for Copilot:
- JSON format provides precise, machine-readable data
- Trimmed context focuses on actionable items
- Explicit categorization aids understanding
- Checklist format enables task tracking

**Workflow:**
1. Generate JSON: `vendor/bin/phpstan analyse --error-format=json > phpstan.json`
2. Parse results: `php .github/scripts/parse-phpstan-results.php phpstan.json`
3. Feed to Copilot: Copy formatted report for automated suggestions
4. Fix errors: Use checklist to track progress systematically

**Configuration:**

PHPStan configuration is in `phpstan.neon`:
- Level 3 analysis (balanced strictness)
- Analyzes all `Modules/` directory
- Excludes HTTP controllers (autogenerated)
- Custom ignore patterns for framework-specific patterns

**Baseline Generation:**

If you need to accept existing errors and focus on new issues:
```bash
vendor/bin/phpstan analyse --generate-baseline
```

This creates `phpstan-baseline.neon` with current errors. Uncomment the baseline include in `phpstan.neon`.

**Script Details:**

The parsing script (`.github/scripts/parse-phpstan-results.php`):
- Categorizes errors by type
- Trims verbose messages for readability
- Groups errors by file
- Generates markdown checklists
- Optimized for GitHub PR comments

See `.github/scripts/README.md` for detailed script documentation.

### 7. Docker Compose Check (`docker.yml`)

**Trigger:** Manual dispatch only

Tests Docker Compose configuration.

### 8. Setup & Install with Error Handling (`setup.yml`)

**Trigger:** Manual dispatch only

**Purpose:** Provides a complete application setup workflow with granular error handling and selective step execution for debugging

**What it does:**
1. **Yarn Install** - Installs JavaScript dependencies
2. **Composer Install** - Installs PHP dependencies  
3. **Environment Setup** - Copies `.env.example` to `.env`
4. **Key Generation** - Runs `php artisan key:generate`
5. **Database Migration** - Runs `php artisan migrate --force`
6. **Database Seeding** - Runs `php artisan db:seed`

**Key Features:**

**Selective Step Execution:**
- Each step can be individually enabled or disabled via workflow inputs
- Allows running only specific steps for debugging
- Default: All steps enabled

**Error Handling:**
- Uses `set +e` to continue execution after errors
- Each step captures its exit code
- Errors are logged to a central error report
- Workflow continues even if steps fail
- Final step reports all errors in a consolidated summary

**Error Reporting:**
- Errors logged with step name and exit code
- Detailed error report at workflow end
- GitHub Actions summary shows pass/fail status for each step
- Individual step logs preserved for debugging

**Usage:**

To run the full setup:
1. Go to **Actions** tab
2. Select **Setup & Install with Error Handling**
3. Click **Run workflow**
4. Leave all options as "true" (default)
5. Click **Run workflow**

To debug specific steps:
1. Go to **Actions** tab
2. Select **Setup & Install with Error Handling**
3. Click **Run workflow**
4. Set unwanted steps to "false"
5. Click **Run workflow**

**Example Scenarios:**

**Full Setup:**
- All inputs set to `true` (default)
- Runs complete installation from scratch

**Debug Seeding Only:**
- `run_seed`: `true`
- All others: `false`
- Useful for testing seeder changes

**Debug Migration + Seeding:**
- `run_migrate`: `true`
- `run_seed`: `true`
- All others: `false`
- Useful for testing database setup

**Infrastructure:**
- MariaDB 10.6 service container
- PHP 8.4 with required extensions
- Node.js 22 with Yarn caching

**Known Issues Fixed:**
- AddressFactory faker instance issue fixed (now uses `$this->faker` consistently)
- Yarn EISDIR errors handled gracefully
- All errors collected and reported at the end

### 9. Crowdin Translation Sync (`crowdin-sync.yml`)

**Trigger:**
- Scheduled: Weekly on Sundays at 2:00 AM UTC
- Manual dispatch with action type selection

**Purpose:** Automates translation synchronization with Crowdin

**What it does:**
1. **Uploads source files** - Pushes English translation files to Crowdin
2. **Downloads translations** - Retrieves translated files from Crowdin
3. **Creates pull request** - Automated PR with translation updates

**Action Types:**
- `upload-sources` - Upload source translation files only
- `download-translations` - Download translated files only (default)
- `sync-bidirectional` - Both upload and download

**Required Secrets:**

This workflow requires a Personal Access Token (PAT) to create pull requests:

- `PAT_TOKEN` - A GitHub Personal Access Token with `repo` and `workflow` scopes
- `CROWDIN_PROJECT_ID` - Your Crowdin project ID
- `CROWDIN_PERSONAL_TOKEN` - Your Crowdin personal access token

To create and configure the PAT:
1. Go to [GitHub Settings > Developer settings > Personal access tokens (classic)](https://github.com/settings/tokens)
2. Click "Generate new token (classic)"
3. Give it a descriptive name like "InvoicePlane Automation"
4. Select the `repo` and `workflow` scopes
5. Generate and copy the token
6. Go to your repository Settings > Secrets and variables > Actions
7. Click "New repository secret"
8. Name: `PAT_TOKEN`, Value: paste your token
9. Click "Add secret"

**Why is a PAT required?**

The default `GITHUB_TOKEN` has restricted permissions and cannot create pull requests that trigger other workflows (like CI tests). This is a GitHub security measure. Using a PAT with appropriate scopes allows the workflow to create PRs that will trigger other workflows.

**Required Permissions:**
- `contents: write` - For creating branches and commits
- `pull-requests: write` - For creating pull requests

## Dependency Management

### GitHub Dependabot

InvoicePlane v2 uses GitHub Dependabot for automated dependency updates. Configuration is in `.github/dependabot.yml`.

**What Dependabot monitors:**
- Composer (PHP dependencies) - Weekly updates on Mondays
- npm/Yarn (JavaScript dependencies) - Weekly updates on Mondays
- GitHub Actions - Monthly updates

**How it works:**
1. Dependabot scans for outdated or vulnerable dependencies
2. Creates pull requests for updates
3. Groups updates by type (security, patch, minor)
4. Automatically labels PRs for easy filtering

**Managing Dependabot PRs:**
- Review the changelog and breaking changes
- Run tests locally if needed
- Merge when ready or close if not needed
- Use `@dependabot rebase` to rebase the PR

See [MAINTENANCE.md](../MAINTENANCE.md) for detailed dependency management guidelines.

### Manual Dependency Updates

Use the manual workflows when you need immediate updates:

1. Go to **Actions** tab
2. Select **Composer Update** or **Yarn Update**
3. Click **Run workflow**
4. Select update type
5. Wait for automated PR

## Release Package Contents

`resources/release/build-package.sh` packages the committed tree only, with the same runtime files as `resources/docker/Containerfile`: `application/`, built `assets/`, `storage/`, `uploads/`, production `vendor/`, `index.php`, `htaccess`, `ipconfig.php.example`, `favicon.ico` and `robots.txt`, plus the license, README, changelog, security policy and install and upgrade guides.

The package ships in English and leaves out the InvoicePlane-Themes and InvoicePlane-e-invoices repositories, which state no license. `.github/docs/INSTALLATION.md` explains how to add them.

## Troubleshooting

### Crowdin Download Fails

If the Crowdin step fails, check:
1. Secrets are correctly configured
2. Your Crowdin personal token has not expired
3. The project ID is correct
4. Your Crowdin project is properly configured

### Build Fails

If the frontend build fails:
1. Ensure `package.json` is up to date
2. Check for syntax errors in Vite/Tailwind config
3. Verify all dependencies are correctly specified

### Composer Install Fails

If Composer installation fails:
1. Check `composer.json` for syntax errors
2. Ensure all required PHP extensions are available
3. Verify package versions are compatible

## Customization

### Changing PHP or Node.js Version

The release workflow sets PHP in the "Set up PHP 8.2" step of `release-tag.yml` and reads Node.js from `.node-version`.

### Changing Package Contents

Edit the `cp` lines in `resources/release/build-package.sh`.

## Best Practices

1. **Test locally first** - Before relying on the workflow, test the build process locally
2. **Monitor workflow runs** - Check the Actions tab regularly for failures
3. **Keep secrets secure** - Never commit secrets to the repository
4. **Update dependencies** - Keep GitHub Actions and dependencies up to date
5. **Tag releases** - Use semantic versioning for production releases

## Support

For issues or questions about these workflows:
- Create an issue in the repository
- Join the [Community Forums](https://community.invoiceplane.com)
- Visit the [Discord server](https://discord.gg/PPzD2hTrXt)
