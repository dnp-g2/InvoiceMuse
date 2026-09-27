<?php
defined('BASEPATH') || exit('No direct script access allowed');
$ci = &get_instance();
$ci->load->helper(['slate_template', 'country', 'client', 'file_security', 'mpdf']);
$slate_invoice      = $slate_type === 'invoice';
$document           = $slate_invoice ? $invoice : $quote;
$property_render    = property_prepare_render($slate_type, $document, $items, $slate_pdf && defined('PROPERTY_ADMIN_PREVIEW') && PROPERTY_ADMIN_PREVIEW);
$slate_title        = trans($slate_invoice ? 'invoice' : 'slate_estimate');
$slate_number       = (string) $document->{$slate_type . '_number'};
$slate_amount_label = trans($slate_invoice ? 'amount_due' : 'slate_estimate_total');
$slate_currency     = (string) get_setting('currency_code');
if ($slate_currency !== '') {
    $slate_amount_label .= ' (' . $slate_currency . ')';
}
$slate_amount        = $document->{$slate_invoice ? 'invoice_balance' : 'quote_total'};
$slate_company       = ($document->user_company ?? '') ?: ($document->user_name ?? '');
$slate_logo          = slate_logo($slate_pdf);
$slate_payments      = $slate_invoice ? slate_payment_rows((int) $document->invoice_id) : [];
$slate_tax_rates     = $slate_invoice ? $invoice_tax_rates : $quote_tax_rates;
$slate_rows          = slate_summary_rows($document, $slate_type, $slate_tax_rates, (bool) $legacy_calculation);
$show_item_discounts = count(array_filter($items, static fn ($item) => (float) ($item->item_discount ?? 0) != 0)) > 0;
$slate_columns       = $show_item_discounts ? 5 : 4;
$slate_notes         = (string) ($slate_invoice ? ($document->invoice_terms ?? '') : ($document->notes ?? ''));
$slate_footer_text   = sanitize_pdf_footer_content(get_setting($slate_invoice ? 'pdf_invoice_footer' : 'pdf_quote_footer') ?: '');
?><!DOCTYPE html>
<html lang="<?php echo html_escape(trans('cldr')); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($slate_title . ' ' . $slate_number . ' - ' . $slate_company); ?></title>
    <?php if ($slate_pdf) { ?>
    <style>
        <?php readfile(FCPATH . 'assets/core/css/slate.css'); ?>
        @page { sheet-size: Letter; margin: 49mm 0mm 43mm 0mm; margin-header: 0mm; margin-footer: 8mm; header: html_slateHeader; footer: html_slateFooter; }
        body { margin: 0; font-family: dejavusans; font-size: 10pt; color: #50585c; line-height: 1.4; }
        table { width: 100%; border-collapse: collapse; }
        .slate-content { margin: 0 7.5mm; }
        .slate-footer { margin: 0 7.5mm; }
        .slate-banner td { padding: 37pt 21pt; }
        .slate-items { overflow: wrap; }
        .slate-items thead { display: table-header-group; }
        .slate-qr { page-break-inside: avoid; }
    </style>
    <?php } else { ?>
    <link rel="stylesheet" href="<?php echo html_escape(base_url('assets/core/css/slate.css')); ?>">
    <link rel="stylesheet" href="<?php echo html_escape(base_url('assets/core/css/slate-web.css')); ?>">
    <?php } ?>
</head>
<body class="slate-document">
<?php if ($slate_pdf) { ?>
    <htmlpageheader name="slateHeader"><?php include __DIR__ . '/header.php'; ?></htmlpageheader>
    <htmlpagefooter name="slateFooter"><?php include __DIR__ . '/footer.php'; ?></htmlpagefooter>
<?php } else { ?>
<div class="slate-shell">
    <?php include __DIR__ . '/actions.php'; ?>
    <?php if ( ! empty($flash_message)) { ?><div class="slate-alert" role="status"><?php echo html_escape($flash_message); ?></div><?php } ?>
    <article class="slate-paper">
        <?php include __DIR__ . '/header.php'; ?>
<?php } ?>
<main class="slate-content">
    <?php if ( ! empty($document->property_incomplete)) { ?><div class="slate-incomplete"><?php echo html_escape(trans('slate_incomplete')); ?></div><?php } ?>
    <?php include __DIR__ . '/body.php'; ?>
</main>
<?php if ( ! $slate_pdf) { ?>
        <?php include __DIR__ . '/footer.php'; ?>
    </article>
</div>
<?php } ?>
</body>
</html>
