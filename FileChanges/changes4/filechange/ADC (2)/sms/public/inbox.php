<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config.php';
date_default_timezone_set($config['timezone'] ?? 'Asia/Kolkata');

$key = $_GET['key'] ?? '';
if (!hash_equals((string)$config['token'], (string)$key)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$rows = [];
$file = $config['storage_file'];
if (is_file($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach (array_reverse(array_slice($lines, -200)) as $line) {
        $r = json_decode($line, true);
        if (is_array($r)) $rows[] = $r;
    }
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>YEEDIPAY Demo SMS Inbox</title>
<style>
body{font-family:Arial,sans-serif;margin:24px;background:#f6f7f9;color:#111}
.card{background:#fff;border-radius:12px;padding:16px;margin-bottom:12px;box-shadow:0 1px 5px #ddd}
small{color:#666}.msg{white-space:pre-wrap;margin-top:8px}
.badge{display:inline-block;background:#eee;border-radius:999px;padding:4px 8px;font-size:12px}
</style>
</head>
<body>
<h1>YEEDIPAY Demo SMS Inbox</h1>
<p>This is a demo log only. Messages shown here were not delivered to a mobile network.</p>
<?php if (!$rows): ?>
<div class="card">No demo SMS messages yet.</div>
<?php endif; ?>
<?php foreach ($rows as $r): ?>
<div class="card">
  <strong><?=e((string)$r['phone'])?></strong>
  <span class="badge"><?=e((string)$r['delivery_status'])?></span><br>
  <small><?=e((string)$r['created_at'])?> · <?=e((string)($r['message_id'] ?? ''))?></small>
  <div class="msg"><?=e((string)$r['message'])?></div>
</div>
<?php endforeach; ?>
</body>
</html>
