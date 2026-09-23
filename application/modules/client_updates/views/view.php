<div id="headerbar">
    <h1 class="headerbar-title">Client Update <?php echo html_escape($request->request_reference); ?></h1>
    <div class="headerbar-item pull-right">
        <a class="btn btn-default btn-sm" href="<?php echo site_url('client-updates/' . $request->status); ?>">Back to queue</a>
    </div>
</div>
<div id="content">
    <?php $this->layout->load_view('layout/alerts'); ?>
    <div class="row">
        <div class="col-sm-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Contact information</strong></div>
                <table class="table">
                    <tr><th style="width: 35%">Full name</th><td><?php echo html_escape($request->full_name); ?></td></tr>
                    <tr><th>Phone</th><td><a href="tel:<?php echo html_escape($request->phone); ?>"><?php echo html_escape($request->phone); ?></a></td></tr>
                    <tr><th>Email</th><td><a href="mailto:<?php echo html_escape($request->email); ?>"><?php echo html_escape($request->email); ?></a></td></tr>
                    <tr><th>Submitted</th><td><?php echo html_escape($request->submitted_at); ?> UTC</td></tr>
                    <tr><th>Status</th><td><?php echo html_escape(ucfirst($request->status)); ?></td></tr>
                </table>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Service Address</strong></div>
                <div class="panel-body">
                    <?php echo html_escape($request->service_address_1); ?><br>
<?php if ($request->service_address_2) { ?>
                    <?php echo html_escape($request->service_address_2); ?><br>
<?php } ?>
                    <?php echo html_escape($request->service_city . ', ' . $request->service_state . ' ' . $request->service_zip); ?>
                </div>
            </div>
<?php if ($additional_service_addresses !== []) { ?>
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Additional Service Addresses</strong></div>
                <div class="panel-body">
<?php foreach ($additional_service_addresses as $index => $address) { ?>
                    <p<?php echo $index === array_key_last($additional_service_addresses) ? ' style="margin-bottom: 0"' : ''; ?>>
                        <strong>Service Address <?php echo $index + 2; ?></strong><br>
                        <?php echo html_escape($address['address_1'] ?? ''); ?><br>
<?php if ( ! empty($address['address_2'])) { ?>
                        <?php echo html_escape($address['address_2']); ?><br>
<?php } ?>
                        <?php echo html_escape(($address['city'] ?? '') . ', ' . ($address['state'] ?? '') . ' ' . ($address['zip'] ?? '')); ?>
                    </p>
<?php } ?>
                </div>
            </div>
<?php } ?>
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Mailing address</strong></div>
                <div class="panel-body">
<?php if ((int) $request->mailing_same_as_service === 1) { ?>
                    Same as Service Address
<?php } else { ?>
                    <?php echo html_escape($request->mailing_address_1); ?><br>
<?php if ($request->mailing_address_2) { ?>
                    <?php echo html_escape($request->mailing_address_2); ?><br>
<?php } ?>
                    <?php echo html_escape($request->mailing_city . ', ' . $request->mailing_state . ' ' . $request->mailing_zip); ?>
<?php } ?>
                </div>
            </div>
        </div>
    </div>
<?php $this->load->view('client_updates/review', compact('request', 'review', 'review_ready', 'dismiss_version')); ?>
</div>
