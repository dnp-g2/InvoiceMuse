<script>
$(function(){
 $('.property-workspace').each(function(){
  const root=$(this);let busy=false;
  function closeEditors(){root.find('.property-editor').each(function(){this.hidden=true;const f=this.querySelector('form');if(f)f.reset();});root.find('.property-open').attr('aria-expanded','false');}
  function token(xhr,r){const t=xhr.getResponseHeader('X-CSRF-Token')||(r&&r.new_token);if(t){csrf_token_value=t;$('meta[name="csrf_token_value"]').attr('content',t);$('input[name="'+csrf_token_name+'"]').val(t);}}
  root.on('click','.property-open',function(){if(busy)return;root.find('.property-feedback').empty().attr('class','property-feedback');closeEditors();const e=document.getElementById(this.dataset.target);e.hidden=false;$(this).attr('aria-expanded','true');e.querySelector('input:not([type=hidden]),select').focus();});
  root.on('click','.property-cancel',function(){if(busy)return;root.find('.property-feedback').empty().attr('class','property-feedback');const id=$(this).closest('.property-editor').attr('id');closeEditors();root.find('.property-open[data-target="'+id+'"]').trigger('focus');});
  root.on('submit','form',function(event){
   if($(this).hasClass('property-archive')&&!window.confirm('Archive this property? It will no longer be available for new work. Past invoices and quotes will be preserved.')){event.preventDefault();return;}
   if(root.attr('data-inline')!=='1')return;
   event.preventDefault();if(busy)return;busy=true;
   const form=$(this);const data=form.serializeArray().filter(v=>v.name!==csrf_token_name);data.push({name:'inline_properties',value:'1'});
   const expanded=root.find('.property-archived').prop('open');root.find('button').prop('disabled',true);
   $.ajax({url:form.attr('action'),type:'POST',data:$.param(data),dataType:'json'}).done(function(r,s,xhr){
    token(xhr,r);root.find('.property-feedback').attr('class','property-feedback alert '+(r.success?'alert-success':'alert-danger')).text(r.message);
    if(r.success){root.find('.property-cards').html(r.html);if(expanded)root.find('.property-archived').prop('open',true);root.find('.property-open').attr('aria-expanded','false');}
   }).fail(function(xhr){token(xhr);root.find('.property-feedback').attr('class','property-feedback alert alert-danger').text('Unable to save. Your entries are still here. Please retry or reload if this property changed.');}).always(function(){busy=false;root.find('button').prop('disabled',false);});
  });
  root.find('.property-editor:not([hidden])').each(function(){root.find('.property-open[data-target="'+this.id+'"]').attr('aria-expanded','true');});
 });
});
</script>
