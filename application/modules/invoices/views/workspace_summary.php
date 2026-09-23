<?php if (!$items) { ?><section class="iw-card"><p class="iw-muted"><?php echo $w('empty'); ?></p></section><?php } ?>
<?php foreach ($groups as $group) { if (!$group['items']) continue; ?>
<section class="iw-card iw-property">
<?php if ($state) { ?><header class="iw-property-heading"><span class="iw-eyebrow"><?php echo $w('service_property'); ?></span><h4><?php echo $group['address'] ? html_escape(Property_rules::text($group['address'])) : $w('choose_property'); ?></h4></header><?php } ?>
<div class="iw-summary-head" aria-hidden="true"><span><?php echo $w('service'); ?></span><span><?php _trans('quantity'); ?></span><span><?php echo $w('rate'); ?></span><span><?php _trans('total'); ?></span></div>
<?php foreach ($group['items'] as $line) { ?>
<article class="iw-summary-row"><div><strong><?php echo html_escape($line->item_name); ?></strong><?php if ($line->item_description) { ?><p class="iw-description"><?php echo html_escape($line->item_description); ?></p><?php } ?>
<?php if ((float)$line->item_discount || (float)$line->item_tax_total) { ?><p class="iw-muted iw-adjustments"><?php if ((float)$line->item_discount) { ?><?php _trans('discount'); ?>: <?php echo format_currency($line->item_discount); ?> <?php } ?><?php if ((float)$line->item_tax_total) { ?><?php _trans('tax'); ?>: <?php echo format_currency($line->item_tax_total); ?><?php } ?></p><?php } ?></div>
<div><span class="iw-mobile-label"><?php _trans('quantity'); ?></span><?php echo format_quantity($line->item_quantity); ?><?php if ($line->item_product_unit) { ?><small><?php echo html_escape($line->item_product_unit); ?></small><?php } ?></div><div><span class="iw-mobile-label"><?php echo $w('rate'); ?></span><?php echo format_currency($line->item_price); ?></div><div><span class="iw-mobile-label"><?php _trans('total'); ?></span><strong><?php echo format_currency($line->item_total); ?></strong></div>
</article><?php } ?>
<?php if ($state) { ?><footer class="iw-property-total"><span><?php echo $w('charges_total'); ?></span><strong><?php echo format_currency($group['total']); ?></strong></footer><?php } ?>
</section><?php } ?>
