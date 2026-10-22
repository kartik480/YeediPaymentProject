<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/payment_matcher.php';

$hours = (int)($argv[1] ?? 24);
$cfg = yeedi_config();
$maxHours = max(1, (int)($cfg['max_event_age_hours'] ?? 24));
$hours = max(1, min($hours, $maxHours));
$pdo = yeedi_db();

// The lookback hour value is clamped to an integer above before interpolation.
$q = $pdo->query(
    "SELECT id FROM bank_sms_events
     WHERE matched_payment_id IS NULL
       AND match_state = 'UNMATCHED'
       AND received_at >= (UTC_TIMESTAMP() - INTERVAL {$hours} HOUR)
     ORDER BY received_at, id"
);

$total = 0; $matched = 0; $ambiguous = 0; $unmatched = 0; $errors = 0;
foreach ($q->fetchAll() as $row) {
    $total++;
    $r = auto_match_bank_event((int)$row['id']);
    if (empty($r['ok'])) { $errors++; continue; }
    $state = $r['match_state'] ?? '';
    if ($state === 'MATCHED') $matched++;
    elseif ($state === 'AMBIGUOUS') $ambiguous++;
    else $unmatched++;
}

echo json_encode([
    'ok'=>($errors===0),
    'hours'=>$hours,
    'processed'=>$total,
    'matched'=>$matched,
    'ambiguous'=>$ambiguous,
    'unmatched'=>$unmatched,
    'errors'=>$errors,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit($errors === 0 ? 0 : 2);
