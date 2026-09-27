<?php

// Usage: php tests/render-slate.php /tmp/slate-previews
// Generates sample documents without connecting to the application database.
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/fixtures/slate_render.php';

$output = $argv[1] ?? sys_get_temp_dir() . '/slate-previews';
if ( ! is_dir($output)) {
    mkdir($output, 0777, true);
}
foreach (['mpdf', 'archive', 'temp'] as $directory) {
    if ( ! is_dir($output . '/' . $directory)) {
        mkdir($output . '/' . $directory, 0777, true);
    }
}
define('UPLOADS_TEMP_MPDF_FOLDER', $output . '/mpdf/');
define('UPLOADS_ARCHIVE_FOLDER', $output . '/archive/');
define('UPLOADS_TEMP_FOLDER', $output . '/temp/');
define('IP_DEBUG', false);

set_error_handler(static function ($severity, $message, $file, $line): bool {
    if ( ! (error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$scenarios = ['invoice', 'estimate', 'partial', 'paid', 'multipage', 'properties', 'tax-discount', 'missing-logo', 'long-description'];
foreach ($scenarios as $scenario) {
    slate_fixture_reset();
    $type                                                 = $scenario === 'estimate' ? 'quote' : 'invoice';
    $data                                                 = slate_fixture_data($type);
    $GLOBALS['slate_fixture']['settings']['invoice_logo'] = 'slate-fixture.png';
    $GLOBALS['slate_fixture']['session']                  = ['user_id' => 2, 'user_type' => 2];
    if (in_array($scenario, ['partial', 'paid'], true)) {
        $paid                                 = $scenario === 'paid' ? 450 : 100;
        $data['invoice']->invoice_paid        = $paid;
        $data['invoice']->invoice_balance     = 450 - $paid;
        $data['invoice']->invoice_status_id   = $scenario === 'paid' ? 4 : 2;
        $GLOBALS['slate_fixture']['payments'] = [(object) ['payment_date' => '2026-02-18', 'payment_amount' => $paid, 'payment_method_name' => 'Cash']];
    }
    if ($scenario === 'multipage') {
        $data['items'] = [];
        for ($i = 0; $i < 24; $i++) {
            $item            = clone slate_fixture_data()['items'][0];
            $item->item_name = 'Maintenance visit ' . ($i + 1);
            $data['items'][] = $item;
        }
        $data['invoice']->invoice_total = $data['invoice']->invoice_balance = $data['invoice']->invoice_item_subtotal = 3600;
        $data['invoice']->invoice_terms = '';
        for ($i = 1; $i <= 16; $i++) {
            $data['invoice']->invoice_terms .= $i . ". Service terms\n" . str_repeat('Work will be completed as described in the approved scope. Please contact our office with scheduling questions. ', 4) . "\n\n";
        }
    }
    if ($scenario === 'long-description') {
        $data['items'][0]->item_description = str_repeat('Detailed service description with inspections, repairs, materials and cleanup included in this line item. ', 65);
    }
    if ($scenario === 'properties') {
        $GLOBALS['slate_fixture']['properties'] = true;
        foreach ($data['items'] as $i => $item) {
            $item->item_service_property_id = $i < 2 ? 1 : 2;
            $item->item_service_address     = json_encode(['address_1' => $i < 2 ? '125 Garden Avenue' : '88 Magnolia Lane', 'city' => 'Port Saint Lucie', 'state' => 'FL']);
        }
    }
    if ($scenario === 'tax-discount') {
        $data['invoice']->invoice_discount_amount = 10;
        $data['invoice']->invoice_item_tax_total  = 21.75;
        $data['invoice']->invoice_item_subtotal   = 435;
        $data['invoice']->invoice_total           = $data['invoice']->invoice_balance = 465.42;
        $data['items'][0]->item_discount          = 5;
        $data['items'][0]->item_total             = 152.25;
        $data['items'][1]->item_total             = 315;
        $data['invoice_tax_rates']                = [(object) ['invoice_tax_rate_name' => 'Local tax', 'invoice_tax_rate_percent' => 2, 'invoice_tax_rate_amount' => 8.67]];
    }
    if ($scenario === 'missing-logo') {
        $GLOBALS['slate_fixture']['settings']['invoice_logo'] = '';
    }
    $html = slate_fixture_render($type, true, $data);
    file_put_contents($output . '/slate-' . $scenario . '-pdf.html', $html);
    $file = pdf_create($html, 'Slate-' . $scenario, false, null, $type === 'invoice');
    copy($file, $output . '/Slate-' . $scenario . '.pdf');
    file_put_contents($output . '/slate-' . $scenario . '.html', slate_fixture_render($type, false, $data));
    echo 'Rendered ' . $scenario . PHP_EOL;
}
