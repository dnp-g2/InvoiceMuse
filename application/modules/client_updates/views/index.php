<div id="headerbar">
    <h1 class="headerbar-title">Client Information Updates</h1>
    <div class="headerbar-item pull-right">
        <div class="btn-group btn-group-sm index-options">
            <a href="<?php echo site_url('client-updates/pending'); ?>" class="btn <?php echo $status === 'pending' ? 'btn-primary' : 'btn-default'; ?>">
                Pending <span class="badge"><?php echo (int) $pending_count; ?></span>
            </a>
            <a href="<?php echo site_url('client-updates/resolved'); ?>" class="btn <?php echo $status === 'resolved' ? 'btn-primary' : 'btn-default'; ?>">Resolved</a>
        </div>
    </div>
</div>
<div id="content" class="table-content">
    <?php $this->layout->load_view('layout/alerts'); ?>
<?php if ($records === []) { ?>
    <div class="alert alert-info">There are no <?php echo html_escape($status); ?> client information updates.</div>
<?php } else { ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
            <tr>
                <th>Reference</th>
                <th>Client</th>
                <th>Service address</th>
                <th>Submitted (UTC)</th>
                <th>Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
<?php foreach ($records as $record) { ?>
<?php $additional_count = count((array) json_decode((string) ($record->additional_service_addresses ?? ''), true)); ?>
            <tr>
                <td><strong><?php echo html_escape($record->request_reference); ?></strong></td>
                <td><?php echo html_escape($record->full_name); ?></td>
                <td>
                    <?php echo html_escape($record->service_address_1); ?><br>
                    <span class="text-muted"><?php echo html_escape($record->service_city . ', ' . $record->service_state . ' ' . $record->service_zip); ?></span>
<?php if ($additional_count > 0) { ?>
                    <br><span class="text-muted">+<?php echo $additional_count; ?> additional service <?php echo $additional_count === 1 ? 'address' : 'addresses'; ?></span>
<?php } ?>
                </td>
                <td><?php echo html_escape($record->submitted_at); ?></td>
                <td><span class="label <?php echo $record->status === 'pending' ? 'label-warning' : 'label-success'; ?>"><?php echo html_escape(ucfirst($record->status)); ?></span></td>
                <td><a class="btn btn-default btn-sm" href="<?php echo site_url('client-updates/view/' . (int) $record->request_id); ?>">Review</a></td>
            </tr>
<?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>
</div>
