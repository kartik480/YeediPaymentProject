<?php
$viewStep = $viewStep ?? ($stage ?? 'requested');
$doneUntil = ['requested' => 0, 'maker' => 1, 'bank_processing' => 2, 'reconciled' => 3][$stage ?? $viewStep] ?? 0;
$viewIdx = ['requested' => 0, 'maker' => 1, 'bank_processing' => 2, 'reconciled' => 3][$viewStep] ?? $doneUntil;
$steps = [
    ['key' => 'requested', 'label' => 'Requested', 'href' => '/admin/payout/requested'],
    ['key' => 'maker', 'label' => 'Maker approved', 'href' => '/admin/payout/maker-approved'],
    ['key' => 'bank_processing', 'label' => 'Bank processing', 'href' => '/admin/payout/bank-processing'],
    ['key' => 'reconciled', 'label' => 'Reconciled', 'href' => '/admin/payout/reconciled'],
];
?>
<div class="po-steps">
    <?php foreach ($steps as $i => $step):
        $cls = 'todo';
        if ($i === $viewIdx) $cls = 'now';
        elseif ($i < $doneUntil || $i < $viewIdx) $cls = 'done';
    ?>
    <a class="po-step <?=$cls?>" href="<?=e(url($step['href']))?>">
        <span class="po-dot"><?=$cls === 'done' ? '✓' : ''?></span>
        <?=e($step['label'])?>
    </a>
    <?php endforeach; ?>
</div>
