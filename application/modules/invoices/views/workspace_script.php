<?php
$workspace_messages = [];
foreach (['service_property','choose_property','charges','add_charge','add_from','unassigned','assign','discard','saving','save','network_error','save_error','copied','copy_error','removed','pick_first','pending','tax_remove','loading_failed','saved_changes'] as $key) $workspace_messages[$key] = trans('invoice_workspace_' . $key);
$workspace_config = [
    'editing' => $editing, 'readOnly' => (bool)$read_only, 'canAdd' => (bool)$can_add,
    'propertyEnabled' => (bool)$state, 'frozen' => (bool)$property_frozen,
    'properties' => $properties, 'revision' => (int)($state->revision ?? 0),
    'invoiceId' => (int)$invoice_id, 'clientId' => (int)$invoice->client_id,
    'legacy' => (int)$legacy_calculation, 'summaryUrl' => $summary_url,
    'site' => site_url(), 'guestUrl' => (int)$invoice->invoice_status_id !== 1 ? site_url('guest/view/invoice/' . $invoice->invoice_url_key) : '',
    'taskEnabled' => get_setting('projects_enabled') == 1,
    'productLabel' => trans('add_product'), 'taskLabel' => trans('add_task'),
    'messages' => $workspace_messages,
];
?>
<script>
$(function () {
    'use strict';
    const cfg = <?php echo json_encode($workspace_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const root = document.getElementById('invoice-workspace');
    const table = $('#item_table');
    const msg = cfg.messages;
    const deleted = new Set();
    const groups = new Map();
    let sequence = 0, dirty = false, busy = false, uncertain = false, lookupGroup = null;
    window.propertyRevision = cfg.revision;
    const endpoint = path => cfg.site.replace(/\/$/, '') + '/' + path;
    function feedback(message) { $('#invoice-feedback').text(message); }
    function dirtyChange() {
        if (!cfg.editing) return;
        dirty = true;
        $('#invoice-totals-pending').prop('hidden', false);
        feedback(msg.pending);
    }
    function syncToken(xhr, response) {
        const token = xhr.getResponseHeader('X-CSRF-Token') || (response && response.new_token);
        if (token) {
            csrf_token_value = token;
            $('meta[name="csrf_token_value"]').attr('content', token);
            $('input[name="' + csrf_token_name + '"]').val(token);
        }
    }
    $(document).ajaxComplete(function (event, xhr) { syncToken(xhr); });
    function displayError(message) {
        $('#invoice-errors').text(message).prop('hidden', false)[0].scrollIntoView({block: 'nearest'});
    }
    function showFieldErrors(errors) {
        if (typeof errors === 'string') errors = {custom: errors};
        if (errors.custom) $('#invoice-settings').prop('open', true);
        $('.iw-has-error').removeClass('iw-has-error').removeAttr('aria-invalid');
        let first = null;
        Object.keys(errors).forEach(function (key) {
            const el = document.getElementById(key) || document.getElementById('custom' + key);
            if (el && root.contains(el)) {
                $(el).addClass('iw-has-error').attr('aria-invalid', 'true');
                $(el).parents('details').prop('open', true);
                first = first || el;
            }
        });
        // Responses from native validators can contain emphasis markup. Render text only.
        const messages = Object.values(errors).map(value => String(value).replace(/<[^>]*>/g, ''));
        if (errors.service_property) {
            table.find('.item').each(function () {
                const name = $(this).find('[name=item_name]');
                if (!name.val().trim()) { name.addClass('iw-has-error').attr('aria-invalid', 'true'); first = first || name[0]; }
                const property = $(this).find('[name=item_service_property_id]');
                if (cfg.propertyEnabled && !property.val()) $(this).closest('.iw-property').find('.iw-assign-group').addClass('iw-has-error').attr('aria-invalid', 'true');
            });
        }
        displayError(messages.join(' ') || msg.save_error);
        if (first) first.focus();
        if (messages.some(message => /reload|did not finish|verification failed/i.test(message))) uncertain = true;
    }
    function propertyText(property) {
        return ['label','address_1','address_2','city','state','zip','country'].map(key => property[key]).filter(Boolean).join(', ');
    }
    function groupFor(pid, snapshot) {
        const property = cfg.properties.find(p => String(p.property_id) === String(pid));
        let savedAddress = null;
        try { savedAddress = snapshot ? JSON.parse(snapshot) : null; } catch (_) { /* Retain unknown historical grouping. */ }
        const addressKey = address => JSON.stringify(['label','address_1','address_2','city','state','zip','country'].map(k => String(address[k] || '')));
        const key = cfg.propertyEnabled ? String(pid || '') + '|' + (savedAddress ? addressKey(savedAddress) : (property ? addressKey(property) : (snapshot || ''))) : 'all';
        if (groups.has(key)) return groups.get(key);
        let address = null;
        try { address = snapshot ? JSON.parse(snapshot) : null; } catch (error) { /* Display a valid property label below. */ }
        address = address || cfg.properties.find(property => String(property.property_id) === String(pid));
        const group = $('<section class="iw-card iw-property">').attr('data-property-id', pid || '');
        const heading = $('<header class="iw-property-heading">');
        $('<span class="iw-eyebrow">').text(cfg.propertyEnabled ? msg.service_property : msg.charges).appendTo(heading);
        $('<h4>').text(cfg.propertyEnabled ? (address ? propertyText(address) : msg.unassigned) : msg.charges).appendTo(heading);
        group.append(heading, $('<div class="iw-group-items">'));
        if (cfg.canAdd && (!cfg.propertyEnabled || !pid || (property && Number(property.active) === 1))) {
            if (cfg.propertyEnabled && !pid) {
                const assignment = $('<select class="form-control iw-assign-group">').attr('aria-label', msg.choose_property).append($('<option>').val('').text(msg.choose_property));
                cfg.properties.filter(p => Number(p.active) === 1).forEach(p => assignment.append($('<option>').val(p.property_id).text(propertyText(p))));
                heading.append(assignment);
            }
            const actions = $('<div class="iw-group-actions">');
            $('<button type="button" class="btn btn-default btn_add_row">').text('+ ' + msg.add_charge).appendTo(actions);
            const menu = $('<div class="dropdown">');
            $('<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true">').text(msg.add_from).appendTo(menu);
            const choices = $('<ul class="dropdown-menu">');
            $('<li>').append($('<button type="button" class="btn_add_product">').text(cfg.productLabel)).appendTo(choices);
            if (cfg.taskEnabled) $('<li>').append($('<button type="button" class="btn_add_task">').text(cfg.taskLabel)).appendTo(choices);
            menu.append(choices); actions.append(menu); group.append(actions);
        }
        groups.set(key, group); table.append(group);
        return group;
    }
    function placeRow(row, pid, snapshot) {
        row.attr('data-address', snapshot || '');
        const group = groupFor(pid, snapshot);
        group.children('.iw-group-items').append(row);
        row.find('[name=item_service_property_id]').val(pid || '');
        // Empty source groups can be reused without changing the order of remaining groups.
        groups.forEach(g => g.toggle(g.find('.item').length > 0 || g.is(group)));
        return group;
    }
    function identifyRow(row) {
        const prefix = 'charge-new-' + (++sequence) + '-';
        row.find('[id]').each(function () {
            const old = this.id, next = prefix + this.id.split('-').pop();
            row.find('label').filter(function () { return $(this).attr('for') === old; }).attr('for', next);
            this.id = next;
        });
    }
    function addRow(group, focus = true) {
        if (!cfg.canAdd) return null;
        const row = $('#new_row > .iw-charge').clone().addClass('item');
        identifyRow(row);
        row.find('[name=item_service_property_id]').val(group.attr('data-property-id') || '');
        group.children('.iw-group-items').append(row);
        group.show();
        dirtyChange();
        check_items_tax_usages();
        if (focus) row.find('[name=item_name]').trigger('focus');
        return row;
    }
    if (cfg.editing) {
        $(document).on('keydown.invoiceWorkspace', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's' && !$('#modal-placeholder .modal:visible').length) { event.preventDefault(); $('#btn_save_invoice').trigger('click'); }
        });
        table.children('.item').each(function () {
            const row = $(this);
            placeRow(row, row.find('[name=item_service_property_id]').val(), row.attr('data-address'));
        });
        if (!table.find('.item').length && cfg.canAdd) {
            const active = cfg.properties.filter(p => Number(p.active) === 1);
            const group = groupFor(active.length === 1 ? active[0].property_id : '', '');
            addRow(group, false);
            dirty = false; $('#invoice-totals-pending').prop('hidden', true); feedback('');
        }
        table.on('click', '.btn_add_row', function () { addRow($(this).closest('.iw-property')); });
        table.on('click', '.iw-remove', function () {
            const row = $(this).closest('.item');
            const id = Number(row.find('[name=item_id]').val());
            if (id) deleted.add(id);
            const group = row.closest('.iw-property');
            row.remove(); dirtyChange(); feedback(msg.removed);
            group.find('.btn_add_row').trigger('focus');
            check_items_tax_usages();
        });
        table.on('click', '.iw-up, .iw-down', function () {
            const row = $(this).closest('.item');
            if ($(this).hasClass('iw-up')) row.insertBefore(row.prev('.item'));
            else row.insertAfter(row.next('.item'));
            dirtyChange(); this.focus();
        });
        table.on('change', '[name=item_service_property_id]', function () {
            const row = $(this).closest('.item');
            const group = placeRow(row, this.value, '');
            dirtyChange(); group.find('[name=item_name]').first().trigger('focus');
        });
        table.on('change', '.iw-assign-group', function () {
            if (!this.value) return;
            const pid = this.value;
            $(this).closest('.iw-property').find('.item').each(function () { placeRow($(this), pid, ''); });
            dirtyChange();
        });
        $('#invoice-add-property').on('click', function () {
            const pid = $('#invoice-property-picker').val();
            if (!pid) { feedback(msg.pick_first); $('#invoice-property-picker').trigger('focus'); return; }
            const group = groupFor(pid, '');
            addRow(group); $('#invoice-property-picker').val('');
        });
        function loadLookup(kind, group) {
            lookupGroup = group;
            const path = kind === 'product' ? 'products/ajax/modal_product_lookups' : 'tasks/ajax/modal_task_lookups/' + cfg.invoiceId;
            $('#modal-placeholder').load(endpoint(path), function (_, status) { if (status === 'error') displayError(msg.loading_failed); });
        }
        table.on('click', '.btn_add_product, .btn_add_task', function () { loadLookup($(this).hasClass('btn_add_product') ? 'product' : 'task', $(this).closest('.iw-property')); });
        window.invoiceWorkspace = {
            addLookupItem: function (data, kind) {
                const row = addRow(lookupGroup, false);
                if (!row) return;
                const mapping = kind === 'product' ? {item_name:'product_name', item_description:'product_description', item_price:'product_price', item_product_id:'product_id', item_product_unit_id:'unit_id'} : {item_name:'task_name', item_description:'task_description', item_price:'task_price', item_task_id:'task_id'};
                Object.keys(mapping).forEach(field => row.find('[name="' + field + '"]').val(data[mapping[field]] || ''));
                row.find('[name=item_quantity]').val('1');
                row.find('[name=item_tax_rate_id]').val(data.tax_rate_id || '0');
                if (Number(data.tax_rate_id) || Number(data.unit_id)) row.find('.iw-charge-details').prop('open', true);
                dirtyChange();
            }
        };
        $(root).on('input change', '#invoice_form input, #invoice_form select, #invoice_form textarea', function () {
            if (this.id !== 'invoice-property-picker' && !$(this).closest('#new_row').length) dirtyChange();
        });
        function discountState() {
            if (cfg.readOnly) return;
            $('#invoice_discount_amount').prop('disabled', $('#invoice_discount_percent').val().length > 0);
            $('#invoice_discount_percent').prop('disabled', $('#invoice_discount_amount').val().length > 0);
        }
        $('#invoice_discount_amount, #invoice_discount_percent').on('input', discountState); discountState();
        $('#btn_save_invoice').on('click', function () {
            if (busy || uncertain) return;
            const lines = [];
            table.find('.item').each(function (index) {
                const line = {};
                $(this).find('input[name], select[name], textarea[name]').each(function () { line[this.name] = $(this).val(); });
                line.item_order = cfg.readOnly ? Number($(this).attr('data-order')) : index + 1;
                lines.push(line);
            });
            const customFields = $('#invoice-settings [name^="custom"]').serializeArray();
            $('#invoice-settings select[multiple]').each(function () { if (!$(this).val().length) customFields.push({name: this.name, value: ''}); });
            const payload = {invoice_id: cfg.invoiceId, legacy_calculation: cfg.legacy, items: JSON.stringify(lines), deleted_item_ids: JSON.stringify(Array.from(deleted)), property_revision: window.propertyRevision, property_refresh: $('#property-refresh').is(':checked') ? '1' : '0', custom: customFields};
            ['invoice_number','invoice_date_created','invoice_date_due','invoice_status_id','invoice_password','invoice_discount_amount','invoice_discount_percent','invoice_terms','payment_method'].forEach(key => payload[key] = $('#' + key).val());
            busy = true; $('#btn_save_invoice').prop('disabled', true).text(msg.saving); $('#invoice-errors').prop('hidden', true);
            $.post(endpoint('invoices/ajax/save'), payload).done(function (data, _, xhr) {
                let response;
                try { response = typeof data === 'string' ? JSON.parse(data) : data; } catch (error) { uncertain = true; displayError(msg.network_error); return; }
                syncToken(xhr, response);
                if (response.property_revision) window.propertyRevision = response.property_revision;
                if (response.success === 1) {
                    dirty = false;
                    window.location.assign(cfg.summaryUrl);
                } else { showFieldErrors(response.validation_errors || {}); if (response.save_incomplete) { uncertain = true; displayError(msg.network_error); } }
            }).fail(function (xhr) { syncToken(xhr); uncertain = true; displayError(msg.network_error); }).always(function () {
                busy = false; $('#btn_save_invoice').prop('disabled', uncertain).text(msg.save);
            });
        });
        window.addEventListener('beforeunload', function (event) { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
        document.addEventListener('click', function (event) {
            const link = event.target.closest('a[href]');
            if (!link || !dirty || link.target === '_blank' || link.getAttribute('href').startsWith('#') || link.getAttribute('href').startsWith('javascript:')) return;
            if (!window.confirm(msg.discard)) { event.preventDefault(); event.stopImmediatePropagation(); }
            else dirty = false;
        }, true);
        check_items_tax_usages();
    }
    $('#invoice-copy-link').on('click', async function () {
        try { await navigator.clipboard.writeText(cfg.guestUrl); feedback(msg.copied); } catch (error) { displayError(msg.copy_error); }
    });
    function loadAction(path, data) {
        $('#modal-placeholder').load(endpoint(path), data, function (_, status) { if (status === 'error') displayError(msg.loading_failed); });
    }
    $('#btn_create_recurring').on('click', function () { loadAction('invoices/ajax/modal_create_recurring', {invoice_id:cfg.invoiceId}); });
    $('#invoice_change_client').on('click', function () { loadAction('invoices/ajax/modal_change_client', {invoice_id:cfg.invoiceId, client_id:cfg.clientId}); });
    $('#invoice_change_user').on('click', function () { loadAction('invoices/ajax/modal_change_user', {invoice_id:cfg.invoiceId}); });
    $('.iw-remove-tax').on('click', function (event) { if (!window.confirm(msg.tax_remove)) event.preventDefault(); });
    if (window.location.hash === '#invoice-attachments') $('#invoice-attachments').prop('open', true);
});
</script>
