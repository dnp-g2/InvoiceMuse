<?php
$property_id    = (int) $property_document->{$property_type . '_id'};
$property_state = service_properties()->state($property_type, $property_id);
if ( ! $property_state) {
    return;
}
$property_list     = service_properties()->properties((int) $property_document->client_id);
$property_selected = [];
foreach ($items as $item) {
    $property_selected[(int) $item->item_id] = (int) ($item->item_service_property_id ?? 0);
}
$property_frozen = $property_state->published || (int) $property_document->{$property_type . '_status_id'} !== 1;
$property_issues = service_properties()->problems($property_type, $property_id);
?>
<div class="panel panel-default" style="margin:12px" id="property-toolbar"><div class="panel-body">
<?php if ($property_frozen) { ?><p>These document addresses are locked. Copy to a new draft to change service properties.</p><?php } ?>
<strong>Service properties</strong> — each charge belongs to a property; billing stays with this customer.
<a href="<?php echo site_url('service-properties/client/' . (int) $property_document->client_id); ?>" target="_blank">Manage properties</a>
<a class="btn btn-default btn-sm" href="<?php echo site_url('service-properties/preview/' . $property_type . '/' . $property_id); ?>" target="_blank">Preview draft</a>
<?php if ( ! $property_frozen) { ?><div style="margin-top:8px"><select id="property-assign" class="form-control" style="display:inline-block;max-width:420px"><option value="">Select property for unassigned lines</option>
<?php foreach ($property_list as $p) {
    if ( ! $p['active']) {
        continue;
    } ?><option value="<?php echo (int) $p['property_id']; ?>"><?php echo html_escape(Property_rules::text($p)); ?></option><?php } ?></select>
<button type="button" class="btn btn-default" id="property-apply">Assign to unassigned lines</button>
<label style="margin-left:12px"><input type="checkbox" id="property-refresh"> Refresh saved billing and property addresses on next save</label></div><?php } ?>
<?php if ($property_issues) { ?><div class="alert alert-warning" style="margin:8px 0 0"><?php echo html_escape(implode(' ', $property_issues)); ?></div><?php } ?>
</div></div>
<script>
window.propertyRevision=<?php echo (int) $property_state->revision; ?>;
$(function(){
 $(document).ajaxComplete(function(event,xhr){
   const token=xhr.getResponseHeader('X-CSRF-Token');
   if(token){
     csrf_token_value=token;
     $('meta[name="csrf_token_value"]').attr('content',token);
     $('input[name="'+csrf_token_name+'"]').val(token);
   }
 });
 const properties=<?php echo json_encode($property_list, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
 const selected=<?php echo json_encode($property_selected, JSON_FORCE_OBJECT); ?>;
 const frozen=<?php echo $property_frozen ? 'true' : 'false'; ?>;
 const active=properties.filter(p=>Number(p.active)===1);
 function addSelectors(){
  $('#item_table .item').each(function(){
   const row=$(this);if(row.find('[name=item_service_property_id]').length)return;
   const id=row.find('[name=item_id]').val();
   const value=selected[id] || (active.length===1?active[0].property_id:'');
   const select=$('<select name="item_service_property_id" class="form-control">').append($('<option>').val('').text('Select service property'));
   properties.forEach(p=>{ if(Number(p.active)===1 || Number(p.property_id)===Number(value)) select.append($('<option>').val(p.property_id).text([p.label,p.address_1,p.address_2,p.city,p.state,p.zip].filter(Boolean).join(', ')+(Number(p.active)?'':' (archived)'))); });
   select.val(String(value));if(frozen)select.prop('disabled',true);
   const label=$('<label style="display:block;margin-bottom:10px">').text('Service property').append(select);
   select.css({maxWidth:'800px',display:'inline-block',marginLeft:'12px'});
   label.css({marginBottom:0});
   row.prepend($('<tr class="property-selection-row" style="background:#eef3ef">').append($('<td>').attr('colspan',row.children('tr').first().children('td').length).append(label)));
   const mark=()=>select.css('border-color',select.val()?'':'#b45309');select.on('change',mark);mark();
  });
 }
 addSelectors();const table=document.getElementById('item_table');if(table)new MutationObserver(addSelectors).observe(table,{childList:true,subtree:true});
 $('#property-apply').on('click',function(){const v=$('#property-assign').val();if(v)$('#item_table .item [name=item_service_property_id]').filter(function(){return !$(this).val();}).val(v).trigger('change');});
});
</script>
