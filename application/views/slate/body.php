<?php defined('BASEPATH') || exit('No direct script access allowed'); ?>
<table class="slate-intro" role="presentation">
    <tr>
        <td class="slate-customer">
            <div class="slate-label"><?php echo html_escape(trans('slate_bill_to')); ?></div>
            <strong><?php echo html_escape(format_client($document)); ?></strong><br>
            <?php if ( ! empty($document->client_contact_name)) { ?><?php echo html_escape($document->client_contact_name); ?><br><?php } ?>
            <?php foreach (slate_address($document, 'client') as $line) {
                echo html_escape($line) . '<br>';
            } ?>
            <?php foreach (['vat_id', 'tax_code'] as $field) {
                if ( ! empty($document->{'client_' . $field})) {
                    echo html_escape(trans($field . '_short') . ': ' . $document->{'client_' . $field}) . '<br>';
                }
            } ?>
            <div class="slate-customer-contact">
                <?php if ( ! empty($document->client_phone)) {
                    echo html_escape($document->client_phone) . '<br>';
                } ?>
                <?php if ( ! empty($document->client_email)) {
                    echo html_escape($document->client_email);
                } ?>
            </div>
        </td>
        <td class="slate-details">
            <table>
                <tr><th scope="row"><?php echo html_escape(trans($slate_invoice ? 'slate_invoice_number' : 'slate_estimate_number')); ?>:</th><td><?php echo html_escape($slate_number); ?></td></tr>
                <tr><th scope="row"><?php echo html_escape(trans($slate_invoice ? 'invoice_date' : 'slate_estimate_date')); ?>:</th><td><?php echo html_escape(date_from_mysql($document->{$slate_type . '_date_created'}, true)); ?></td></tr>
                <tr><th scope="row"><?php echo html_escape(trans($slate_invoice ? 'slate_payment_due' : 'expires')); ?>:</th><td><?php echo html_escape(date_from_mysql($document->{$slate_invoice ? 'invoice_date_due' : 'quote_date_expires'}, true)); ?></td></tr>
                <tr><th scope="row"><?php echo html_escape($slate_amount_label); ?>:</th><td><strong><?php echo format_currency($slate_amount); ?></strong></td></tr>
                <?php if ($slate_invoice && ! empty($payment_method->payment_method_name)) { ?>
                <tr><th scope="row"><?php echo html_escape(trans('payment_method')); ?>:</th><td><?php echo html_escape($payment_method->payment_method_name); ?></td></tr>
                <?php } ?>
            </table>
        </td>
    </tr>
</table>
<div class="slate-scroll">
<table class="slate-items" data-columns="<?php echo $slate_columns; ?>" autosize="1">
    <thead><tr>
        <th scope="col" class="slate-service" style="width:<?php echo $show_item_discounts ? '44' : '50'; ?>%"><?php echo html_escape(trans('slate_services')); ?></th>
        <th scope="col" style="width:12%"><?php echo html_escape(trans('slate_quantity')); ?></th>
        <th scope="col" style="width:<?php echo $show_item_discounts ? '15' : '19'; ?>%"><?php echo html_escape(trans('price')); ?></th>
        <?php if ($show_item_discounts) { ?><th scope="col" style="width:14%"><?php echo html_escape(trans('discount')); ?></th><?php } ?>
        <th scope="col" style="width:<?php echo $show_item_discounts ? '15' : '19'; ?>%"><?php echo html_escape(trans('amount')); ?></th>
    </tr></thead>
    <tbody>
    <?php $slate_row_index = 0;
foreach ($items as $item) {
    $slate_shading                            = $slate_row_index++ % 2 === 0 ? 'slate-shaded' : '';
    [$service_description, $service_location] = slate_service_details($item, $property_render);
    foreach (slate_description_chunks($service_description, $slate_pdf) as $chunk_index => $description) { ?>
        <tr class="<?php echo $slate_shading . ($chunk_index ? ' slate-continuation' : ''); ?>">
            <td class="slate-service"><strong><?php echo html_escape($chunk_index ? trans('slate_continued') : $item->item_name); ?></strong><div class="slate-description"><?php echo nl2br(html_escape($description)); ?></div><?php if ($service_location !== '' && $chunk_index === 0) { ?><div class="slate-location"><?php echo html_escape(trans('slate_location') . ': ' . $service_location); ?></div><?php } ?></td>
            <td class="slate-number" data-label="<?php echo html_escape(trans('slate_quantity')); ?>"><?php if ( ! $chunk_index) {
                echo html_escape(format_quantity($item->item_quantity) ?? '0'); ?><?php if ( ! empty($item->item_product_unit)) { ?><br><span class="slate-unit"><?php echo html_escape($item->item_product_unit); ?></span><?php }
                } ?></td>
            <td class="slate-number" data-label="<?php echo html_escape(trans('price')); ?>"><?php if ( ! $chunk_index) {
                echo format_currency($item->item_price);
            } ?></td>
            <?php if ($show_item_discounts) { ?><td class="slate-number" data-label="<?php echo html_escape(trans('discount')); ?>"><?php if ( ! $chunk_index) {
                echo format_currency($item->item_discount);
            } ?></td><?php } ?>
            <td class="slate-number" data-label="<?php echo html_escape(trans('amount')); ?>"><?php if ( ! $chunk_index) {
                echo format_currency($slate_pdf ? $item->item_total : $item->item_subtotal - $item->item_discount);
            } ?></td>
        </tr>
    <?php }
    } ?>
    </tbody>
