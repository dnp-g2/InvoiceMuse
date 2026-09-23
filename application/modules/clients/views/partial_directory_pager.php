<div class="directory-pagination">
<p class="directory-summary"><?php echo html_escape($summary); ?></p>
<?php if ($pageCount > 1) { ?>
<nav class="directory-page-controls" aria-label="Customer pages"><span class="directory-page-position">Page <?php echo $pageNumber; ?> of <?php echo $pageCount; ?></span>
<?php if ($pageNumber>1) { ?><a class="btn btn-default btn-sm" href="<?php echo html_escape($url($l['status'],$l['offset']-$l['size'])); ?>">Previous</a><?php } else { ?><button class="btn btn-default btn-sm" disabled>Previous</button><?php } ?>
<?php
$pages=array_unique(array_merge([1,$pageCount],range(max(1,$pageNumber-1),min($pageCount,$pageNumber+1))));sort($pages);$prior=0;
foreach ($pages as $number) { if($prior && $number>$prior+1){ ?><span aria-hidden="true">…</span><?php } ?>
<a class="btn btn-sm <?php echo $number===$pageNumber?'btn-primary':'btn-default'; ?>" href="<?php echo html_escape($url($l['status'],($number-1)*$l['size'])); ?>" aria-label="Page <?php echo $number; ?>" <?php echo $number===$pageNumber?'aria-current="page"':''; ?>><?php echo $number; ?></a>
<?php $prior=$number; } ?>
<?php if ($pageNumber<$pageCount) { ?><a class="btn btn-default btn-sm" href="<?php echo html_escape($url($l['status'],$l['offset']+$l['size'])); ?>">Next</a><?php } else { ?><button class="btn btn-default btn-sm" disabled>Next</button><?php } ?>
</nav><?php } ?>
</div>
