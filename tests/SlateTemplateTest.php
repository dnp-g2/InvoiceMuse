<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SlateTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        if (defined('BASEPATH')) {
            self::markTestSkipped('Standalone template fixture requires no running CI application.');
        }
        require __DIR__ . '/fixtures/slate_render.php';
        slate_fixture_reset();
    }

    #[Test]
    public function it_selects_slate_through_the_existing_allowlists_and_rejects_paths(): void
    {
        $templates                    = new Mdl_Templates();
        get_instance()->mdl_templates = $templates;
        foreach (['invoice', 'quote'] as $type) {
            self::assertSame('Slate', validate_template_name('Slate', $type, 'pdf'));
            self::assertSame('Slate_Web', validate_template_name('Slate_Web', $type, 'public'));
            self::assertFalse(validate_template_name('../Slate', $type, 'pdf'));
            self::assertSame('InvoiceMuse', validate_template_name('InvoiceMuse', $type, 'pdf'));
        }
    }

    #[Test]
    public function it_renders_both_documents_in_both_formats_with_escaped_recipient_data(): void
    {
        foreach (['invoice', 'quote'] as $type) {
            foreach ([true, false] as $pdf) {
                $data                               = slate_fixture_data($type);
                $data[$type]->client_name           = '<script>alert(1)</script>';
                $data['items'][0]->item_description = '<img src=x onerror=alert(1)>';
                $html                               = slate_fixture_render($type, $pdf, $data);
                self::assertStringContainsString($type === 'invoice' ? 'INVOICE' : 'ESTIMATE', $html);
                self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
                self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
                self::assertStringNotContainsString('<script>', $html);
                self::assertStringNotContainsString('Powered by', $html);
                self::assertStringContainsString('$450.00', $html);
                if ( ! $pdf) {
                    self::assertStringContainsString('/fixture-document-key/1/Slate', $html);
                }
            }
        }
    }

    #[Test]
    public function it_loads_only_recipient_payment_fields_for_this_invoice_and_renders_them(): void
    {
        $data                                 = slate_fixture_data();
        $data['invoice']->invoice_paid        = 450;
        $data['invoice']->invoice_balance     = 0;
        $data['invoice']->invoice_status_id   = 4;
        $GLOBALS['slate_fixture']['payments'] = [(object) ['payment_date' => '2026-02-18', 'payment_amount' => 450, 'payment_method_name' => 'Cash & check']];
        foreach ([true, false] as $pdf) {
            $html = slate_fixture_render('invoice', $pdf, $data);
            self::assertStringContainsString('Payment on February 18, 2026 using Cash &amp; check', $html);
            self::assertStringContainsString('$0.00', $html);
            self::assertStringNotContainsString('Pay Now', $html);
        }
        $calls = get_instance()->db->calls;
        self::assertContains(['where', ['p.invoice_id', 17]], $calls);
        self::assertContains(['select', ['p.payment_date, p.payment_amount, m.payment_method_name']], $calls);
    }

    #[Test]
    public function it_preserves_tax_discount_order_and_correct_columns(): void
    {
        $data                                      = slate_fixture_data();
        $data['invoice']->invoice_discount_percent = 10;
        $data['invoice']->invoice_item_tax_total   = 20;
        $data['invoice']->invoice_total            = 425;
        $data['items'][0]->item_discount           = 5;
        $data['invoice_tax_rates']                 = [(object) ['invoice_tax_rate_name' => 'Local <tax>', 'invoice_tax_rate_percent' => 2, 'invoice_tax_rate_amount' => 8]];
        $html                                      = slate_fixture_render('invoice', true, $data);
        $summary                                   = substr($html, strpos($html, '<table class="slate-summary"'));
        self::assertLessThan(strpos($summary, 'Subtotal'), strpos($summary, 'Discount'));
        self::assertStringContainsString('Local &lt;tax&gt;', $summary);
        self::assertStringContainsString('$425.00', $summary);
        $data['legacy_calculation'] = true;
        $html                       = slate_fixture_render('invoice', true, $data);
        $summary                    = substr($html, strpos($html, '<table class="slate-summary"'));
        self::assertGreaterThan(strpos($summary, 'Local &lt;tax&gt;'), strpos($summary, 'Discount'));
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        self::assertSame(5, $xpath->query('//table[@class="slate-items"]/thead/tr/th')->length);
    }

    #[Test]
    public function it_keeps_estimate_approval_forms_authenticated_and_csrf_protected(): void
    {
        $data = slate_fixture_data('quote');
        self::assertStringNotContainsString('<form', slate_fixture_render('quote', false, $data));
        $GLOBALS['slate_fixture']['session'] = ['user_id' => 2, 'user_type' => 2];
        $html                                = slate_fixture_render('quote', false, $data);
        self::assertSame(2, substr_count($html, 'method="post"'));
        self::assertSame(2, substr_count($html, 'name="csrf_token"'));
        self::assertStringContainsString('Approve estimate', $html);
        $data['quote']->quote_status_id = 4;
        self::assertStringNotContainsString('<form', slate_fixture_render('quote', false, $data));
    }

    #[Test]
    public function it_uses_property_snapshots_and_blocks_incomplete_publication(): void
    {
        $data                                       = slate_fixture_data();
        $GLOBALS['slate_fixture']['properties']     = true;
        $data['invoice']->client_name               = 'Edited customer name';
        $data['items'][0]->item_service_property_id = 7;
        $data['items'][0]->item_service_address     = json_encode(['address_1' => '125 Garden Avenue', 'city' => 'Port Saint Lucie']);
        $html                                       = slate_fixture_render('invoice', true, $data);
        self::assertStringContainsString('Sample Property Group', $html);
        self::assertStringNotContainsString('Edited customer name', $html);
        self::assertStringContainsString('Location: 125 Garden Avenue', $html);
        self::assertStringNotContainsString('Property line total', $html);
        self::assertSame(1, get_instance()->mdl_service_properties->published);
        $GLOBALS['slate_fixture']['problems'] = ['Service property required'];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(409);
        slate_fixture_render('invoice', false, $data);
    }

    #[Test]
    public function it_rejects_unsafe_logos_and_sanitizes_configured_footer_content(): void
    {
        foreach (['../config.php', 'https://example.invalid/logo.png', '/tmp/logo.png', 'logo.svg'] as $name) {
            $GLOBALS['slate_fixture']['settings']['invoice_logo'] = $name;
            self::assertSame('', slate_logo(true));
        }
        $GLOBALS['slate_fixture']['settings']['invoice_logo'] = 'logo_300x150.jpg';
        self::assertStringContainsString('alt="Invoice Logo"', slate_logo(true));
        $GLOBALS['slate_fixture']['settings']['pdf_invoice_footer'] = '<img src="https://example.invalid/tracker"><b onclick="x()">Thank you</b>';
        $html                                                       = slate_fixture_render('invoice', true, slate_fixture_data());
        self::assertStringContainsString('<b>Thank you</b>', $html);
        self::assertStringNotContainsString('tracker', $html);
        self::assertStringNotContainsString('onclick', $html);
    }

    #[Test]
    public function it_continues_long_descriptions_without_losing_text_or_repeating_charges(): void
    {
        $description = str_repeat("Café service and repairs.\n", 120) . str_repeat('x', 1000);
        $chunks      = slate_description_chunks($description, true);
        self::assertSame($description, implode('', $chunks));
        self::assertGreaterThan(1, count($chunks));
        self::assertSame([$description], slate_description_chunks($description, false));
        $data                               = slate_fixture_data();
        $data['items'][0]->item_description = $description;
        $html                               = slate_fixture_render('invoice', true, $data);
        self::assertStringContainsString('slate-continuation', $html);
        // Only the price and amount cells contain this charge, even across continuation rows.
        self::assertSame(2, substr_count($html, '$150.00'));
    }

    #[Test]
    public function it_allows_incomplete_admin_pdf_previews_with_a_clear_warning(): void
    {
        define('PROPERTY_ADMIN_PREVIEW', true);
        $GLOBALS['slate_fixture']['properties'] = true;
        $GLOBALS['slate_fixture']['problems']   = ['Service property required'];
        $html                                   = slate_fixture_render('invoice', true, slate_fixture_data());
        self::assertStringContainsString('INCOMPLETE DRAFT:', $html);
        self::assertSame(0, get_instance()->mdl_service_properties->published);
    }

    #[Test]
    public function it_displays_paid_reference_details_in_pdf_and_web_without_duplicate_locations(): void
    {
        $data                                       = slate_fixture_data();
        $data['invoice']->invoice_balance           = 0;
        $data['invoice']->invoice_status_id         = 4;
        $data['invoice']->client_contact_name       = 'Juan Arcila';
        $data['invoice']->invoice_terms             = str_repeat("Terms of payment and warranty apply.\n\n", 35);
        $GLOBALS['slate_fixture']['payments']       = [(object) ['payment_date' => '2026-02-18', 'payment_amount' => 450, 'payment_method_name' => 'Cash']];
        $GLOBALS['slate_fixture']['properties']     = true;
        $data['items'][0]->item_service_property_id = 7;
        $data['items'][0]->item_service_address     = json_encode(['address_1' => '88 Magnolia Lane']);
        foreach ([true, false] as $pdf) {
            $html = slate_fixture_render('invoice', $pdf, $data);
            self::assertStringContainsString('Juan Arcila', $html);
            self::assertStringContainsString('Payment Due', $html);
            self::assertStringContainsString('Quantity', $html);
            self::assertStringContainsString('Location: 88 Magnolia Lane', $html);
            self::assertSame(1, substr_count($html, 'Location: 88 Magnolia Lane'));
            self::assertSame(2, substr_count($html, 'Location: 125 Garden Avenue'));
            self::assertStringContainsString('Payment on February 18, 2026 using Cash', $html);
            self::assertStringContainsString('THANK YOU!', $html);
        }
    }

    #[Test]
    public function it_keeps_customer_written_location_lines_when_replacing_the_stale_one(): void
    {
        $item = (object) [
            'item_description'     => "Replace valves\nLocation: basement utility room, gate code 4411\nLocation: 125 Garden Avenue",
            'item_service_address' => json_encode(['address_1' => '125 Garden Avenue']),
        ];
        [$description, $location] = slate_service_details($item, true);
        self::assertSame('125 Garden Avenue', $location);
        self::assertSame("Replace valves\nLocation: basement utility room, gate code 4411", $description);

        $item->item_description = "Location: 125 Garden Avenue\nReplace valves\nLocation: basement utility room, gate code 4411";
        [$description]          = slate_service_details($item, true);
        self::assertSame("Replace valves\nLocation: basement utility room, gate code 4411", $description);

        $item->item_description = "Replace valves\nLocation: 88 Magnolia Lane";
        [$description]          = slate_service_details($item, true);
        self::assertSame('Replace valves', $description);
    }

    #[Test]
    public function it_prints_the_full_property_address_as_the_service_location(): void
    {
        $item = (object) [
            'item_description'     => "Replace valves\nLocation: 125 Garden Avenue",
            'item_service_address' => json_encode(['label' => 'Main house', 'address_1' => '125 Garden Avenue', 'city' => 'Port Saint Lucie', 'state' => 'FL', 'zip' => '34952']),
        ];
        [$description, $location] = slate_service_details($item, true);
        self::assertSame('Main house, 125 Garden Avenue, Port Saint Lucie, FL, 34952', $location);
        self::assertSame('Replace valves', $description);
    }
}
