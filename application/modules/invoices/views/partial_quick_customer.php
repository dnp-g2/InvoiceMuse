<style>
#create-invoice .modal-content{display:flex;flex-direction:column;max-height:calc(100vh - 40px);max-height:calc(100dvh - 40px)}
#create-invoice .modal-body{overflow-y:auto;min-height:0}
#create-invoice .modal-header,#create-invoice .modal-footer{flex-shrink:0}
#create-invoice .qc-customer-label{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
#create-invoice .qc-customer-label label{margin:0}
#create-invoice #qc-panel .qc-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 18px}
#create-invoice #qc-panel .qc-wide{grid-column:1/-1}
#create-invoice #qc-panel details{border-top:1px solid #ddd;margin-top:10px;padding-top:12px}
#create-invoice #qc-panel summary{cursor:pointer;font-weight:600;margin-bottom:14px}
#create-invoice #qc-panel .help-block{font-size:13px}
#create-invoice #qc-matches .qc-match{padding:10px 0;border-bottom:1px solid #ddd;display:flex;justify-content:space-between;gap:10px;align-items:center;overflow-wrap:anywhere}
#create-invoice .qc-dropdown-create{display:block;width:100%;text-align:left;padding:10px 12px;border:0;border-top:1px solid #ddd;background:#f0f7ff;color:#176ca3;font-weight:600}
#create-invoice .qc-dropdown-create:focus{outline:2px solid #2185cb;outline-offset:-2px}
#create-invoice .select2-container{max-width:100%}
#create-invoice .qc-customer-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}
@media(max-width:600px){#create-invoice #qc-panel .qc-grid{grid-template-columns:1fr}#create-invoice .qc-customer-actions .btn{flex:1;white-space:normal}#create-invoice #qc-matches .qc-match{align-items:flex-start;flex-direction:column}}
</style>
<div id="qc-panel" style="display:none" aria-labelledby="qc-heading">
    <h4 id="qc-heading">New customer</h4>
    <p>Start with a name. You can add more details later.</p>
    <input type="hidden" id="qc-request" value="<?php echo html_escape($quick_customer_request); ?>">
    <div id="qc-errors" class="alert alert-danger" role="alert" tabindex="-1" style="display:none"></div>
    <div class="qc-grid">
<?php foreach (['full_name' => ['Full name', 'text', 150], 'email' => ['Email (optional)', 'email', 254], 'phone' => ['Phone (optional)', 'tel', 30]] as $field => $definition) { ?>
        <div class="form-group <?php echo $field === 'full_name' ? 'qc-wide' : ''; ?>">
            <label for="qc-<?php echo $field; ?>"><?php echo $definition[0]; ?></label>
            <input class="form-control" id="qc-<?php echo $field; ?>" name="qc_<?php echo $field; ?>" data-qc-field="<?php echo $field; ?>" type="<?php echo $definition[1]; ?>" maxlength="<?php echo $definition[2]; ?>" <?php echo $field === 'full_name' ? 'aria-required="true"' : ''; ?> autocomplete="<?php echo $field === 'full_name' ? 'name' : $definition[1]; ?>">
            <span class="help-block qc-field-error" id="qc-<?php echo $field; ?>-error"></span>
        </div>
<?php } ?>
    </div>
    <details id="qc-addresses"><summary>Service and billing addresses (optional)</summary>
<?php foreach (['service' => 'Service address', 'billing' => 'Billing address'] as $prefix => $title) { ?>
        <div id="qc-<?php echo $prefix; ?>" <?php echo $prefix === 'billing' ? 'style="display:none"' : ''; ?>>
            <h5><?php echo $title; ?></h5>
            <div class="qc-grid">
<?php foreach (['address_1'=>['Street address',150],'address_2'=>['Apartment or unit (optional)',150],'city'=>['City',100],'state'=>['State / province',100],'zip'=>['ZIP / postal code',20]] as $field=>$definition) { $key=$prefix.'_'.$field; ?>
                <div class="form-group <?php echo in_array($field, ['address_1','address_2']) ? 'qc-wide' : ''; ?>">
                    <label for="qc-<?php echo $key; ?>"><?php echo $definition[0]; ?></label>
                    <input class="form-control" type="text" id="qc-<?php echo $key; ?>" data-qc-field="<?php echo $key; ?>" maxlength="<?php echo $definition[1]; ?>">
                    <span class="help-block qc-field-error" id="qc-<?php echo $key; ?>-error"></span>
                </div>
<?php } ?>
                <div class="form-group"><label for="qc-<?php echo $prefix; ?>_country">Country</label>
                    <select class="form-control" id="qc-<?php echo $prefix; ?>_country" data-qc-field="<?php echo $prefix; ?>_country">
<?php foreach ($quick_customer_countries as $code=>$name) { ?><option value="<?php echo html_escape($code); ?>" <?php echo $code === 'US' ? 'selected' : ''; ?>><?php echo html_escape($name); ?></option><?php } ?>
                    </select>
                    <span class="help-block qc-field-error" id="qc-<?php echo $prefix; ?>_country-error"></span>
                </div>
            </div>
        </div>
<?php if ($prefix === 'service') { ?><div class="checkbox"><label><input type="checkbox" id="qc-same-billing" checked> Billing address is the same as the service address</label></div><?php } ?>
<?php } ?>
    </details>
    <div id="qc-matches" class="alert alert-info" style="display:none" role="status">
        <strong>Possible existing customers</strong>
        <p id="qc-match-message"></p><div id="qc-match-list"></div>
        <div class="checkbox"><label><input type="checkbox" id="qc-separate"> This is a different customer. Create a separate account.</label></div>
    </div>
</div>
