<?php
include __DIR__.'/_portal_head.php';
$qs = http_build_query(['start' => $start, 'end' => $end]);
?>
<section class="yp-sheet">
    <div class="yp-sheet-head">
        <h1><?=e(Portal::t('statement'))?></h1>
        <form class="yp-range" method="get" action="<?=e(url('/c/'.$c['code'].'/statement'))?>">
            <input type="date" name="start" value="<?=e($start)?>" aria-label="From">
            <span>–</span>
            <input type="date" name="end" value="<?=e($end)?>" aria-label="To">
            <button class="yp-btn yp-btn-sm" type="submit">Apply</button>
        </form>
        <a class="yp-btn yp-btn-sm yp-no-print" href="<?=e(url('/c/'.$c['code'].'/statement.csv?'.$qs))?>"><?=e(Portal::t('export'))?></a>
        <a class="yp-btn yp-btn-sm yp-no-print" href="<?=e(url('/c/'.$c['code'].'/statement.pdf?'.$qs))?>">PDF</a>
    </div>
    <div class="yp-table-wrap">
        <table class="yp-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date &amp; Time</th>
                    <th>Transaction ID</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Buyer name</th>
                    <th>UTR number</th>
                    <th>Payment Mode</th>
                </tr>
            </thead>
            <tbody>
            <?php $n = 1; foreach ($rows as $t):
                [$label, $class] = Portal::payStatus($t);
                $raw = TransactionService::displayAmount($t);
                $num = $raw === '' ? 0.0 : (float)str_replace(',', '', $raw);
                $success = strtoupper((string)$t['status']) === 'SUCCESS';
            ?>
                <tr>
                    <td><?=$n++?></td>
                    <td><?=e(Portal::when((string)$t['created_at']))?></td>
                    <td class="mono">
                        <?php if ($success && empty($t['is_razorpay'])): ?>
                        <a href="<?=e(url('/c/'.$c['code'].'/invoice/'.$t['reference']))?>"><?=e($t['reference'])?></a>
                        <?php else: ?>
                        <?=e($t['reference'])?>
                        <?php endif; ?>
                    </td>
                    <td>₹<?=e(Portal::money($num))?></td>
                    <td><span class="<?=e($class)?>"><?=e($label)?></span></td>
                    <td><?=e(TransactionService::displayBuyerName($t))?></td>
                    <td class="mono"><?=e(TransactionService::displayVerifiedUtr($t))?></td>
                    <td><?=e(Portal::payMode($t))?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="8" class="yp-empty">No transactions in this date range.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__.'/_portal_foot.php'; ?>
