<script>
    // e-invoice user switch anima
    const switch_fa_toggle = function (id){
        const f = $('#'+id);
        f.toggleClass('fa-toggle-on').toggleClass('fa-toggle-off');
    }

    $(function () {
        const client_id = <?php echo $client->client_id; ?>;
        let notesBusy = false;
        function updateNoteToken(xhr, response) {
            const token = xhr.getResponseHeader('X-CSRF-Token') || (response && response.new_token);
            if (token) {
                csrf_token_value = token;
                $('meta[name="csrf_token_value"]').attr('content', token);
                $('input[name="'+csrf_token_name+'"]').val(token);
            }
        }
        function noteError(message) {
            $('#note_action_error').text(message).show();
        }
        function updateNotes(path, payload, submittedText) {
            if (notesBusy) return;
            notesBusy = true;
            $('#note_action_error').hide();
            $('#notes_list button, #save_client_note').prop('disabled', true);
            const expanded = $('#notes_list details').prop('open');
            $.post(path, payload).then(function(data, status, xhr) {
                const response = json_parse(data, <?php echo (int) IP_DEBUG; ?>);
                updateNoteToken(xhr, response);
                if (response.success !== 1) {
                    noteError(response.message || 'Please enter a note and try again.');
                    return;
                }
                if (submittedText !== undefined && $('#client_note').val() === submittedText) $('#client_note').val('');
                return $.post('<?php echo site_url('clients/ajax/load_client_notes'); ?>', {client_id: client_id})
                    .done(function(html, status, xhr) {
                        updateNoteToken(xhr);
                        $('#notes_list').html(html);
                        if (expanded) $('#notes_list details').prop('open', true);
                    }).fail(function(xhr) {updateNoteToken(xhr); noteError('Note saved, but the list could not reload. Refresh this page.');});
            }).fail(function(xhr) {
                updateNoteToken(xhr);
                noteError('Unable to update the note. Refresh the page and try again.');
            }).always(function() {
                notesBusy = false;
                $('#notes_list button, #save_client_note').prop('disabled', false);
            });
        }
        $('#notes_list').on('click', '.archive_client_note', function() {
            updateNotes('<?php echo site_url('clients/ajax/archive_client_note'); ?>', {
                client_id: client_id, client_note_id: $(this).attr('data-id'), action: $(this).attr('data-action')
            });
        });
        $('#save_client_note').click(function() {
            const text = $('#client_note').val();
            updateNotes('<?php echo site_url('clients/ajax/save_client_note'); ?>', {client_id: client_id, client_note: text}, text);
        });
    });
