<?php include __DIR__.'/_super_head.php';
$form = $form ?? [
    'code' => '',
    'bank_name' => '',
    'vpa' => '',
    'payee_name' => '',
    'priority' => '100',
    'verification_mode' => 'MANUAL',
    'webhook_secret' => '',
];
?>
<?php if (!empty($error)): ?><div class="sa-error"><?=e($error)?></div><?php endif; ?>
<?php
$routeDb = Database::pdo();
$cfg = $cfg ?? ($routeDb->query('SELECT * FROM system_config WHERE id=1')->fetch() ?: []);
$active = $active ?? BankService::active($routeDb);
$rot = $rot ?? BankService::rotationInfo($routeDb);
$routingMode = !empty($cfg['rotation_enabled']) ? 'AUTO' : 'MANUAL';
$manualId = (int)($cfg['manual_active_bank_id'] ?? ($active['id'] ?? 0));
$minsLeft = (int)ceil(($rot['seconds_left'] ?? 0) / 60);
$enabledCount = 0;
foreach ($banks ?? [] as $b) { if ((int)($b['enabled'] ?? 0) === 1) $enabledCount++; }
?>

<div class="sa-panel sa-bank-form">
    <h2 class="sa-section-title">Bank switch</h2>
    <?php if (!empty($rot['on'])): ?>
    <div class="sa-ok">Auto-switch is on. Live now: <b><?=e((string)($rot['active']['bank_name'] ?? ''))?></b><?php if (!empty($rot['next'])): ?> · next <b><?=e((string)$rot['next']['bank_name'])?></b> in about <b><?=e((string)max(1, $minsLeft))?> min</b><?php endif; ?>.</div>
    <?php else: ?>
    <div class="sa-links-note">Manual mode. Live now: <b><?=e((string)($active['bank_name'] ?? 'none'))?></b>. It stays on this bank until you switch it or turn on hourly auto.</div>
    <?php endif; ?>
    <form method="post" action="<?=e(url('/super-admin/banks/routing'))?>" class="sa-route-form">
        <?=csrf_field()?>
        <label>Mode
            <select name="routing_mode">
                <option value="AUTO" <?=$routingMode==='AUTO'?'selected':''?>>AUTO — switch every 1 hour</option>
                <option value="MANUAL" <?=$routingMode==='MANUAL'?'selected':''?>>MANUAL — stay on one bank</option>
            </select>
        </label>
        <label>Bank
            <select name="manual_active_bank_id">
                <option value="">First enabled bank</option>
                <?php foreach ($banks ?? [] as $b): if ((int)($b['enabled'] ?? 0) !== 1) continue; ?>
                <option value="<?=e((string)$b['id'])?>" <?=$manualId===(int)$b['id']?'selected':''?>><?=e($b['bank_name'])?> — <?=e($b['code'])?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="sa-filter-btn">Apply</button>
    </form>
    <p class="sa-links-note">AUTO uses each enabled bank for 1 hour, in priority order, then repeats. MANUAL, or Use now, holds one bank. Customer payment links do not change.<?=$enabledCount < 2 ? ' Enable at least 2 banks before hourly switch can move.' : ''?></p>
</div>

<div class="sa-panel sa-bank-form">
    <h2 class="sa-section-title">Add Bank Route</h2>
    <form method="post" action="<?=e(url('/super-admin/banks'))?>" enctype="multipart/form-data" autocomplete="off">
        <?=csrf_field()?>
        <input type="hidden" name="upi_uri" id="upi_uri" value="">
        <div class="sa-form-grid">
            <label>Code
                <input name="code" value="<?=e($form['code'])?>" placeholder="INDUSIND">
            </label>
            <label>Bank Name
                <input name="bank_name" value="<?=e($form['bank_name'])?>" required>
            </label>
            <label>UPI VPA <span class="sa-hint-red">CHANGE TO LIVE VPA</span>
                <input name="vpa" id="bank_vpa" value="<?=e($form['vpa'])?>" placeholder="Pos.11427319@indus">
            </label>
            <label>Payee Name <span class="sa-hint-red">VERIFY WITH BANK</span>
                <input name="payee_name" id="bank_pn" value="<?=e($form['payee_name'])?>">
            </label>
            <label>Priority
                <input name="priority" type="number" min="1" value="<?=e($form['priority'])?>">
            </label>
            <label>Verification Mode
                <select name="verification_mode">
                    <option value="MANUAL" <?=($form['verification_mode'] ?? 'MANUAL') === 'MANUAL' ? 'selected' : ''?>>MANUAL (use now)</option>
                    <option value="API" <?=($form['verification_mode'] ?? '') === 'API' ? 'selected' : ''?>>API (only after bank docs)</option>
                </select>
            </label>
            <label>Webhook Secret <span class="sa-hint-red">LATER: IndusInd webhook</span>
                <input name="webhook_secret" value="<?=e($form['webhook_secret'])?>" placeholder="Optional">
            </label>
        </div>
        <p class="sa-qr-status" id="qrDecodeStatus"></p>
        <button type="submit" class="sa-filter-btn sa-create-btn">Add Route</button>
    </form>
</div>

<div class="sa-panel sa-links-panel">
    <div class="sa-txn-table-wrap scroll">
        <table class="sa-txn-table sa-bank-table">
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>VPA</th>
                    <th>Mode</th>
                    <th>Live</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($banks ?? [] as $b): ?>
                <?php
                $mode = SuperAdminService::bankVerificationMode($b);
                $isLive = $active && (int)$active['id'] === (int)$b['id'];
                ?>
                <tr>
                    <td><?=e((string)($b['rotation_order'] ?? ''))?></td>
                    <td class="mono"><?=e($b['code'])?></td>
                    <td><?=e($b['bank_name'])?></td>
                    <td class="mono"><?=e($b['vpa'])?></td>
                    <td><span class="sa-mode-pill"><?=e($mode)?></span></td>
                    <td><?php if ($isLive): ?><span class="sa-live-pill">LIVE</span><?php elseif ((int)($b['enabled'] ?? 0) !== 1): ?><span class="sa-empty">Off</span><?php else: ?>—<?php endif; ?></td>
                    <td>
                        <?php if (!$isLive): ?>
                        <form method="post" action="<?=e(url('/super-admin/banks/'.$b['id'].'/activate'))?>">
                            <?=csrf_field()?>
                            <button type="submit" class="sa-use-btn">Use now</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($banks)): ?>
                <tr><td colspan="7" class="sa-empty">No bank routes yet. Add one above.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__.'/_super_foot.php'; ?>
