<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db.php';

$bank = normalize_bank((string)($argv[1] ?? ''));
$key = trim((string)($argv[2] ?? ''));
$label = trim((string)($argv[3] ?? ''));
$last4 = preg_replace('/\D/', '', (string)($argv[4] ?? ''));
$upiId = trim((string)($argv[5] ?? ''));
$payeeName = trim((string)($argv[6] ?? 'YEEDI'));
$qrImageUrl = trim((string)($argv[7] ?? ''));

if (!in_array($bank, ['ICICI','INDUSIND','RBL'], true)) { fwrite(STDERR, "Bank must be ICICI, INDUSIND or RBL\n"); exit(1); }
if (!preg_match('/^[A-Z0-9_-]{3,64}$/i', $key) || $label === '') { fwrite(STDERR, "Invalid account key/label\n"); exit(1); }
if (!preg_match('/^\d{4}$/', $last4)) { fwrite(STDERR, "Last4 must be exactly 4 digits\n"); exit(1); }
if ($upiId !== '' && !preg_match('/^[A-Za-z0-9._-]{2,80}@[A-Za-z0-9.-]{2,40}$/', $upiId)) { fwrite(STDERR, "Invalid UPI ID\n"); exit(1); }
if ($qrImageUrl !== '' && !preg_match('#^https://#i', $qrImageUrl) && $qrImageUrl[0] !== '/') { fwrite(STDERR, "QR image URL must be https:// or an absolute path beginning /\n"); exit(1); }

$q = yeedi_db()->prepare(
    'INSERT INTO bank_receiving_accounts (account_key, bank_name, account_label, account_last4, upi_id, payee_name, qr_image_url, active)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
);
$q->execute([$key, $bank, $label, $last4, $upiId ?: null, $payeeName ?: null, $qrImageUrl ?: null]);
echo "Added: {$key} / {$bank} / last4={$last4} / UPI=" . ($upiId ?: 'NOT SET') . "\n";
