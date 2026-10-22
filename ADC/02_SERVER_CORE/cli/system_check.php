<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db.php';

try {
    $pdo = yeedi_db();
    $cfg = yeedi_config();
    $fail = [];
    $warn = [];
    echo "YEEDI V5 CORE DEVELOPED - SYSTEM CHECK\n";
    echo "DB connection: PASS\n";

    foreach (['sms_relay_devices','merchant_clients','bank_receiving_accounts','bank_sms_events','qr_payment_orders'] as $t) {
        try { $pdo->query("SELECT 1 FROM `{$t}` LIMIT 1"); echo "Table {$t}: PASS\n"; }
        catch (Throwable $e) { echo "Table {$t}: FAIL\n"; $fail[] = "missing table {$t}"; }
    }

    $requiredColumns = [
        'bank_receiving_accounts'=>['upi_id','payee_name','qr_image_url','sort_order'],
        'qr_payment_orders'=>['merchant_client_id','merchant_order_id','public_token_hash','customer_ack_at','last_status_poll_at'],
    ];
    foreach ($requiredColumns as $table=>$cols) {
        $rows = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
        $have = array_column($rows, 'Field');
        foreach ($cols as $col) {
            if (in_array($col, $have, true)) echo "Column {$table}.{$col}: PASS\n";
            else { echo "Column {$table}.{$col}: FAIL\n"; $fail[] = "missing {$table}.{$col}"; }
        }
    }

    $accounts = $pdo->query("SELECT bank_name, account_key, account_last4, upi_id, active FROM bank_receiving_accounts ORDER BY sort_order,id")->fetchAll();
    $activeBanks = [];
    foreach ($accounts as $a) {
        if ((int)$a['active'] !== 1) continue;
        $bank = normalize_bank($a['bank_name']);
        $activeBanks[$bank] = true;
        echo "Account {$a['account_key']} / {$bank} / last4=" . ($a['account_last4'] ?: 'MISSING') . " / UPI=" . ($a['upi_id'] ?: 'NOT SET') . "\n";
        if (empty($a['account_last4']) && empty($cfg['allow_missing_account_mask_for_single_account'])) $fail[] = "account {$a['account_key']} missing last4";
        if (empty($a['upi_id'])) $warn[] = "account {$a['account_key']} has no UPI ID (UPI intent button unavailable)";
    }
    if (!$activeBanks) $fail[] = 'no active receiving account';

    foreach (array_keys($activeBanks) as $bank) {
        $ids = array_values(array_filter(array_map(fn($v)=>trim((string)$v), $cfg['sender_ids'][$bank] ?? [])));
        echo "Sender allowlist {$bank}: " . count($ids) . "\n";
        if (!$ids) $fail[] = "sender allowlist empty for {$bank}";
    }

    $dev = $pdo->query("SELECT device_id, active, last_seen_at FROM sms_relay_devices ORDER BY id")->fetchAll();
    $activeDevices = 0;
    foreach ($dev as $d) {
        if ((int)$d['active'] === 1) $activeDevices++;
        echo "Relay {$d['device_id']} / active={$d['active']} / last_seen=" . ($d['last_seen_at'] ?: 'NEVER') . "\n";
        if ((int)$d['active'] === 1 && empty($d['last_seen_at'])) $warn[] = "relay {$d['device_id']} has never connected";
    }
    if (!$activeDevices) $fail[] = 'no active relay device';

    $merchants = (int)$pdo->query("SELECT COUNT(*) FROM merchant_clients WHERE active=1")->fetchColumn();
    echo "Active merchant clients: {$merchants}\n";
    if ($merchants === 0) $warn[] = 'no merchant client yet; run cli/provision_merchant.php';

    echo "Collision guard: " . (!empty($cfg['collision_guard_enabled']) ? 'ON' : 'OFF') . "\n";
    echo "SUCCESS authority: VERIFIED SIGNED BANK CREDIT SMS ONLY\n";

    foreach ($warn as $w) echo "WARNING: {$w}\n";
    if ($fail) {
        foreach ($fail as $f) echo "FAIL: {$f}\n";
        exit(2);
    }
    echo "CORE READINESS: PASS (real bank SMS UAT still mandatory)\n";
} catch (Throwable $e) {
    fwrite(STDERR, "System check failed: {$e->getMessage()}\n");
    exit(1);
}