</script>
<?php include APPPATH . 'modules/service_properties/views/styles.php'; ?>
<style>
.customer-page{max-width:1100px;margin:auto;padding:24px}.customer-heading{display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:24px}.customer-heading h2{margin:0 0 8px}.customer-actions{display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap}.customer-status{display:inline-block;font-size:13px;padding:4px 10px;border-radius:12px;background:#edf4ef;color:#275b34}.customer-status.inactive{background:#eee;color:#555}.customer-nav{margin-bottom:24px;display:flex;flex-wrap:wrap}.customer-page .customer-card{border:1px solid #ddd;border-radius:5px;background:#fff;padding:20px;margin-bottom:24px}.customer-page h3{font-size:19px;margin:0 0 16px}.customer-page h2.section-title{font-size:21px;margin:0}.customer-contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}.customer-page address{height:auto;min-height:0;margin:0;line-height:1.6}.customer-data{margin:0}.customer-data dt{font-weight:normal;color:#666;margin-top:12px}.customer-data dt:first-child{margin-top:0}.customer-data dd{margin:3px 0 0;font-size:16px;overflow-wrap:anywhere}.customer-account{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.customer-account .amount{display:block;float:none;text-align:left;font-size:25px;margin-top:8px}.customer-account .balance{color:#245c86}.customer-more summary,.customer-extra summary{cursor:pointer}.customer-more{padding:8px}.customer-more form{margin-top:10px}.customer-extra summary{font-size:18px}.customer-extra dl{margin-top:20px}.customer-extra .row{margin-top:16px}.customer-empty{color:#666}.customer-page .property-card address{min-height:0;height:auto}.customer-page #notes_list .panel-body{font-size:14px;line-height:1.5}.customer-page #notes_list button{min-height:32px}.customer-note-preview{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.customer-note-toggle{margin-top:8px}.customer-page #client_note{width:100%;resize:vertical;margin-bottom:10px}.customer-history{overflow-x:auto}.customer-page .tab-pane{padding:0}.customer-alerts{margin-bottom:20px}@media(max-width:650px){.customer-page{padding:14px}.customer-contact-grid{grid-template-columns:1fr;gap:0}.customer-account{grid-template-columns:1fr}.customer-actions .btn,.customer-page .property-actions .btn{min-height:42px}.customer-page .customer-card{padding:16px}.customer-nav>li>a{padding:12px 10px}.customer-more{width:100%}}
</style>
<div id="headerbar"><h1 class="headerbar-title">Customer</h1></div>
<div id="content" class="no-padding"><main class="customer-page">
<div class="customer-heading"><div><h2><?php _htmlsc(format_client($client)); ?></h2><span class="customer-status <?php echo $client->client_active ? '' : 'inactive'; ?>"><?php echo $client->client_active ? 'Active customer' : 'Inactive customer'; ?></span></div>
<div class="customer-actions">
<a href="#" class="btn btn-primary client-create-invoice" data-client-id="<?php echo (int)$client->client_id; ?>">Create invoice</a>
<a href="#" class="btn btn-default client-create-quote" data-client-id="<?php echo (int)$client->client_id; ?>">Create quote</a>
<a class="btn btn-default" href="<?php echo site_url('clients/form/' . (int)$client->client_id); ?>">Edit customer</a>
<a class="btn btn-default" href="<?php echo site_url('clients'); ?>">Back to customers</a>
<details class="customer-more"><summary>More actions</summary><form action="<?php echo site_url('clients/delete/' . (int)$client->client_id); ?>" method="POST"><?php _csrf_field(); ?><button class="btn btn-danger" type="submit" onclick="return confirm(<?php echo html_escape(json_encode(trans('delete_client_warning'))); ?>);">Delete customer</button></form></details>
</div></div>
<ul class="nav nav-tabs customer-nav">
<?php foreach (['detail'=>['Overview','client-details'],'invoices'=>['Invoices','client-invoices'],'quotes'=>['Quotes','client-quotes'],'payments'=>['Payments','client-payments']] as $key=>[$text,$target]) { ?><li class="<?php echo $activeTab===$key?'active':''; ?>"><a data-toggle="tab" href="#<?php echo $target; ?>"><?php echo $text; ?></a></li><?php } ?>
</ul>
<div class="customer-alerts"><?php $this->layout->load_view('layout/alerts'); ?></div>
<div class="tab-content"><div id="client-details" class="tab-pane <?php echo $activeTab==='detail'?'active':''; ?>">
<div class="customer-contact-grid">
<section class="customer-card"><h3>Contact details</h3><dl class="customer-data">
<?php
$contact_found = false;
foreach (['client_company'=>'Company','client_invoicing_contact'=>'Billing contact','client_email'=>'Email','client_phone'=>'Phone','client_mobile'=>'Mobile'] as $key=>$label) {
    $value=trim((string)$client->$key); if($value==='')continue; $contact_found=true;
?><dt><?php echo $label; ?></dt><dd><?php
if ($key==='client_email') { _auto_link($value,'email'); }
elseif (in_array($key,['client_phone','client_mobile'],true)) {
    $digits=preg_replace('/[^0-9+]/','',$value);$shown=$value;
    if(preg_match('/^(?:\+?1)?([0-9]{3})([0-9]{3})([0-9]{4})$/',$digits,$m))$shown='('.$m[1].') '.$m[2].'-'.$m[3];
    ?><a href="tel:<?php echo html_escape($digits); ?>"><?php echo html_escape($shown); ?></a><?php
} else { echo html_escape($value); }
?></dd><?php } ?></dl><?php if(!$contact_found){ ?><p class="customer-empty">No contact details yet. Use Edit customer to add them.</p><?php } ?></section>
<section class="customer-card"><h3>Billing address</h3><?php if($client->client_address_1||$client->client_city) { ?><address><?php $this->layout->load_view('clients/partial_client_address'); ?></address><?php } else { ?><p class="customer-empty">No billing address yet. Use Edit customer to add it.</p><?php } ?></section>
</div>
<?php if(service_properties()->installed()){ ?>
<section class="customer-card property-workspace" id="customer-properties" data-inline="1">
<div class="property-section-head"><h2 class="section-title">Service properties</h2><button class="btn btn-primary property-open" type="button" data-target="property-new" aria-controls="property-new" aria-expanded="false">Add property</button></div>
<div class="property-feedback" role="status"></div><div class="property-cards"><?php include APPPATH . 'modules/service_properties/views/cards.php'; ?></div>
</section><?php } ?>
<section class="customer-card"><h3>Account summary</h3><div class="customer-account">
<div>Total billed<strong class="amount"><?php echo format_currency($client->client_invoice_total); ?></strong></div>
<div>Total paid<strong class="amount"><?php echo format_currency($client->client_invoice_paid); ?></strong></div>
<div>Balance<strong class="amount balance"><?php echo format_currency($client->client_invoice_balance); ?></strong></div>
</div></section>
<section class="customer-card"><h3>Notes</h3>
<div id="note_action_error" class="alert alert-danger" role="alert" style="display:none"></div><div id="notes_list"><?php echo $partial_notes; ?></div>
<label for="client_note">Add a note</label><textarea id="client_note" class="form-control" rows="3" placeholder="Write useful details about this customer..."></textarea><button id="save_client_note" class="btn btn-default" type="button">Add note</button>
</section>
<?php if($req_einvoicing){ ?><div class="row"><?php include __DIR__ . '/partial_customer_einvoice.php'; ?></div><?php } ?>
<details class="customer-card customer-extra"><summary>Additional details</summary><dl class="customer-data">
<dt>Language</dt><dd><?php echo html_escape(empty($client->client_language)||$client->client_language==='system'?'System default':ucfirst($client->client_language)); ?></dd>
<?php
$extra=['client_web'=>'Website','client_fax'=>'Fax','client_vat_id'=>'VAT ID','client_tax_code'=>'Tax code'];
foreach($extra as $key=>$label){if(trim((string)$client->$key)==='')continue;?><dt><?php echo $label; ?></dt><dd><?php echo html_escape($client->$key); ?></dd><?php }
if(!empty($client->client_birthdate)&&$client->client_birthdate!=='0000-00-00'){ ?><dt>Date of birth</dt><dd><?php echo html_escape(format_date($client->client_birthdate)); ?></dd><?php }
if($client->client_surname!=='' && $client->client_gender!==null && $client->client_gender!==''){ ?><dt>Gender</dt><dd><?php echo html_escape(format_gender($client->client_gender)); ?></dd><?php }
if($this->mdl_settings->setting('sumex')==='1'){
foreach(['client_avs'=>'Social security number','client_insurednumber'=>'Insured number','client_veka'=>'Insurance card number'] as $key=>$label){if(empty($client->$key))continue;?><dt><?php echo $label; ?></dt><dd><?php echo html_escape($client->$key); ?></dd><?php }
}
foreach($custom_fields as $field){$value=$this->mdl_client_custom->form_value('cf_'.$field->custom_field_id);if($value===null||$value==='')continue;?><dt><?php echo html_escape($field->custom_field_label); ?></dt><dd><?php echo html_escape($value); ?></dd><?php }
?></dl></details>
</div>
<?php foreach(['invoice','quote','payment'] as $what){$table=$what.'_table'; ?>
<div id="client-<?php echo $what; ?>s" class="tab-pane customer-history <?php echo $activeTab===$what.'s'?'active':''; ?>">
<div class="clearfix"><?php echo pager(site_url('clients/view/'.(int)$client->client_id.'/'.$what.'s'),'mdl_'.$what.'s'); ?></div>
<?php echo ${$table}; ?></div><?php } ?>
</div></main></div>
<?php include APPPATH . 'modules/service_properties/views/script.php'; ?>
<script>
$(function(){
 function refreshNotePreviews(){ $('#notes_list .customer-note-text').each(function(){const t=$(this);const button=t.siblings('.customer-note-toggle');if(t[0].scrollHeight>t[0].clientHeight+1)button.show();}); }
 $('#notes_list').on('click','.customer-note-toggle',function(){const expanded=$(this).attr('aria-expanded')==='true';$(this).siblings('.customer-note-text').toggleClass('customer-note-preview',expanded);$(this).attr('aria-expanded',!expanded).text(expanded?'Show full note':'Show less');});
 const list=document.getElementById('notes_list');if(list)new MutationObserver(refreshNotePreviews).observe(list,{childList:true,subtree:true});
 $('#notes_list').on('toggle','details',refreshNotePreviews);document.querySelector('#notes_list').addEventListener('toggle',refreshNotePreviews,true);
 $('a[data-toggle="tab"]').on('shown.bs.tab',refreshNotePreviews);$(window).on('resize',refreshNotePreviews);refreshNotePreviews();
 $(document).ajaxComplete(function(e,xhr){const t=xhr.getResponseHeader('X-CSRF-Token');if(t){csrf_token_value=t;$('meta[name="csrf_token_value"]').attr('content',t);$('input[name="'+csrf_token_name+'"]').val(t);}});
});
</script>
