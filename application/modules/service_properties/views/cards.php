<?php
$groups = ['active' => [], 'archived' => []];
foreach ($properties as $index => $property) {
    $property['number'] = $index + 1;
    $groups[$property['active'] ? 'active' : 'archived'][] = $property;
}
?>
<div class="panel panel-default property-editor" id="property-new" <?php echo $property_form_error && !$property_form_error['id'] ? '' : 'hidden'; ?>><div class="panel-body"><h3>Add property</h3>
<?php $property = ['property_id' => 0, 'revision' => 0, 'active' => 1]; include __DIR__ . '/property_form.php'; ?>
</div></div>
<?php if (!$groups['active']) { ?><p>No active service properties. Use <strong>Add property</strong> to add an address.</p><?php } ?>
<?php foreach ($groups as $group => $list) {
    if ($group === 'archived') {
        if (!$list) continue;
        $archivedError = $property_form_error && in_array((int)$property_form_error['id'], array_map('intval', array_column($list, 'property_id')), true);
        ?><details class="property-archived" <?php echo $archivedError ? 'open' : ''; ?>><summary>Archived properties (<?php echo count($list); ?>)</summary><?php
    }
    foreach ($list as $property) {
        $pid = (int)$property['property_id'];
        ?>
<section class="panel panel-default property-card"><div class="panel-body">
<div class="property-summary"><div><h3>Property <?php echo (int)$property['number']; ?><span class="label <?php echo $property['active'] ? 'label-success' : 'label-default'; ?>"><?php echo $property['active'] ? 'Active' : 'Archived'; ?></span></h3>
<?php if ($property['label']) { ?><p><strong><?php echo html_escape($property['label']); ?></strong></p><?php } ?>
<address><?php echo html_escape($property['address_1']); ?><br>
<?php if ($property['address_2']) { echo html_escape($property['address_2']) . '<br>'; } ?>
<?php echo html_escape($property['city'] . ', ' . $property['state'] . ' ' . $property['zip']); ?><br>
<?php echo html_escape($countries[$property['country']] ?? $property['country']); ?></address></div>
<div class="property-actions"><button type="button" class="btn btn-default property-open" data-target="property-edit-<?php echo $pid; ?>" aria-controls="property-edit-<?php echo $pid; ?>" aria-expanded="false">Edit</button>
<?php echo form_open('service-properties/client/' . (int)$client->client_id . '/status', ['class' => $property['active'] ? 'property-archive' : 'property-restore']); ?>
<input type="hidden" name="property_id" value="<?php echo $pid; ?>"><input type="hidden" name="revision" value="<?php echo (int)$property['revision']; ?>"><input type="hidden" name="action" value="<?php echo $property['active'] ? 'archive' : 'restore'; ?>">
<button class="btn btn-default" type="submit"><?php echo $property['active'] ? 'Archive' : 'Restore'; ?></button></form></div></div>
<div class="property-editor" id="property-edit-<?php echo $pid; ?>" <?php echo $property_form_error && (int)$property_form_error['id'] === $pid ? '' : 'hidden'; ?>>
<?php include __DIR__ . '/property_form.php'; ?>
</div></div></section>
<?php } if ($group === 'archived' && $list) { ?></details><?php } } ?>
