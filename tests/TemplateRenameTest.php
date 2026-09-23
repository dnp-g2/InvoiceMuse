<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Each test runs in its own process because the ipconfig values are PHP constants.
 */
final class TemplateRenameTest extends TestCase
{
    private const ALLOWLISTS = [
        'CUSTOM_INVOICE_TEMPLATES_PDF',
        'CUSTOM_INVOICE_TEMPLATES_PUBLIC',
        'CUSTOM_QUOTE_TEMPLATES_PDF',
        'CUSTOM_QUOTE_TEMPLATES_PUBLIC',
    ];

    private ?string $custom_folder = null;

    protected function tearDown(): void
    {
        if ($this->custom_folder === null) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->custom_folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->custom_folder);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_renames_bundled_legacy_names(): void
    {
        $templates = $this->load_templates();

        self::assertSame('InvoiceMuse', $templates->get_legacy_template_rename('InvoicePlane', 'invoice_templates/pdf'));
        self::assertSame('InvoiceMuse - paid', $templates->get_legacy_template_rename('InvoicePlane - paid', 'invoice_templates/pdf'));
        self::assertSame('InvoiceMuse - overdue', $templates->get_legacy_template_rename('InvoicePlane - overdue', 'invoice_templates/pdf'));
        self::assertSame('InvoiceMuse_Web', $templates->get_legacy_template_rename('InvoicePlane_Web', 'quote_templates/public'));
        self::assertNull($templates->get_legacy_template_rename('InvoiceMuse', 'invoice_templates/pdf'));
        self::assertNull($templates->get_legacy_template_rename('MyTemplate', 'invoice_templates/pdf'));
        self::assertNull($templates->get_legacy_template_rename('', 'invoice_templates/pdf'));
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_keeps_legacy_names_listed_as_custom_templates(): void
    {
        $templates = $this->load_templates(['CUSTOM_INVOICE_TEMPLATES_PDF' => 'MyTemplate, InvoicePlane']);

        self::assertNull($templates->get_legacy_template_rename('InvoicePlane', 'invoice_templates/pdf'));
        self::assertSame('InvoiceMuse', $templates->get_legacy_template_rename('InvoicePlane', 'quote_templates/pdf'));
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_keeps_legacy_names_overridden_in_the_custom_folder(): void
    {
        $templates = $this->load_templates([], ['invoice_templates/public/InvoicePlane_Web.php']);

        self::assertNull($templates->get_legacy_template_rename('InvoicePlane_Web', 'invoice_templates/public'));
        self::assertSame('InvoiceMuse_Web', $templates->get_legacy_template_rename('InvoicePlane_Web', 'quote_templates/public'));
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_warns_about_unlisted_custom_overrides_and_skips_bundled_leftovers(): void
    {
        $GLOBALS['template_test_settings'] = [
            'pdf_invoice_template'      => 'InvoicePlane',
            'pdf_invoice_template_paid' => 'InvoicePlane - paid',
            'pdf_quote_template'        => 'InvoiceMuse',
            'public_quote_template'     => 'Unknown',
        ];
        $templates = $this->load_templates([], ['invoice_templates/pdf/InvoicePlane - paid.php']);

        self::assertSame(
            [
                'CUSTOM_INVOICE_TEMPLATES_PDF'  => ['InvoicePlane - paid'],
                'CUSTOM_QUOTE_TEMPLATES_PUBLIC' => ['Unknown'],
            ],
            $templates->get_missing_allowlisted_template_settings()
        );
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_warns_about_email_template_pdf_choices(): void
    {
        $GLOBALS['template_test_rows']['ip_email_templates'] = [
            (object) ['email_template_type' => 'invoice', 'email_template_pdf_template' => 'InvoicePlane - overdue'],
            (object) ['email_template_type' => 'quote', 'email_template_pdf_template' => 'InvoicePlane'],
            (object) ['email_template_type' => 'invoice', 'email_template_pdf_template' => 'InvoiceMuse'],
            (object) ['email_template_type' => 'quote', 'email_template_pdf_template' => 'MyQuote'],
            (object) ['email_template_type' => 'invoice', 'email_template_pdf_template' => null],
        ];
        $templates = $this->load_templates([], ['invoice_templates/pdf/InvoicePlane - overdue.php']);

        self::assertSame(
            [
                'CUSTOM_INVOICE_TEMPLATES_PDF' => ['InvoicePlane - overdue'],
                'CUSTOM_QUOTE_TEMPLATES_PDF'   => ['MyQuote'],
            ],
            $templates->get_missing_allowlisted_template_settings()
        );
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_finds_template_files_only_in_the_custom_folder(): void
    {
        $this->load_templates([], ['quote_templates/pdf/Custom Quote.php']);

        self::assertSame($this->custom_folder . 'quote_templates/pdf/Custom Quote.php', custom_template_file('quote_templates/pdf/Custom Quote'));
        self::assertSame($this->custom_folder . 'quote_templates/pdf/Custom Quote.php', custom_template_file('quote_templates/pdf/Custom Quote.php'));
        self::assertNull(custom_template_file('quote_templates/pdf/Missing'));
    }

    /**
     * @param array<string, string> $allowlists   ipconfig allowlist values by constant name
     * @param array<int, string>    $custom_files Files to create under a temporary CUSTOM_TEMPLATES_FOLDER
     */
    private function load_templates(array $allowlists = [], array $custom_files = []): Mdl_Templates
    {
        if (defined('BASEPATH')) {
            self::markTestSkipped('Needs a process without CodeIgniter loaded.');
        }

        if ($custom_files !== []) {
            $this->custom_folder = sys_get_temp_dir() . '/invoicemuse-templates-' . bin2hex(random_bytes(4)) . '/';
            foreach ($custom_files as $file) {
                $path = $this->custom_folder . $file;
                is_dir(dirname($path)) || mkdir(dirname($path), 0777, true);
                touch($path);
            }
        }

        define('CUSTOM_TEMPLATES_FOLDER', $this->custom_folder);
        foreach (self::ALLOWLISTS as $constant) {
            define($constant, $allowlists[$constant] ?? null);
        }

        require_once __DIR__ . '/fixtures/template_model_stubs.php';
        require_once __DIR__ . '/../application/helpers/template_helper.php';
        require_once __DIR__ . '/../application/modules/invoices/models/Mdl_templates.php';

        return new Mdl_Templates();
    }
}
