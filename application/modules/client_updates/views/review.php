<?php
$selected = $review['selected'];
$labels = ['name' => 'Full name', 'email' => 'Email', 'phone' => 'Phone', 'billing' => 'Billing address', 'active' => 'Customer status'];
$display = static function (string $group, array $fields): string {
    if ($group === 'active') { return !empty($fields['client_active']) ? 'Active' : 'Inactive'; }
    return implode($group === 'name' ? ' ' : ', ', array_filter(array_map('strval', array_values($fields)), fn($v) => $v !== ''));
};
?>
<style>
.cu-review {max-width:1000px;margin:24px auto}.cu-review .panel-body {padding:22px}.cu-review h2{font-size:21px;margin:0 0 14px}.cu-review .cu-fields{display:grid;grid-template-columns:1fr;gap:12px;margin:20px 0}.cu-review .cu-field{padding:14px;background:#f5f8fb;border:1px solid #dce4eb;border-radius:6px;overflow-wrap:anywhere}.cu-review .cu-before{color:#596875;font-size:13px;margin:6px 0}.cu-review .cu-after{font-weight:600}.cu-review .cu-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.cu-review select{max-width:100%}.cu-review label{font-weight:500}.cu-review summary{cursor:pointer;padding:12px 0}.cu-review .cu-note{color:#596875}.cu-review .btn{white-space:normal}@media(min-width:768px){.cu-review .cu-fields{grid-template-columns:1fr 1fr}}
</style>
<div class="cu-review">
<?php if (!empty($request->applied_client_id)) { ?>
    <div class="panel panel-success"><div class="panel-body">
        <h2>Customer information saved</h2>
        <p>This submission was saved on <?php echo html_escape($request->applied_at); ?> UTC.</p>
        <a class="btn btn-primary" href="<?php echo site_url('clients/view/' . (int)$request->applied_client_id); ?>">View customer</a>
<?php if ($request->status === 'pending') { ?>
        <?php echo form_open('client-updates/apply/' . (int)$request->request_id); ?>
            <input type="hidden" name="customer_id" value="<?php echo (int)$request->applied_client_id; ?>">
            <input type="hidden" name="review_version" value="<?php echo html_escape($review['version']); ?>">
            <p>This request was reopened. Closing it again will keep the customer information already saved.</p>
            <button class="btn btn-default" type="submit">Close reviewed request</button>
        </form>
<?php } ?>
    </div></div>
<?php } else { ?>
    <div class="panel panel-default"><div class="panel-body">
        <h2>Save customer information</h2>
<?php if ($request->status === 'resolved') { ?>
        <div class="alert alert-info">This request was previously closed. No customer link was recorded. Review any matching customer before saving it.</div>
        <p class="cu-note">Previous resolution: <?php echo html_escape($request->resolution_note ?: 'No note recorded.'); ?></p>
<?php } ?>
<?php if (!$review_ready) { ?>
        <div class="alert alert-warning">Customer review setup is incomplete. Saving is temporarily unavailable.</div>
<?php } ?>
<?php foreach ($review['validation_errors'] as $error) { ?>
        <div class="alert alert-warning"><?php echo html_escape($error); ?></div>
<?php } ?>
<?php if ($review['matches']) { ?>
        <p><strong>Possible matching customers</strong> — choose one to review changes:</p>
        <ul>
<?php foreach ($review['matches'] as $match) { ?>
            <li><a href="<?php echo site_url('client-updates/view/' . (int)$request->request_id) . '?customer=' . (int)$match['client_id']; ?>"><?php echo html_escape(trim($match['client_name'] . ' ' . $match['client_surname'])); ?></a> · <?php echo $match['client_active'] ? 'Active' : 'Inactive'; ?> · <?php echo html_escape($match['client_email']); ?></li>
<?php } ?>
        </ul>
<?php } ?>
        <form id="cu-select-form" method="get" action="<?php echo site_url('client-updates/view/' . (int)$request->request_id); ?>" class="cu-actions">
            <label for="cu-customer">Save to</label>
            <select id="cu-customer" name="customer" class="form-control" style="width:auto">
                <option value="0">New customer</option>
<?php foreach ($review['clients'] as $client) { ?>
                <option value="<?php echo (int)$client['client_id']; ?>" <?php echo $selected && $selected['client_id'] === $client['client_id'] ? 'selected' : ''; ?>><?php echo html_escape(trim($client['client_name'] . ' ' . $client['client_surname']) . ($client['client_active'] ? '' : ' (Inactive)')); ?></option>
<?php } ?>
            </select>
            <button type="submit" class="btn btn-default">Review selection</button>
        </form>
        <?php echo form_open('client-updates/apply/' . (int)$request->request_id, ['id' => 'cu-apply']); ?>
            <input type="hidden" name="customer_id" value="<?php echo (int)($selected['client_id'] ?? 0); ?>">
            <input type="hidden" name="review_version" value="<?php echo html_escape($review['version']); ?>">
<?php if ($selected) { ?>
            <p style="margin-top:18px">Choose the details to update for <strong><?php echo html_escape(trim($selected['client_name'] . ' ' . $selected['client_surname'])); ?></strong>. Unchecked details stay as they are.</p>
<?php } ?>
            <div class="cu-fields">
<?php foreach ($review['groups'] as $key => $fields) {
    $before = $selected ? array_intersect_key($selected, $fields) : [];
    // Keep address and name fields in the same order as the proposed values.
    $before = $selected ? array_replace(array_fill_keys(array_keys($fields), ''), $before) : [];
    $changed = !$selected || $display($key, $before) !== $display($key, $fields);
?>
                <div class="cu-field">
                    <label>
<?php if ($selected && $changed) { ?><input type="checkbox" name="fields[]" value="<?php echo html_escape($key); ?>"> <?php } ?>
                        <?php echo html_escape($labels[$key]); ?>
                    </label>
<?php if ($selected && $changed) { ?><div class="cu-before">Current: <?php echo html_escape($display($key, $before) ?: 'Not provided'); ?></div><?php } ?>
                    <div class="cu-after"><?php echo html_escape($display($key, $fields)); ?></div>
<?php if ($selected && !$changed) { ?><div class="cu-note">Already up to date</div><?php } ?>
                </div>
<?php } ?>
            </div>
            <h3 style="font-size:17px">Service properties</h3>
<?php if (!$review['additions']) { ?>
            <p>All submitted service properties already exist on this customer.</p>
<?php } else { ?>
            <p>These service properties will be added:</p>
            <ul><?php foreach ($review['additions'] as $addition) { ?><li><?php echo html_escape(Property_rules::text($addition['address'])); ?></li><?php } ?></ul>
<?php } ?>
            <p class="cu-note">Existing properties are kept. The original submission is saved in customer notes.</p>
<?php if (!$selected && $review['matches']) { ?>
            <div class="checkbox"><label><input type="checkbox" name="separate_customer" value="1" required> I reviewed the matches above. This is a different customer and needs a separate account.</label></div>
<?php } ?>
            <button class="btn btn-success" type="submit" <?php echo !$review_ready || $review['validation_errors'] ? 'disabled' : '';  ?>><?php echo $selected ? 'Update customer and resolve' : 'Create customer and resolve'; ?></button>
        </form>
    </div></div>
<?php if ($request->status === 'pending') { ?>
    <details><summary>Dismiss without customer changes</summary>
        <p>Use this only for spam, duplicates, or information that needs no changes.</p>
        <?php echo form_open('client-updates/dismiss/' . (int)$request->request_id); ?>
            <input type="hidden" name="review_version" value="<?php echo html_escape($dismiss_version); ?>">
            <label for="resolution_note">Reason</label>
            <textarea id="resolution_note" name="resolution_note" class="form-control" rows="2" maxlength="1000" required></textarea>
            <button class="btn btn-default" style="margin-top:12px" type="submit">Dismiss without changes</button>
        </form>
    </details>
<?php } else { ?>
    <?php echo form_open('client-updates/reopen/' . (int)$request->request_id); ?><button class="btn btn-default" type="submit">Reopen request</button></form>
<?php } ?>
<?php } ?>
</div>

<script>
(function () {
    var picker = document.getElementById('cu-customer');
    var form = document.getElementById('cu-apply');
    if (picker && form) {
        picker.addEventListener('change', function () {
            var button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            button.textContent = 'Review selection before saving';
        });
    }
}());
</script>
