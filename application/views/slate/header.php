<?php defined('BASEPATH') || exit('No direct script access allowed'); ?>
<table class="slate-banner" role="presentation">
    <tr>
        <td class="slate-heading">
            <div class="slate-title"><?php echo html_escape(mb_strtoupper($slate_title)); ?></div>
            <div class="slate-subtitle"><?php echo html_escape(trans('slate_professional_services')); ?></div>
        </td>
        <td class="slate-highlight">
            <div class="slate-highlight-label"><?php echo html_escape($slate_amount_label); ?></div>
            <div class="slate-highlight-value"><?php echo format_currency($slate_amount); ?></div>
        </td>
    </tr>
</table>
