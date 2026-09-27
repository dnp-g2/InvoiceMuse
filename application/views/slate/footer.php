<?php defined('BASEPATH') || exit('No direct script access allowed'); ?>
<div class="slate-footer">
    <table role="presentation">
        <tr>
            <td class="slate-footer-logo"><?php echo $slate_logo; ?></td>
            <td class="slate-footer-company">
                <strong><?php echo html_escape($slate_company); ?></strong><br>
                <?php foreach (slate_address($document, 'user') as $line) {
                    echo html_escape($line) . '<br>';
                } ?>
                <?php foreach (['vat_id', 'tax_code'] as $field) {
                    if ( ! empty($document->{'user_' . $field})) {
                        echo html_escape(trans($field . '_short') . ': ' . $document->{'user_' . $field}) . '<br>';
                    }
                } ?>
            </td>
            <td class="slate-footer-contact">
                <?php if ( ! empty($document->user_phone) || ! empty($document->user_email)) { ?>
                <strong><?php echo html_escape(trans('slate_contact_information')); ?></strong><br>
                <?php } ?>
                <?php if ( ! empty($document->user_phone)) {
                    echo html_escape($document->user_phone) . '<br>';
                } ?>
                <?php if ( ! empty($document->user_email)) {
                    echo html_escape($document->user_email);
                } ?>
            </td>
        </tr>
    </table>
    <?php if ($slate_pdf) { ?>
    <div class="slate-pagination"><?php echo html_escape(sprintf(trans('slate_page_number'), '{PAGENO}', '{nbpg}', mb_strtoupper($slate_title), $slate_number)); ?></div>
    <?php } ?>
</div>
