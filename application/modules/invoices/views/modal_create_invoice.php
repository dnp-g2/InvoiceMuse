<script>
    $(function () {
        if (typeof csrf_token_value !== 'undefined') { csrf_token_value = <?php echo json_encode($this->security->get_csrf_hash()); ?>; }
        // Display the create invoice modal
        $('#create-invoice').modal('show').attr({'aria-hidden':'false', 'aria-modal':'true'});
        $('#create-invoice').on('hidden.bs.modal', function () { $(this).attr('aria-hidden', 'true').removeAttr('aria-modal'); });

        // Enable select2 for all selects
        $('#create-invoice .simple-select').select2({dropdownParent: $('#create-invoice')});

        <?php $this->layout->load_view('clients/script_select2_client_id.js', ['invoice_quick_customer' => true]); ?>

        // Creates the invoice
        $('#invoice_create_confirm').click(function () {
            // Posts the data to validate and create the invoice;
            // Customer creation is handled by the explicit quick-create step.
            $.post("<?php echo site_url('invoices/ajax/create'); ?>", {
                    client_id: $('#create_invoice_client_id').val(),
                    invoice_date_created: $('#invoice_date_created').val(),
                    invoice_group_id: $('#invoice_group_id').val(),
                    invoice_time_created: '<?php echo date('H:i:s') ?>',
                    invoice_password: $('#invoice_password').val(),
                    user_id: '<?php echo $this->session->userdata('user_id'); ?>',
                    payment_method: $('#payment_method_id').val()
                },
                function (data) {
                    var response = json_parse(data, <?php echo (int) IP_DEBUG; ?>);
                    if (response.success === 1) {
                        // The validation was successful and invoice was created
                        window.location = "<?php echo site_url('invoices/view'); ?>/" + response.invoice_id;
                    }
                    else {
                        // The validation was not successful
                        $('.control-group').removeClass('has-error');
                        for (var key in response.validation_errors) {
                            $('#' + key).parent().parent().addClass('has-error');
                        }
                    }
                });
        });
    });

</script>

<div id="create-invoice" class="modal modal-lg"
     role="dialog" tabindex="-1" aria-labelledby="modal_create_invoice" aria-hidden="true">
    <form class="modal-content" novalidate>
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><i class="fa fa-close"></i></button>
            <h4 class="panel-title" id="modal_create_invoice"><?php _trans('create_invoice'); ?></h4>
        </div>
        <div class="modal-body">
            <div id="qc-confirmation" class="alert alert-success" role="status" style="display:none"></div>
            <div id="invoice-start-fields">

            <input class="hidden" id="payment_method_id"
                   value="<?php echo html_escape(get_setting('invoice_default_payment_method')); ?>">
            <input class="hidden" id="input_permissive_search_clients"
                   value="<?php echo html_escape(get_setting('enable_permissive_search_clients')); ?>">

            <div class="form-group has-feedback">
                <div class="qc-customer-label"><label for="create_invoice_client_id">Customer</label><button type="button" class="btn btn-link btn-sm" id="qc-open">+ Create new customer</button></div>
                <div class="input-group">
                    <span id="toggle_permissive_search_clients" class="input-group-addon" title="<?php _trans('enable_permissive_search_clients'); ?>" style="cursor:pointer;">
                        <i class="fa fa-toggle-<?php echo get_setting('enable_permissive_search_clients') ? 'on' : 'off' ?> fa-fw"></i>
                    </span>
                    <select name="client_id" id="create_invoice_client_id" class="client-id-select form-control"
                            autofocus="autofocus" required>
<?php if ( ! empty($client)) : ?>
                        <option value="<?php echo $client->client_id; ?>"><?php _htmlsc(format_client($client, false)); ?></option>
<?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="form-group has-feedback">
                <label for="invoice_date_created"><?php _trans('invoice_date'); ?></label>

                <div class="input-group">
                    <input name="invoice_date_created" id="invoice_date_created"
                           class="form-control datepicker"
                           value="<?php echo date(date_format_setting()); ?>" required>
                    <span class="input-group-addon">
                    <i class="fa fa-calendar fa-fw"></i>
                </span>
                </div>
            </div>

            <div class="form-group">
                <label for="invoice_password"><?php _trans('invoice_password'); ?></label>
                <input type="text" name="invoice_password" id="invoice_password" class="form-control"
                       value="<?php echo get_setting('invoice_pre_password') === '' ? '' : html_escape(get_setting('invoice_pre_password')); ?>"
                       style="margin: 0 auto;" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="invoice_group_id"><?php _trans('invoice_group'); ?></label>
                <select name="invoice_group_id" id="invoice_group_id"
                    class="form-control simple-select" data-minimum-results-for-search="Infinity" required>
<?php
foreach ($invoice_groups as $invoice_group) {
    $is_selected = (get_setting('default_invoice_group') == $invoice_group->invoice_group_id) ? ' selected="selected"' : '';
    ?>
                    <option value="<?php echo $invoice_group->invoice_group_id; ?>"<?php echo $is_selected; ?>>
                        <?php _htmlsc($invoice_group->invoice_group_name); ?>
                    </option>
<?php
}
        ?>
                </select>
            </div>

            </div><!-- invoice-start-fields -->
            <?php $this->load->view('invoices/partial_quick_customer', compact('quick_customer_request', 'quick_customer_countries')); ?>
        </div>
        <div class="modal-footer">
            <div class="btn-group qc-invoice-actions">
                <button class="btn btn-success ajax-loader" id="invoice_create_confirm" type="button">
                    <i class="fa fa-check"></i> Create draft invoice
                </button>
                <button class="btn btn-danger" type="button" data-dismiss="modal">
                    <i class="fa fa-times"></i> <?php _trans('cancel'); ?>
                </button>
            </div>
            <div class="qc-customer-actions" style="display:none">
                <button class="btn btn-default" type="button" id="qc-back">Back to invoice</button>
                <button class="btn btn-success" type="button" id="qc-save">Save customer and continue</button>
            </div>
        </div>

    </form>

</div>
<?php $this->load->view('invoices/script_quick_customer'); ?>
