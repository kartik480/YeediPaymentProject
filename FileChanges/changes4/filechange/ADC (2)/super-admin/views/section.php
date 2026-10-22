<?php include __DIR__.'/_super_head.php'; ?>
<h1 class="sa-title"><?=e($heading ?? 'Section')?></h1>
<div class="sa-panel">
    <p><?=e($message ?? 'This section is available in the Super Admin portal.')?></p>
    <?php if (!empty($admin_href)): ?>
    <p><a class="sa-btn" href="<?=e($admin_href)?>" target="_blank" rel="noopener">Open in Operations Admin</a></p>
    <?php endif; ?>
</div>
<?php include __DIR__.'/_super_foot.php'; ?>