</table>
</div>
<table class="slate-summary" autosize="1"<?php if (count($slate_rows) + count($slate_payments) < 9) { ?> style="page-break-inside: avoid"<?php } ?>>
    <?php foreach ($slate_rows as $row) { ?>
    <tr class="<?php echo ($row[2] ?? '') === 'total' ? 'slate-total' : ''; ?>"><td class="slate-summary-label"><?php echo html_escape($row[0]); ?>:</td><td class="slate-summary-amount"><?php echo $row[1]; ?></td></tr>
    <?php } ?>
    <?php foreach ($slate_payments as $payment) {
        $label = sprintf(trans('slate_payment_on'), date_from_mysql($payment->payment_date, true));
        if ( ! empty($payment->payment_method_name)) {
            $label .= ' ' . sprintf(trans('slate_payment_using'), $payment->payment_method_name);
        } ?>
    <tr><td class="slate-summary-label"><?php echo html_escape($label); ?>:</td><td class="slate-summary-amount"><?php echo format_currency($payment->payment_amount); ?></td></tr>
    <?php } ?>
    <?php if ($slate_invoice) { ?>
    <tr class="slate-balance"><td class="slate-summary-label"><?php echo html_escape($slate_amount_label); ?>:</td><td class="slate-summary-amount"><?php echo format_currency($slate_amount); ?></td></tr>
    <?php } ?>
</table>
<?php if ($slate_invoice && get_setting('qr_code') && $document->invoice_balance > 0 && $document->invoice_balance < 10e9) { ?>
<table class="slate-qr" role="presentation"><tr><td>
    <?php foreach (['recipient' => $document->user_company ?? '', 'iban' => $document->user_iban ?? '', 'bic' => $document->user_bic ?? ''] as $field => $value) { ?>
    <div><strong><?php echo html_escape(trans('qr_code_settings_' . $field)); ?>:</strong> <?php echo html_escape($value ?: get_setting('qr_code_' . $field)); ?></div>
    <?php } ?>
    <div><strong><?php echo html_escape(trans('qr_code_settings_remittance_text')); ?>:</strong> <?php echo html_escape(parse_template($document, ($document->user_remittance_text ?? '') ?: get_setting('qr_code_remittance_text'))); ?></div>
</td><td class="slate-number"><?php echo invoice_qrcode((int) $document->invoice_id); ?></td></tr></table>
<?php } ?>
<?php if ($slate_notes !== '') { ?>
<section class="slate-notes"<?php if (mb_strlen($slate_notes) < 900 && preg_match_all('/\R/u', $slate_notes) < 8) { ?> style="page-break-inside: avoid"<?php } ?>>
    <h2><?php echo html_escape(trans('slate_notes_terms')); ?></h2>
    <?php foreach (preg_split('/\R\s*\R/u', $slate_notes) as $paragraph) { ?>
    <div class="slate-note-paragraph"<?php if (mb_strlen($paragraph) < 900 && preg_match_all('/\R/u', $paragraph) < 8) { ?> style="page-break-inside: avoid"<?php } ?>><?php echo nl2br(html_escape($paragraph)); ?></div>
    <?php } ?>
</section>
<?php } ?>
<?php if ($slate_invoice) { ?><div class="slate-thank-you"><?php echo html_escape(trans('slate_thank_you')); ?></div><?php } ?>
<?php if ($slate_footer_text !== '') { ?><div class="slate-extra-footer"><?php echo $slate_footer_text; ?></div><?php } ?>
<?php if ( ! $slate_pdf && ! empty($attachments)) { ?>
<section class="slate-attachments">
    <h2><?php echo html_escape(trans('attachments')); ?></h2>
    <ul><?php foreach ($attachments as $attachment) { ?>
        <li><a href="<?php echo html_escape(site_url('guest/get/get_file/' . rawurlencode($attachment['fullname']))); ?>"><?php echo html_escape(html_entity_decode($attachment['name'], ENT_QUOTES, 'UTF-8')); ?></a></li>
    <?php } ?></ul>
</section>
<?php } ?>
