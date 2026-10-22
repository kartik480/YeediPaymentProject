<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/payment_matcher.php';

$eventId = (int)($argv[1] ?? 0);
$paymentId = trim((string)($argv[2] ?? ''));
if ($eventId <= 0 || $paymentId === '') {
    fwrite(STDERR, "Usage: php manual_match_cli.php EVENT_DB_ID PAYMENT_ID\n");
    exit(1);
}

$result = manual_match_event_to_payment($eventId, $paymentId);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit(!empty($result['ok']) ? 0 : 2);
