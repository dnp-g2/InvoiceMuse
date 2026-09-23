<?php
$l = $listing;
$url = static function ($status, $offset = 0) use ($l) {
    return site_url('clients/status/' . $status . '/' . $offset) . '?' . http_build_query(['q' => $l['query'], 'per_page' => $l['size']]);
};
$first = $l['total'] ? $l['offset'] + 1 : 0;
$last = min($l['offset'] + count($records), $l['total']);
$pageNumber = (int) floor($l['offset'] / $l['size']) + 1;
$pageCount = max(1, (int) ceil($l['total'] / $l['size']));
$summary = 'Showing ' . $first . '–' . $last . ' of ' . $l['total'] . ($l['query'] !== '' ? ' matching' : '') . ($l['status'] === 'all' ? '' : ' ' . $l['status']) . ' customers';
$phoneText = static function ($value) {
    $digits = preg_replace('/\D/', '', $value);
    if (strlen($digits) === 11 && $digits[0] === '1') { $digits = substr($digits, 1); }
    return strlen($digits) === 10 ? '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6) : $value;
};
?>
<style>
.customer-directory{max-width:1320px;margin:auto;padding:24px;color:#303a43}.directory-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px}.directory-heading h2{font-size:28px;margin:0 0 6px}.directory-muted{color:#5f6971}.directory-panel{background:#fff;border:1px solid #dce2e6;border-radius:8px;padding:20px;margin-bottom:20px}.directory-filters{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px}.directory-filters a{padding:10px 16px;border:1px solid #d4dce2;border-radius:5px;color:#344451;background:#fff;text-decoration:none}.directory-filters a[aria-current=page]{background:#eaf4fc;border-color:#2c80bb;color:#165983;font-weight:600}.directory-search{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}.directory-search label{display:block;font-weight:600;margin-bottom:6px}.directory-search-field{flex:1;min-width:220px}.directory-search .form-control{height:40px}.directory-search .btn{min-height:40px}.directory-clear{padding:10px 0}.directory-pagination{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin:18px 0}.directory-summary{font-size:15px;font-weight:600;margin:0}.directory-page-controls{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.directory-page-controls .btn{min-height:36px}.directory-page-position{margin-right:8px;color:#5f6971}.directory-table{width:100%;table-layout:fixed;background:#fff;border:1px solid #dce2e6}.directory-table th{background:#f1f5f8;font-size:13px;color:#4a5863;padding:13px 14px;border-bottom:1px solid #dce2e6}.directory-table td{padding:17px 14px;border-bottom:1px solid #e3e8eb;vertical-align:top;overflow-wrap:anywhere;font-size:14px}.directory-name{display:block;font-size:16px;font-weight:600;margin-bottom:8px}.directory-status{display:inline-block;background:#edf4ef;color:#275b34;border-radius:12px;padding:3px 9px;font-size:12px}.directory-status.inactive{background:#eee;color:#555}.directory-contact a{display:block;margin-bottom:5px}.directory-contact .directory-muted{display:block}.directory-properties a{display:block;font-weight:600;margin-bottom:5px}.directory-property-address{display:block;margin-top:3px}.directory-balance{font-weight:600;white-space:nowrap}.directory-actions{display:flex;gap:6px;flex-wrap:wrap}.directory-actions .dropdown-menu{left:auto;right:0}.directory-actions .btn{min-height:34px}.directory-empty{text-align:center;padding:44px 16px;background:#fff;border:1px solid #dce2e6;border-radius:8px}.directory-empty h3{font-size:20px}.directory-note{margin-top:12px;font-size:13px;color:#5f6971}.directory-directory-label{display:none}.directory-einvoice{display:block;font-size:12px;margin-top:8px}.customer-directory a:focus-visible,.customer-directory button:focus-visible,.customer-directory input:focus-visible,.customer-directory select:focus-visible{outline:3px solid #215f93;outline-offset:3px}
@media(max-width:1000px){.customer-directory{padding:18px}.directory-table th,.directory-table td{padding:12px 9px}.directory-actions .btn{padding:6px 9px}}
@media(max-width:800px){.customer-directory{padding:14px}.directory-heading{align-items:flex-start}.directory-heading h2{font-size:25px}.directory-panel{padding:16px}.directory-table,.directory-table tbody,.directory-table tr,.directory-table td{display:block;width:100%}.directory-table{border:0;background:transparent}.directory-table thead{display:none}.directory-table tr{background:#fff;border:1px solid #dce2e6;border-radius:8px;margin-bottom:14px;padding:6px 16px}.directory-table td{border:0;padding:9px 0}.directory-table td:before{content:attr(data-label);display:block;font-size:12px;color:#5f6971;margin-bottom:4px}.directory-table td:first-child:before{display:none}.directory-name{display:inline-block;margin-right:10px}.directory-actions .btn,.directory-page-controls .btn{min-height:42px}.directory-search-field{min-width:100%}.directory-pagination{align-items:flex-start;flex-direction:column}.directory-page-position{width:100%;margin:0 0 4px}.directory-heading .btn{white-space:normal}.directory-filters a{flex:1;text-align:center;padding:10px 8px}}
</style>
<div id="headerbar"><h1 class="headerbar-title">Customers</h1></div>
<div id="content" class="no-padding"><main class="customer-directory">
<?php $this->layout->load_view('layout/alerts'); ?>
<header class="directory-heading"><div><h2>Customers</h2><p class="directory-muted">Find a customer, see their properties, and manage their account.</p></div><a class="btn btn-primary" href="<?php echo site_url('clients/form'); ?>">+ Add customer</a></header>
<section class="directory-panel" aria-label="Find customers">
<nav class="directory-filters" aria-label="Customer status">
<?php foreach (['active'=>'Active','inactive'=>'Inactive','all'=>'All'] as $status=>$label) { ?>
<a href="<?php echo html_escape($url($status)); ?>" <?php echo $l['status'] === $status ? 'aria-current="page"' : ''; ?>><?php echo $label; ?> (<?php echo (int)$l['counts'][$status]; ?>)</a>
<?php } ?></nav>
<form method="get" action="<?php echo site_url('clients/status/' . $l['status']); ?>" class="directory-search">
<div class="directory-search-field"><label for="customer-search">Search customers</label><input class="form-control" id="customer-search" name="q" type="search" maxlength="250" value="<?php echo html_escape($l['query']); ?>" placeholder="Name, email, phone, or address"></div>
<div><label for="customer-page-size">Customers per page</label><select class="form-control" id="customer-page-size" name="per_page"><?php foreach ([25,50,100] as $size) { ?><option value="<?php echo $size; ?>" <?php echo $l['size']===$size?'selected':''; ?>><?php echo $size; ?></option><?php } ?></select></div>
<button class="btn btn-primary" type="submit">Search</button>
<?php if ($l['query'] !== '') { ?><a class="directory-clear" href="<?php echo html_escape(site_url('clients/status/' . $l['status']) . '?per_page=' . $l['size']); ?>">Clear search</a><?php } ?>
</form>
</section>
<?php include __DIR__ . '/partial_directory_pager.php'; ?>
<?php if (!$records) { ?>
<section class="directory-empty"><h3><?php echo $l['query']!==''?'No customers found':'No ' . ($l['status']==='all'?'':$l['status'].' ') . 'customers yet'; ?></h3><p class="directory-muted"><?php echo $l['query']!==''?'Try another name, phone number, or address, or clear your search.':'Choose another status filter or add a customer.'; ?></p></section>
<?php } else { ?>
<table class="directory-table"><caption class="sr-only"><?php echo html_escape($summary); ?></caption><thead><tr><th style="width:20%" scope="col">Customer</th><th style="width:24%" scope="col">Contact details</th><th style="width:25%" scope="col">Service properties</th><th style="width:12%" scope="col">Balance</th><th style="width:19%" scope="col">Actions</th></tr></thead><tbody>
<?php foreach ($records as $client) {
$cid=(int)$client->client_id; $properties=$l['properties'][$cid]??[];
$phone=trim((string)($client->client_phone ?: $client->client_mobile));
?>
<tr data-customer-id="<?php echo $cid; ?>">
<td data-label="Customer"><a class="directory-name" href="<?php echo site_url('clients/view/' . $cid); ?>"><?php _htmlsc(format_client($client)); ?></a><span class="directory-status <?php echo $client->client_active?'':'inactive'; ?>"><?php echo $client->client_active?'Active':'Inactive'; ?></span>
<?php if ($einvoicing) { ?><span class="directory-einvoice">E-invoicing: <?php echo ($client->client_einvoicing_active??0)?'Active':'Inactive'; ?> <?php _htmlsc($client->client_einvoicing_version??''); ?></span><?php } ?></td>
<td data-label="Contact details" class="directory-contact"><?php if (trim((string)$client->client_email)!=='') { ?><a href="mailto:<?php echo html_escape($client->client_email); ?>"><?php _htmlsc($client->client_email); ?></a><?php } else { ?><span class="directory-muted">No email added</span><?php } ?>
<?php if ($phone!=='') { ?><a href="tel:<?php echo html_escape(preg_replace('/[^+0-9]/','',$phone)); ?>"><?php echo html_escape($phoneText($phone)); ?></a><?php } else { ?><span class="directory-muted">No phone added</span><?php } ?></td>
<td data-label="Service properties" class="directory-properties"><?php if ($properties) { ?><a href="<?php echo site_url('clients/view/' . $cid) . '#customer-properties'; ?>"><?php echo count($properties) . (count($properties)===1?' service property':' service properties'); ?></a><?php foreach (array_slice($properties,0,2) as $property) { ?><span class="directory-property-address"><?php echo html_escape($property->address_1 . ($property->address_2 ? ', ' . $property->address_2 : '')); ?></span><?php } if (count($properties)>2) { ?><span class="directory-muted">+<?php echo count($properties)-2; ?> more</span><?php } } else { ?><span class="directory-muted">No service properties added</span><?php } ?></td>
<td data-label="Balance"><span class="directory-balance"><?php echo format_currency($client->client_invoice_balance); ?></span></td>
<td data-label="Actions"><div class="directory-actions"><a class="btn btn-default btn-sm" href="<?php echo site_url('clients/view/' . $cid); ?>" aria-label="View <?php echo html_escape(format_client($client)); ?>">View</a><a class="btn btn-default btn-sm" href="<?php echo site_url('clients/form/' . $cid); ?>" aria-label="Edit <?php echo html_escape(format_client($client)); ?>">Edit</a>
<div class="btn-group"><button class="btn btn-default btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">More actions <span class="caret"></span></button><ul class="dropdown-menu">
<li><a href="#" class="client-create-invoice" data-client-id="<?php echo $cid; ?>">Create invoice</a></li><li><a href="#" class="client-create-quote" data-client-id="<?php echo $cid; ?>">Create quote</a></li><li role="separator" class="divider"></li>
<li><form action="<?php echo site_url('clients/delete/' . $cid); ?>" method="POST"><?php _csrf_field(); ?><button type="submit" class="dropdown-button" onclick="return confirm(<?php echo html_escape(json_encode(trans('delete_client_warning'))); ?>);">Delete customer</button></form></li>
</ul></div></div></td></tr>
<?php } ?></tbody></table>
<?php } ?>
<p class="directory-note">Balances include draft invoices. Review a customer’s invoices before collecting payment.</p>
<?php include __DIR__ . '/partial_directory_pager.php'; ?>
</main></div>
<script>$(function(){ $('#customer-page-size').on('change',function(){this.form.submit();}); });</script>
