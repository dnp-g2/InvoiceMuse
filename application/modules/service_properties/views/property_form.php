<?php
$id = (int)$property['property_id'];
$failed = $property_form_error && (int)$property_form_error['id'] === $id;
$values = $failed ? array_replace($property, $property_form_error['address']) : $property;
$revision = $failed ? (int)$property_form_error['revision'] : (int)$property['revision'];
echo form_open('service-properties/client/' . (int)$client->client_id . '/save');
?>
<input type="hidden" name="property_id" value="<?php echo $id; ?>"><input type="hidden" name="revision" value="<?php echo $revision; ?>"><input type="hidden" name="active" value="<?php echo (int)$property['active']; ?>">
<?php if ($failed) { ?><p class="text-danger property-error" role="alert">Please correct the issue shown above. Your entries have been kept. If another edit changed this property, reload the page before trying again.</p><?php } ?>
<div class="row">
<?php foreach (['address_1' => ['Street address',12,150], 'address_2' => ['Unit / address line 2 (optional)',12,150], 'city' => ['City',6,100], 'state' => ['State',3,100], 'zip' => ['ZIP / postal code',3,20], 'country' => ['Country',6,2], 'label' => ['Property name (optional)',6,100]] as $key => [$label,$width,$max]) {
$value = $values[$key] ?? ($key === 'country' ? 'US' : '');
$value = is_scalar($value) ? (string)$value : '';
?>
<div class="col-sm-<?php echo $width; ?> form-group"><label for="property-<?php echo $id . '-' . $key; ?>"><?php echo html_escape($label); ?></label>
<?php if ($key === 'country') { ?><select class="form-control" id="property-<?php echo $id . '-' . $key; ?>" name="address[country]" required><?php foreach ($countries as $code => $name) { ?><option value="<?php echo html_escape($code); ?>" <?php echo $value === $code ? 'selected' : ''; ?>><?php echo html_escape($name); ?></option><?php } ?></select>
<?php } else { ?><input class="form-control" id="property-<?php echo $id . '-' . $key; ?>" name="address[<?php echo $key; ?>]" value="<?php echo html_escape($value); ?>" maxlength="<?php echo $max; ?>" <?php echo in_array($key,['label','address_2'],true) ? '' : 'required'; ?>><?php } ?></div>
<?php } ?></div>
<button type="submit" class="btn btn-primary"><?php echo $id ? 'Save changes' : 'Add property'; ?></button> <button type="button" class="btn btn-default property-cancel">Cancel</button></form>
