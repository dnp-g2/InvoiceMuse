<?php
// Standard invoice workspace. SUMEX retains its dedicated editor.
$w = static fn (string $key): string => html_escape(trans('invoice_workspace_' . $key));
$state = service_properties()->state('invoice', (int)$invoice_id);
$properties = $state ? service_properties()->properties((int)$invoice->client_id) : [];
$property_frozen = $state && ($state->published || (int)$invoice->invoice_status_id !== 1);
$issues = $state ? service_properties()->problems('invoice', (int)$invoice_id) : [];
$read_only = $invoice->is_read_only && !$this->config->item('disable_read_only');
$can_edit = !$read_only || (int)$invoice->invoice_status_id !== 4;
$mode = $this->input->get('mode');
$editing = $can_edit && ($mode === 'edit' || ($mode !== 'summary' && (int)$invoice->invoice_status_id === 1));
$can_add = $editing && !$read_only && !$property_frozen;
$disabled = $read_only ? ' disabled' : '';
$billing = clone $invoice;
if ($state) foreach (json_decode($state->billing_snapshot, true) ?: [] as $key => $value) $billing->$key = $value;
$base_url = site_url('invoices/view/' . (int)$invoice_id);
$summary_url = $base_url . '?mode=summary';
$groups = $state ? Property_rules::groups($items) : [['address' => null, 'items' => $items, 'total' => array_sum(array_column($items, 'item_total'))]];
$has_tasks = (bool)array_filter($items, static fn ($line) => !empty($line->item_task_id));
$can_change_customer = (int)$invoice->invoice_status_id === 1 && !$invoice->creditinvoice_parent_id && !$property_frozen && !$read_only && !$has_tasks;
$uploads = $this->mdl_uploads->get_files($invoice->invoice_url_key) ?: [];
$attachment_count = count($uploads);
include __DIR__ . '/workspace_styles.php';
echo $modal_delete_invoice;
if ($legacy_calculation && !$editing && !$read_only) echo $modal_add_invoice_tax;
?>
<div id="headerbar"><h1 class="headerbar-title"><?php _trans('invoice'); ?></h1></div>
<div id="content" class="no-padding"><main class="invoice-workspace" id="invoice-workspace">
<a class="iw-back" href="<?php echo site_url('invoices'); ?>">&larr; <?php echo $w('back'); ?></a>
<header class="iw-heading">
<div><div class="iw-title"><h2><?php _trans('invoice'); ?> <?php echo $invoice->invoice_number ? '#' . html_escape($invoice->invoice_number) : '#' . (int)$invoice_id; ?></h2><span class="iw-status"><?php echo html_escape($invoice_statuses[$invoice->invoice_status_id]['label']); ?></span><?php if ($read_only) { ?><span class="iw-status"><?php _trans('read_only'); ?></span><?php } ?><?php if ($invoice->invoice_is_recurring) { ?><span class="iw-status"><?php _trans('recurring'); ?></span><?php } ?></div>
<p class="iw-customer"><a href="<?php echo site_url('clients/view/' . (int)$invoice->client_id); ?>"><?php echo html_escape(format_client($billing)); ?></a></p>
<p class="iw-muted"><?php _trans('date'); ?>: <?php echo format_date($invoice->invoice_date_created); ?> <span aria-hidden="true">&middot;</span> <?php _trans('due_date'); ?>: <?php echo format_date($invoice->invoice_date_due); ?></p></div>
<div class="iw-actions">
<?php if ($editing) { ?>
<button type="button" class="btn btn-primary" id="btn_save_invoice"><?php echo $w('save'); ?></button><a class="btn btn-default" id="invoice-cancel" href="<?php echo $summary_url; ?>"><?php _trans('cancel'); ?></a>
<?php } else { ?>
<?php if ((int)$invoice->invoice_status_id !== 1 && (float)$invoice->invoice_balance > 0) { ?><button type="button" class="btn btn-primary invoice-add-payment" data-invoice-id="<?php echo (int)$invoice_id; ?>" data-invoice-balance="<?php echo html_escape($invoice->invoice_balance); ?>" data-invoice-payment-method="<?php echo (int)$invoice->payment_method; ?>" data-payment-cf-exist="<?php echo html_escape($payment_cf_exist); ?>"><?php echo $w('record_payment'); ?></button><?php } elseif ((int)$invoice->invoice_status_id === 1 && !$issues) { ?><a class="btn btn-primary" href="<?php echo site_url('mailer/invoice/' . (int)$invoice_id); ?>"><?php echo $w('send'); ?></a><?php } ?>
<?php if ($can_edit) { ?><a class="btn btn-default" href="<?php echo $base_url; ?>?mode=edit"><?php _trans('edit'); ?></a><?php } ?>
<?php if (!$issues) { ?><a class="btn btn-default" id="btn_generate_pdf" target="_blank" rel="noopener" href="<?php echo site_url('invoices/generate_pdf/' . (int)$invoice_id) . '?' . _csrf_query(); ?>"><?php _trans('download_pdf'); ?></a><?php } ?>
<div class="dropdown"><button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true"><?php echo $w('more'); ?> <span class="caret"></span></button><ul class="dropdown-menu dropdown-menu-right">
<?php if (!$issues) { ?><li><a href="<?php echo site_url('mailer/invoice/' . (int)$invoice_id); ?>"><?php _trans('send_email'); ?></a></li><?php } ?>
<?php if ((int)$invoice->invoice_status_id !== 1) { ?><li><button type="button" id="invoice-copy-link"><?php echo $w('copy_link'); ?></button></li><?php } ?>
<?php if ($state && (int)$invoice->invoice_status_id === 1) { ?><li><a target="_blank" rel="noopener" href="<?php echo site_url('service-properties/preview/invoice/' . (int)$invoice_id); ?>"><?php echo $w('preview'); ?></a></li><?php } ?>
<li><button type="button" id="btn_copy_invoice" data-invoice-id="<?php echo (int)$invoice_id; ?>" data-client-id="<?php echo (int)$invoice->client_id; ?>"><?php echo $w('copy_draft'); ?></button></li>
<?php if ((float)$invoice->invoice_balance < 0 || ((int)$invoice->invoice_status_id === 1 && (float)$invoice->invoice_balance !== 0.0)) { ?><li><button type="button" class="invoice-add-payment" data-invoice-id="<?php echo (int)$invoice_id; ?>" data-invoice-balance="<?php echo html_escape($invoice->invoice_balance); ?>" data-invoice-payment-method="<?php echo (int)$invoice->payment_method; ?>" data-payment-cf-exist="<?php echo html_escape($payment_cf_exist); ?>"><?php _trans('enter_payment'); ?></button></li><?php } ?>
<li><button type="button" id="btn_create_recurring"><?php _trans('create_recurring'); ?></button></li>
<li><button type="button" id="btn_create_credit" data-invoice-id="<?php echo (int)$invoice_id; ?>"><?php _trans('create_credit_invoice'); ?></button></li>
<?php if ($einvoice->user) { ?><li><a target="_blank" rel="noopener" href="<?php echo site_url('invoices/generate_xml/' . (int)$invoice_id); ?>"><?php _trans('download_xml'); ?></a></li><?php } ?>
<?php if ($legacy_calculation && !$read_only) { ?><li><a href="#add-invoice-tax" data-toggle="modal"><?php _trans('add_invoice_tax'); ?></a></li><?php } ?>
<?php if ((int)$invoice->invoice_status_id === 1 || ($this->config->item('enable_invoice_deletion') === true && !$read_only)) { ?><li class="divider"></li><li><a href="#delete-invoice" data-toggle="modal"><?php _trans('delete'); ?></a></li><?php } ?>
</ul></div>
<?php } ?>
</div></header>
<?php $this->layout->load_view('layout/alerts'); ?>
<div id="invoice-feedback" role="status" aria-live="polite"></div>
<div id="invoice-errors" class="alert alert-danger" role="alert" hidden></div>
<?php if ($issues) { ?><div class="iw-notice"><?php echo html_escape(implode(' ', $issues)); ?></div><?php } ?>
<?php if ($invoice->creditinvoice_parent_id) { ?><p class="iw-notice"><?php _trans('credit_invoice_for_invoice'); ?> <a href="<?php echo site_url('invoices/view/' . (int)$invoice->creditinvoice_parent_id); ?>"><?php echo html_escape($this->mdl_invoices->get_parent_invoice_number($invoice->creditinvoice_parent_id)); ?></a></p><?php } ?>
<div id="invoice_form" class="iw-layout"><div class="iw-main">
<section class="iw-card iw-billing"><div><h3><?php echo $w('bill_to'); ?></h3><strong><?php echo html_escape(format_client($billing)); ?></strong><address><?php $this->layout->load_view('clients/partial_client_address', ['client' => $billing]); ?></address>
<?php if ($can_change_customer && !$editing) { ?><button type="button" class="btn btn-link" id="invoice_change_client"><?php _trans('change_client'); ?></button><?php } ?>
<?php if ($billing->client_email || $billing->client_phone) { ?><details class="iw-contact"><summary><?php echo $w('contact'); ?></summary><?php if ($billing->client_email) { ?><p><?php _auto_link($billing->client_email, 'email'); ?></p><?php } ?><?php if ($billing->client_phone) { ?><p><?php echo html_escape($billing->client_phone); ?></p><?php } ?></details><?php } ?></div>
<?php if ($editing) { ?><div class="iw-dates">
<label for="invoice_date_created"><?php _trans('date'); ?><input class="form-control datepicker" id="invoice_date_created" value="<?php echo format_date($invoice->invoice_date_created); ?>"<?php echo $disabled; ?>></label>
<label for="invoice_date_due"><?php _trans('due_date'); ?><input class="form-control datepicker" id="invoice_date_due" value="<?php echo format_date($invoice->invoice_date_due); ?>"<?php echo $disabled; ?>></label>
</div><?php } ?>
</section>
<div class="iw-section-heading"><h3><?php echo $w('charges'); ?></h3><?php if ($state) { ?><a href="<?php echo site_url('clients/view/' . (int)$invoice->client_id); ?>#customer-properties" target="_blank" rel="noopener"><?php echo $w('manage_properties'); ?></a><?php } ?></div>
<?php if ($property_frozen) { ?><p class="iw-lock"><i class="fa fa-lock" aria-hidden="true"></i> <?php echo $w('locked'); ?><?php if ($editing) { ?> <a href="<?php echo $summary_url; ?>"><?php echo $w('copy_help'); ?></a><?php } ?></p><?php } ?>
<div id="item_table">
<?php if ($editing) { foreach ($items as $item) { include __DIR__ . '/workspace_item.php'; } } else { include __DIR__ . '/workspace_summary.php'; } ?>
</div>
<?php if ($can_add) { ?>
<?php if ($state) { ?><div class="iw-add-property"><label for="invoice-property-picker"><?php echo $w('add_property'); ?></label><div class="iw-actions"><select class="form-control" id="invoice-property-picker"><option value=""><?php echo $w('choose_property'); ?></option><?php foreach ($properties as $property) { if (!$property['active']) continue; ?><option value="<?php echo (int)$property['property_id']; ?>"><?php echo html_escape(Property_rules::text($property)); ?></option><?php } ?></select><button class="btn btn-default" type="button" id="invoice-add-property"><?php echo $w('add_property'); ?></button></div></div><?php } ?>
<div id="new_row" hidden><?php $item = null; include __DIR__ . '/workspace_item.php'; ?></div>
<?php } ?>
<details class="iw-card iw-extra"><summary><?php _trans('invoice_terms'); ?></summary><?php if ($editing) { ?><label class="sr-only" for="invoice_terms"><?php _trans('invoice_terms'); ?></label><textarea id="invoice_terms" class="form-control" rows="3"<?php echo $disabled; ?>><?php echo html_escape($invoice->invoice_terms); ?></textarea><?php } else { ?><p class="iw-description"><?php echo html_escape($invoice->invoice_terms ?: trans('none')); ?></p><?php } ?></details>
<details class="iw-card iw-extra" id="invoice-attachments"><summary><?php _trans('attachments'); ?> <span class="iw-muted">(<?php echo $attachment_count; ?>)</span></summary><?php if ($editing) { ?><p><?php echo $w('attachments_help'); ?></p><a href="<?php echo $summary_url; ?>#invoice-attachments"><?php echo $w('manage_attachments'); ?></a><?php } elseif ($read_only) { ?>
<?php if (!$uploads) { ?><p class="iw-muted"><?php echo $w('no_attachments'); ?></p><?php } ?>
<?php foreach ($uploads as $upload) { ?><p><a href="<?php echo html_escape(site_url('upload/get_file/' . $invoice->invoice_url_key . '_' . rawurlencode($upload['name']))); ?>"><?php echo html_escape($upload['name']); ?></a></p><?php } ?>
<?php } else { _dropzone_html(false); } ?></details>
<?php include __DIR__ . '/workspace_settings.php'; ?>
</div><aside class="iw-sidebar"><section class="iw-card iw-totals"><span class="iw-eyebrow"><?php echo $w('balance_due'); ?></span><strong class="iw-balance"><?php echo format_currency($invoice->invoice_balance); ?></strong><p class="iw-muted"><?php _trans('due_date'); ?> <?php echo format_date($invoice->invoice_date_due); ?></p><p id="invoice-totals-pending" class="iw-notice" hidden><?php echo $w('totals_pending'); ?></p>
<dl><dt><?php _trans('subtotal'); ?></dt><dd><?php echo format_currency($invoice->invoice_item_subtotal); ?></dd>
<?php if ((float)$invoice->invoice_item_tax_total) { ?><dt><?php _trans('item_tax'); ?></dt><dd><?php echo format_currency($invoice->invoice_item_tax_total); ?></dd><?php } ?>
<?php if ((float)$invoice->invoice_tax_total) { ?><dt><?php _trans('invoice_tax'); ?></dt><dd><?php echo format_currency($invoice->invoice_tax_total); ?></dd><?php } ?>
<?php if ((float)$invoice->invoice_discount_amount || (float)$invoice->invoice_discount_percent) { ?><dt><?php _trans('global_discount'); ?><?php if (!$legacy_calculation) { ?><small><?php echo $w('included'); ?></small><?php } ?></dt><dd><?php echo (float)$invoice->invoice_discount_percent ? format_amount($invoice->invoice_discount_percent) . '%' : format_currency($invoice->invoice_discount_amount); ?></dd><?php } ?>
<dt class="iw-total-line"><?php _trans('total'); ?></dt><dd class="iw-total-line"><?php echo format_currency($invoice->invoice_total); ?></dd><dt><?php _trans('paid'); ?></dt><dd><?php echo format_currency($invoice->invoice_paid); ?></dd></dl>
<?php if (!$editing) { ?><p class="iw-muted"><?php _trans('payment_method'); ?>: <?php $method_label = trans('none'); foreach ($payment_methods as $method) if ($method->payment_method_id == $invoice->payment_method) $method_label = $method->payment_method_name; echo html_escape($method_label); ?></p><?php } ?>
</section>
<?php if ($editing) { ?><p class="iw-muted"><?php echo $w('save_help'); ?></p><?php } ?>
</aside></div></main></div>
<?php include __DIR__ . '/workspace_script.php'; ?>
<?php if (!$editing && !$read_only) _dropzone_script($invoice->invoice_url_key, $invoice->client_id); ?>
