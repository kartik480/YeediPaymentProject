<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db.php';

$name        = trim((string)($argv[1] ?? 'YEEDI CUSTOMER DASHBOARD'));
$displayName = trim((string)($argv[2] ?? ''));
$logoUrl     = trim((string)($argv[3] ?? ''));
$themeColor  = trim((string)($argv[4] ?? '#FF6B35'));
$whatsapp    = trim((string)($argv[5] ?? ''));
$email       = trim((string)($argv[6] ?? ''));
$videoUrl    = trim((string)($argv[7] ?? ''));

$clientId = 'YMC-' . strtoupper(bin2hex(random_bytes(4)));
$secret = bin2hex(random_bytes(32));
$q = yeedi_db()->prepare(
    'INSERT INTO merchant_clients
     (client_id, client_name, display_name, logo_url, theme_color, support_whatsapp, support_email, video_url, api_secret, active)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
);
$q->execute([
    $clientId, $name,
    $displayName ?: null, $logoUrl ?: null, $themeColor,
    $whatsapp ?: null, $email ?: null, $videoUrl ?: null,
    $secret,
]);
echo "CLIENT ID:    {$clientId}\n";
echo "API SECRET:   {$secret}\n";
echo "DISPLAY NAME: " . ($displayName ?: $name) . "\n";
echo "THEME COLOR:  {$themeColor}\n";
echo "\nUsage: php provision_merchant.php NAME [DISPLAY_NAME] [LOGO_URL] [THEME_COLOR] [WHATSAPP] [EMAIL] [VIDEO_URL]\n";
echo "Use CLIENT ID in the payment page URL: ?client_id={$clientId}\n";
echo "Use API SECRET only on the merchant server side. Never expose it in browser JavaScript.\n";
