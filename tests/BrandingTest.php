<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BrandingTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    private const LEGACY_TEMPLATES = ['InvoicePlane', 'InvoicePlane - paid', 'InvoicePlane - overdue', 'InvoicePlane_Web'];

    private const TEMPLATE_FILES = [
        'invoice_templates/pdf/%s.php',
        'invoice_templates/pdf/%s - paid.php',
        'invoice_templates/pdf/%s - overdue.php',
        'invoice_templates/public/%s_Web.php',
        'quote_templates/pdf/%s.php',
        'quote_templates/public/%s_Web.php',
    ];

    #[Test]
    public function it_preserves_upstream_attribution_and_uses_invoicemuse_branding(): void
    {
        $readme = $this->read('README.md');

        self::assertStringContainsString('# _InvoiceMuse_', $readme);
        self::assertStringContainsString('based on [InvoicePlane]', $readme);
        self::assertStringContainsString('git clone https://github.com/dnp-g2/InvoiceMuse.git', $readme);
        self::assertStringNotContainsString('github.com/InvoicePlane/InvoicePlane.git', $readme);
        self::assertStringContainsString('Copyright (c) 2012-2014 InvoicePlane.com', $this->read('LICENSE.txt'));
    }

    #[Test]
    public function it_routes_security_reports_to_invoicemuse(): void
    {
        $security = $this->read('SECURITY.md');

        self::assertStringContainsString('https://github.com/dnp-g2/InvoiceMuse/security/advisories/new', $security);
        self::assertStringNotContainsString('mail@invoiceplane.com', $security);
    }

    #[Test]
    public function it_shows_the_credit_on_admin_and_setup_pages_only(): void
    {
        $lang   = $this->read('application/language/english/ip_lang.php');
        $footer = $this->read('application/modules/layout/views/includes/product_footer.php');

        self::assertMatchesRegularExpression("/'product_credit'\\s*=> 'InvoiceMuse is based on <a href=\"https:\\/\\/www\\.invoiceplane\\.com\\/\"/", $lang);
        self::assertStringContainsString("_trans('product_credit')", $footer);
        self::assertStringContainsString('layout/includes/product_footer', $this->read('application/modules/layout/views/layout.php'));
        self::assertStringContainsString('layout/includes/product_footer', $this->read('application/modules/layout/views/setup.php'));
        self::assertStringNotContainsString('product_footer', $this->read('application/modules/layout/views/layout_guest.php'));

        foreach (['invoice_templates/pdf/InvoiceMuse.php', 'quote_templates/pdf/InvoiceMuse.php'] as $template) {
            self::assertStringNotContainsString('product_footer', $this->read('application/views/' . $template));
        }
    }

    #[Test]
    public function it_ships_credit_styles_in_the_build_and_leaves_custom_css_to_users(): void
    {
        self::assertStringNotContainsString('product-credit', $this->read('assets/core/css/custom.css'));
        self::assertStringContainsString('.product-credit', $this->read('assets/core/scss/_product-credit.scss'));
        self::assertStringContainsString('@import "product-credit";', $this->read('assets/core/scss/_core.scss'));
        self::assertStringContainsString('@import "product-credit";', $this->read('assets/core/scss/_welcome.scss'));
    }

    #[Test]
    public function it_does_not_query_upstream_services_for_invoicemuse_updates(): void
    {
        $updatesView = $this->read('application/modules/settings/views/partial_settings_updates.php');

        self::assertStringNotContainsString('ids.invoiceplane', $updatesView);
        self::assertStringContainsString("_trans('updatecheck_not_configured')", $updatesView);
    }

    #[Test]
    public function it_labels_the_bundled_themes_as_invoicemuse(): void
    {
        self::assertStringContainsString("TITLE='InvoiceMuse Default'", $this->read('assets/invoiceplane/invoiceplane.theme'));
        self::assertStringContainsString("TITLE='InvoiceMuse Blue'", $this->read('assets/invoiceplane_blue/invoiceplane_blue.theme'));
    }

    #[Test]
    public function it_ships_the_bundled_templates_under_invoicemuse_names(): void
    {
        foreach (self::TEMPLATE_FILES as $pattern) {
            self::assertFileExists(self::ROOT . '/application/views/' . sprintf($pattern, 'InvoiceMuse'));
            self::assertFileDoesNotExist(self::ROOT . '/application/views/' . sprintf($pattern, 'InvoicePlane'));
        }

        $gitignores = $this->read('application/views/invoice_templates/pdf/.gitignore')
            . $this->read('application/views/invoice_templates/public/.gitignore')
            . $this->read('application/views/quote_templates/pdf/.gitignore')
            . $this->read('application/views/quote_templates/public/.gitignore');
        self::assertStringNotContainsString('InvoicePlane', $gitignores);

        self::assertStringContainsString("include __DIR__ . '/InvoiceMuse.php';", $this->read('application/views/invoice_templates/pdf/InvoiceMuse - paid.php'));
        self::assertStringContainsString("include __DIR__ . '/InvoiceMuse.php';", $this->read('application/views/invoice_templates/pdf/InvoiceMuse - overdue.php'));
    }

    #[Test]
    public function it_allowlists_and_defaults_to_the_renamed_templates(): void
    {
        $model = $this->read('application/modules/invoices/models/Mdl_templates.php');

        foreach (['ALLOWED_INVOICE_TEMPLATES', 'ALLOWED_QUOTE_TEMPLATES'] as $constant) {
            self::assertSame(1, preg_match('/' . $constant . ' = \[(.*?)\n    \];/s', $model, $match));
            self::assertStringContainsString("'InvoiceMuse'", $match[1]);
            self::assertStringContainsString("'InvoiceMuse_Web'", $match[1]);
            self::assertStringNotContainsString('InvoicePlane', $match[1]);
        }

        $defaults = [
            'application/helpers/pdf_helper.php',
            'application/helpers/template_helper.php',
            'application/modules/guest/controllers/View.php',
            'application/modules/setup/models/Mdl_setup.php',
        ];
        foreach ($defaults as $file) {
            self::assertDoesNotMatchRegularExpression("/'InvoicePlane( - paid| - overdue|_Web)?'/", $this->read($file), $file);
        }
    }

    #[Test]
    public function it_migrates_saved_template_names_in_php(): void
    {
        self::assertStringNotContainsString('UPDATE', $this->read('application/modules/setup/sql/045_1.7.3.sql'));

        $setup = $this->read('application/modules/setup/models/Mdl_setup.php');
        self::assertSame(1, preg_match('/function upgrade_045_1_7_3\(\)\s*\{(.*?)\n    \}/s', $setup, $match));
        self::assertSame(2, substr_count($match[1], 'get_legacy_template_rename('));
        self::assertStringContainsString("update('ip_settings'", $match[1]);
        self::assertStringContainsString("update('ip_email_templates'", $match[1]);

        $model = $this->read('application/modules/invoices/models/Mdl_templates.php');
        foreach (self::LEGACY_TEMPLATES as $legacy) {
            $renamed = str_replace('InvoicePlane', 'InvoiceMuse', $legacy);
            self::assertMatchesRegularExpression("/'" . preg_quote($legacy, '/') . "'\\s*=> '" . preg_quote($renamed, '/') . "'/", $model);
        }
    }

    #[Test]
    public function it_keeps_the_release_version_in_step(): void
    {
        self::assertSame(1, preg_match("/define\\('INVOICEMUSE_VERSION', '([^']+)'\\)/", $this->read('application/config/constants.php'), $constant));
        $package = json_decode($this->read('package.json'), true);

        self::assertSame($constant[1], $package['version']);
        self::assertStringContainsString('## [' . $constant[1] . '] - ', $this->read('.github/CHANGELOG.md'));

        $updatesView = $this->read('application/modules/settings/views/partial_settings_updates.php');
        self::assertStringContainsString('html_escape(INVOICEMUSE_VERSION)', $updatesView);
        self::assertStringContainsString("_trans('database_schema_version')", $updatesView);
    }

    #[Test]
    public function it_builds_release_packages_from_this_repository_only(): void
    {
        $workflow = $this->read('.github/workflows/release-tag.yml');

        self::assertStringContainsString('resources/release/build-package.sh', $workflow);
        self::assertStringNotContainsString('InvoicePlane/', $workflow);
        self::assertStringNotContainsString('crowdin/github-action', $workflow);
        self::assertFileDoesNotExist(self::ROOT . '/.github/workflows/release.yml');
        self::assertTrue(is_executable(self::ROOT . '/resources/release/build-package.sh'));
    }

    #[Test]
    public function it_denies_web_access_to_vendor(): void
    {
        $htaccess = $this->read('htaccess');
        $forbid   = strpos($htaccess, 'RewriteRule ^vendor/ - [F,L]');
        self::assertIsInt($forbid);
        self::assertLessThan(strpos($htaccess, 'RewriteRule . /index.php [L]'), $forbid);

        $nginx = $this->read('resources/docker/nginx/invoiceplane.conf');
        foreach (['vendor', 'application'] as $directory) {
            self::assertMatchesRegularExpression('#location \^~ /' . $directory . '/ \{\s*deny all;#', $nginx);
        }
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(self::ROOT . '/' . $path);
        self::assertIsString($contents, $path);

        return $contents;
    }
}
