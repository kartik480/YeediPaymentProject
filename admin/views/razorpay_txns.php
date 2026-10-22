<?php
include __DIR__.'/_head.php';
$fmt = static function($paise){ return $paise === null ? '—' : '₹'.number_format(((int)$paise)/100, 2); };
$when = static function($v){ return $v ? e((string)$v) : '—'; };
?>
<h1>Razorpay Transactions &amp; Reconciliation</h1>

<div class="card">
    <h2>Summary</h2>
    <p>
        Total: <b><?= (int)$stats['total'] ?></b> ·
        Paid: <b style="color:#16a34a"><?= (int)$stats['paid'] ?></b> ·
        Pending: <b><?= (int)$stats['pending'] ?></b> ·
        Created: <b><?= (int)$stats['created'] ?></b> ·
        Failed: <b><?= (int)$stats['failed'] ?></b> ·
        Cancelled: <b><?= (int)$stats['cancelled'] ?></b> ·
        <span style="color:#dc2626">Needs review: <b><?= (int)$stats['review'] ?></b></span>
    </p>
    <p class="muted">Webhook events: <b><?= (int)$stats['events'] ?></b> ·
        Unprocessed (valid sig): <b><?= (int)$stats['events_unprocessed'] ?></b> ·
        <span style="color:#dc2626">Rejected bad-signature: <b><?= (int)$stats['events_badsig'] ?></b></span></p>
</div>

<div class="card">
    <form method="get" action="<?=e(url('/admin/razorpay'))?>" class="row">
        <input class="grow" name="q" value="<?=e((string)($q ?? ''))?>" placeholder="Search reference / order_id / payment_id / customer / email / contact">
        <select name="status">
            <?php foreach ([''=>'All','CREATED'=>'Created','PENDING'=>'Pending','PAID'=>'Paid','FAILED'=>'Failed','CANCELLED'=>'Cancelled','REVIEW'=>'Review'] as $k=>$v): ?>
            <option value="<?=e($k)?>" <?= (string)($status ?? '')===$k?'selected':'' ?>><?=e($v)?></option>
            <?php endforeach; ?>
        </select>
        <button>Filter</button>
    </form>
</div>

<div class="card">
    <h2>Payments</h2>
    <div style="overflow:auto">
    <table class="tbl">
        <thead><tr>
            <th>Reference</th><th>Status</th><th>Amount</th><th>Captured</th><th>Order ID</th><th>Payment ID</th>
            <th>Customer</th><th>Method</th><th>Verified</th><th>Mode</th><th>Created</th><th>Paid</th><th>Note</th>
        </tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="13" class="muted">No records.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r):
            $st = strtoupper((string)$r['status']);
            $color = $st==='PAID'?'#16a34a':($st==='REVIEW'?'#dc2626':($st==='FAILED'?'#b45309':'#555'));
        ?>
            <tr>
                <td class="mono"><?=e((string)$r['local_reference'])?></td>
                <td><b style="color:<?=$color?>"><?=e($st)?></b></td>
                <td><?=$fmt($r['amount_paise'])?></td>
                <td><?=$fmt($r['captured_amount_paise'])?></td>
                <td class="mono"><?=e((string)$r['rzp_order_id'])?></td>
                <td class="mono"><?=e((string)($r['rzp_payment_id'] ?? ''))?></td>
                <td><?=e((string)($r['customer_code'] ?? ''))?></td>
                <td><?=e((string)($r['method'] ?? ''))?></td>
                <td><?= (int)$r['verified'] ? '✓ '.e((string)($r['verification_source'] ?? '')) : '—' ?></td>
                <td><?= (int)$r['live_mode'] ? 'LIVE' : 'TEST' ?></td>
                <td><?=$when($r['created_at'])?></td>
                <td><?=$when($r['paid_at'])?></td>
                <td class="muted"><?=e((string)($r['verification_note'] ?? ''))?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="card">
    <h2>Webhook / event log</h2>
    <div style="overflow:auto">
    <table class="tbl">
        <thead><tr><th>Event</th><th>Type</th><th>Order</th><th>Payment</th><th>Sig</th><th>Amount</th><th>Processed</th><th>Note</th><th>At</th></tr></thead>
        <tbody>
        <?php if (!$events): ?><tr><td colspan="9" class="muted">No events yet.</td></tr><?php endif; ?>
        <?php foreach ($events as $ev): ?>
            <tr>
                <td class="mono"><?=e((string)$ev['event_id'])?></td>
                <td><?=e((string)$ev['event_type'])?></td>
                <td class="mono"><?=e((string)($ev['rzp_order_id'] ?? ''))?></td>
                <td class="mono"><?=e((string)($ev['rzp_payment_id'] ?? ''))?></td>
                <td><?= (int)$ev['signature_valid'] ? '✓' : '<b style="color:#dc2626">✗</b>' ?></td>
                <td><?=$fmt($ev['amount_paise'])?></td>
                <td><?= (int)$ev['processed'] ? '✓' : '—' ?></td>
                <td class="muted"><?=e((string)($ev['process_note'] ?? ''))?></td>
                <td><?=$when($ev['created_at'])?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__.'/_foot.php'; ?>
