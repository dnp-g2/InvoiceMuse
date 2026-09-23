<?php
$note_groups = ['active' => [], 'archived' => []];
foreach ($client_notes as $note) {
    $note_groups[empty($note->client_note_archived_at) ? 'active' : 'archived'][] = $note;
}
if (!$note_groups['active']) { ?><p class="text-muted">No active notes.</p><?php }
foreach ($note_groups as $group => $notes) {
    if ($group === 'archived') {
        if (!$notes) continue;
        ?><details class="archived-client-notes" style="margin-bottom:16px"><summary style="cursor:pointer;margin-bottom:12px">Archived notes (<?php echo count($notes); ?>)</summary><?php
    }
    foreach ($notes as $client_note) { ?>
    <div class="panel panel-default small">
        <div class="panel-body"><div class="customer-note-text customer-note-preview" id="customer-note-<?php echo (int)$client_note->client_note_id; ?>"><?php echo nl2br(htmlsc($client_note->client_note)); ?></div><button type="button" class="btn btn-link btn-xs customer-note-toggle" aria-expanded="false" aria-controls="customer-note-<?php echo (int)$client_note->client_note_id; ?>" style="display:none">Show full note</button></div>
        <div class="panel-footer text-muted clearfix">
            <?php echo date_from_mysql($client_note->client_note_date, true); ?>
            <button type="button" data-id="<?php echo (int)$client_note->client_note_id; ?>" data-action="<?php echo $group === 'archived' ? 'restore' : 'archive'; ?>" class="archive_client_note pull-right btn btn-xs btn-default">
                <i class="fa <?php echo $group === 'archived' ? 'fa-undo' : 'fa-archive'; ?>" aria-hidden="true"></i> <?php echo $group === 'archived' ? 'Restore' : 'Archive'; ?>
            </button>
        </div>
    </div>
<?php } if ($group === 'archived') { ?></details><?php } } ?>
