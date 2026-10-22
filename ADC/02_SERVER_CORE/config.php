<?php
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') continue;
        $quote = $value[0] ?? '';
        if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote) && strlen($value) >= 2) {
            $value = substr($value, 1, -1);
        }
        // An already exported variable wins, so a host can override this file.
        if (getenv($key) === false) putenv($key . '=' . $value);
    }
}

$csv = static function (string $key): array {
    $raw = getenv($key);
    if ($raw === false || trim($raw) === '') return [];
    return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn($v) => $v !== ''));
};

$originEnv = trim((string)(getenv('YEEDI_CORS_ALLOWED_ORIGINS') ?: ''));
$origins = $originEnv === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $originEnv))));
$dbPass = getenv('YEEDI_DB_PASS');

return [
    'db' => [
        'dsn'  => getenv('YEEDI_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=yeedipay;charset=utf8mb4',
        'user' => getenv('YEEDI_DB_USER') ?: 'root',
        // Empty string is a real password (local XAMPP root). Set YEEDI_DB_PASS to override.
        'pass' => $dbPass === false ? '' : $dbPass,
    ],

    // Android SMS Relay HMAC security.
    'allowed_clock_skew_seconds' => 300,
    'max_event_age_hours' => 720,

    // Matching window. Bank SMS original received_at is always used.
    'payment_preorder_grace_seconds' => 120,
    'payment_post_expiry_grace_seconds' => 300,

    // Customer payment flow.
    'order_expiry_minutes' => 10,
    'collision_guard_enabled' => true,
    'customer_poll_seconds' => 2,
    'customer_verification_buffer_seconds' => 30,
    'customer_delayed_poll_seconds' => 3,

    // For a production customer dashboard hosted on another origin, set a comma-separated
    // allowlist in YEEDI_CORS_ALLOWED_ORIGINS. Empty = same-origin only.
    'cors_allowed_origins' => $origins,

    // With false, bank SMS must contain a configured receiving account last4.
    'allow_missing_account_mask_for_single_account' => false,

    // FAIL CLOSED. Populate with the exact full sender IDs observed on the relay phone.
    'sender_ids' => [
        'ICICI'    => $csv('YEEDI_SENDER_IDS_ICICI'),
        'INDUSIND' => $csv('YEEDI_SENDER_IDS_INDUSIND'),
        'RBL'      => $csv('YEEDI_SENDER_IDS_RBL'),
    ],
];
