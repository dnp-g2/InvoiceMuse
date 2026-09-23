<script>
$(function () {
    var modal = $('#create-invoice'), panel = modal.find('#qc-panel'), picker = modal.find('#create_invoice_client_id');
    var search = '', matchVersion = '', busy = false, focusAfterSave = null;
    var title = modal.find('#modal_create_invoice'), originalTitle = title.text();
    function message(text) { modal.find('#qc-errors').text(text).show().focus(); }
    function resetErrors() {
        modal.find('#qc-errors').hide().empty();
        panel.find('.has-error').removeClass('has-error');
        panel.find('[aria-invalid]').removeAttr('aria-invalid');
        panel.find('.qc-field-error').empty();
    }
    function showInvoice() {
        panel.hide(); modal.find('#invoice-start-fields,.qc-invoice-actions').show();
        modal.find('.qc-customer-actions').hide(); title.text(originalTitle);
        modal.find('#qc-open').focus();
    }
    function openCustomer() {
        picker.select2('close');
        if (!panel.find('#qc-full_name').val()) { panel.find('#qc-full_name').val(search); }
        modal.find('#invoice-start-fields,.qc-invoice-actions,#qc-confirmation').hide();
        panel.show(); modal.find('.qc-customer-actions').show(); title.text('Create new customer');
        panel.find('#qc-full_name').focus();
    }
    function selectCustomer(client, saved) {
        var option = picker.find('option').filter(function () { return this.value === String(client.id); });
        if (!option.length) { picker.append(new Option(client.text, String(client.id), true, true)); }
        picker.val(String(client.id)).trigger('change');
        showInvoice();
        modal.find('#qc-confirmation').text((saved ? 'Customer saved: ' : 'Customer selected: ') + client.text).show();
        modal.find('#invoice_create_confirm').focus();
    }
    function setBusy(value) {
        busy = value;
        modal.find('#qc-save').prop('disabled', value).text(value ? 'Saving customer…' : 'Save customer and continue');
        modal.find('#qc-back,.close').prop('disabled', value);
        panel.find('input:not([type=hidden]),select,button').prop('disabled', value);
    }
    modal.on('hide.bs.modal.quickCustomer', function (e) { if (busy) { e.preventDefault(); } });
    modal.find('form.modal-content').on('submit.quickCustomer', function (e) {
        e.preventDefault();
        if (panel.is(':visible')) { modal.find('#qc-save').click(); }
        else { modal.find('#invoice_create_confirm').click(); }
    });
    panel.on('keydown.quickCustomer', 'input:not([type=checkbox]):not([type=hidden])', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); modal.find('#qc-save').click(); }
    });
    modal.find('#qc-open').on('click', openCustomer);
    modal.find('#qc-back').on('click', function () { if (!busy) { showInvoice(); } });
    modal.find('#qc-same-billing').on('change', function () { modal.find('#qc-billing').toggle(!this.checked); });
    panel.find('[data-qc-field],#qc-same-billing').on('input change', function () {
        $(this).removeAttr('aria-invalid').closest('.form-group').removeClass('has-error').find('.qc-field-error').empty();
        if (!panel.find('[aria-invalid=true]').length) { modal.find('#qc-errors').hide(); }
        matchVersion = ''; modal.find('#qc-matches').hide(); modal.find('#qc-separate').prop('checked', false);
    });
    picker.on('select2:open.quickCustomer', function () {
        var dropdown = modal.find('.select2-dropdown');
        var field = dropdown.find('.select2-search__field');
        search = field.val() || '';
        field.off('input.quickCustomer').on('input.quickCustomer', function () { search = this.value; });
        if (field.length && !field.data('qc-keyboard')) {
            field.data('qc-keyboard', true);
            field[0].addEventListener('keydown', function (e) {
                if (e.key === 'Tab' && !e.shiftKey) {
                    e.preventDefault(); e.stopImmediatePropagation();
                    modal.find('.qc-dropdown-create').focus();
                }
            }, true);
        }
        dropdown.find('.qc-dropdown-create').remove();
        $('<button type="button" class="qc-dropdown-create">+ Create new customer</button>')
            .on('mousedown', function (e) { e.stopPropagation(); })
            .on('keydown', function (e) {
                if (e.key === 'Tab' && e.shiftKey) { e.preventDefault(); field.focus(); }
                if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); picker.select2('close'); modal.find('#qc-open').focus(); }
            })
            .on('click', openCustomer).appendTo(dropdown);
    });
    modal.find('#qc-save').on('click', function () {
        if (busy) { return; }
        resetErrors();
        var data = {request_id: modal.find('#qc-request').val(), same_billing: modal.find('#qc-same-billing').prop('checked') ? '1' : '0', separate_customer: modal.find('#qc-separate').prop('checked') ? '1' : '0', match_version: matchVersion};
        panel.find('[data-qc-field]').each(function () { data[$(this).attr('data-qc-field')] = $(this).val(); });
        setBusy(true);
        $.ajax({url: <?php echo json_encode(site_url('clients/ajax/quick_create')); ?>, method:'POST', data:data, dataType:'json', timeout:25000})
            .done(function (response) {
                if (response.new_token && typeof csrf_token_value !== 'undefined') { csrf_token_value = response.new_token; }
                if (response.success === 1) {
                    modal.find('#qc-request').val(response.next_request_id);
                    panel.find('[data-qc-field]').each(function () { $(this).val($(this).attr('data-qc-field').endsWith('_country') ? 'US' : ''); });
                    modal.find('#qc-same-billing').prop('checked', true); modal.find('#qc-billing,#qc-matches').hide();
                    modal.find('#qc-separate').prop('checked', false); matchVersion = ''; search = '';
                    modal.find('#qc-addresses').prop('open', false);
                    selectCustomer(response.client, true);
                } else if (response.matches) {
                    matchVersion = response.match_version;
                    var list = modal.find('#qc-match-list').empty();
                    modal.find('#qc-match-message').text(response.message);
                    response.matches.forEach(function (client) {
                        var row = $('<div class="qc-match"></div>').appendTo(list);
                        $('<span></span>').text(client.text + (client.email ? ' · ' + client.email : '') + (client.active ? '' : ' · Inactive')).appendTo(row);
                        if (client.active) { $('<button type="button" class="btn btn-default btn-sm">Use this customer</button>').on('click', function () { selectCustomer(client, false); }).appendTo(row); }
                        else { $('<span></span>').text('Manage inactive customers from the customer list.').appendTo(row); }
                    });
                    modal.find('#qc-separate').prop('checked', false); modal.find('#qc-matches').show();
                    modal.find('#qc-match-message')[0].scrollIntoView({block:'nearest'});
                } else {
                    var first = null;
                    Object.keys(response.validation_errors || {}).forEach(function (key) {
                        var field = panel.find('[data-qc-field]').filter(function () { return $(this).attr('data-qc-field') === key; });
                        field.attr({'aria-invalid':'true','aria-describedby':'qc-' + key + '-error'}).closest('.form-group').addClass('has-error').find('.qc-field-error').text(response.validation_errors[key]);
                        if (key.indexOf('service_') === 0 || key.indexOf('billing_') === 0) { modal.find('#qc-addresses').prop('open',true); }
                        if (!first && field.length) { first = field; }
                    });
                    message(response.message || 'Please check the highlighted customer details.');
                    if (first) { focusAfterSave = first; }
                }
            }).fail(function (xhr) {
                var token = xhr.getResponseHeader('X-CSRF-Token');
                if (token && typeof csrf_token_value !== 'undefined') { csrf_token_value = token; }
                message('Could not confirm the save. Keep these details and try again; the same request will be checked before another customer is created.');
            }).always(function () { setBusy(false); if (focusAfterSave) { focusAfterSave.focus(); focusAfterSave = null; } });
    });
});
</script>
