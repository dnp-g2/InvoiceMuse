<?php
defined('BASEPATH') || exit('No direct script access allowed');
$slate_key           = $slate_invoice ? $invoice_url_key : $quote_url_key;
$slate_download_type = $slate_invoice ? (empty($document->sumex_id) ? 'invoice' : 'sumex') : 'quote';
$slate_download      = 'guest/view/generate_' . $slate_download_type . '_pdf/' . rawurlencode($slate_key) . '/1/Slate';
$slate_user_type     = (int) $ci->session->userdata('user_type');
$slate_user_id       = $ci->session->userdata('user_id');
?>
<nav class="slate-toolbar" aria-label="<?php echo html_escape(trans('slate_document_actions')); ?>">
    <?php if ($slate_user_id && $slate_user_type) { ?>
    <a class="slate-button" href="<?php echo html_escape(site_url($slate_user_type > 1 ? 'guest' : '')); ?>"><?php echo html_escape(trans('dashboard')); ?></a>
    <?php } ?>
    <a class="slate-button slate-button-primary" href="<?php echo html_escape(site_url($slate_download)); ?>"><?php echo html_escape(trans('download_pdf')); ?></a>
    <?php if ($slate_invoice && get_setting('enable_online_payments') == 1 && $document->invoice_balance > 0) { ?>
    <a class="slate-button slate-button-pay" href="<?php echo html_escape(site_url('guest/payment_information/form/' . rawurlencode($slate_key))); ?>"><?php echo html_escape(trans('pay_now')); ?></a>
    <?php } ?>
    <?php if ( ! $slate_invoice && $slate_user_id && $slate_user_type === 2 && in_array((int) $document->quote_status_id, [2, 3], true)) { ?>
    <form method="post" action="<?php echo html_escape(site_url('guest/view/approve_quote/' . rawurlencode($slate_key))); ?>">
        <?php _csrf_field(); ?>
        <button type="submit" class="slate-button slate-button-pay"><?php echo html_escape(trans('slate_approve_estimate')); ?></button>
    </form>
    <form method="post" action="<?php echo html_escape(site_url('guest/view/reject_quote/' . rawurlencode($slate_key))); ?>">
        <?php _csrf_field(); ?>
        <button type="submit" class="slate-button"><?php echo html_escape(trans('slate_reject_estimate')); ?></button>
    </form>
    <?php } ?>
</nav>
