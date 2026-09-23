<?php include __DIR__ . '/styles.php'; ?>
<div id="headerbar"><h1 class="headerbar-title">Service properties</h1></div>
<div id="content"><div class="property-page property-workspace" data-inline="0">
<?php $this->layout->load_view('layout/alerts'); ?>
<div class="property-top"><div><h2><?php echo html_escape($client->client_name); ?></h2><p class="property-muted">Service addresses for this customer. Billing details are managed on the customer page.</p></div>
<div class="property-actions"><button class="btn btn-primary property-open" type="button" data-target="property-new" aria-controls="property-new" aria-expanded="false">Add property</button><a class="btn btn-default" href="<?php echo site_url('clients/view/' . (int)$client->client_id); ?>">Back to customer</a></div></div>
<div class="property-feedback" role="status"></div><div class="property-cards"><?php include __DIR__ . '/cards.php'; ?></div></div></div>
<?php include __DIR__ . '/script.php'; ?>
