<?php
function yeedi_config(): array {
    static $cfg = null;
    if ($cfg === null) $cfg = require __DIR__ . '/config.php';
    return $cfg;
}

function yeedi_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $cfg = yeedi_config()['db'];
    $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_body(int $maxBytes = 16384): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > $maxBytes) json_response(['ok'=>false,'error'=>'invalid_body'], 400);
    if (trim($raw) === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) json_response(['ok'=>false,'error'=>'invalid_json'], 400);
    return $data;
}

function normalize_bank(string $bank): string {
    return strtoupper(trim($bank));
}

function normalize_reference(string $reference): string {
    return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $reference));
}

function normalize_amount_2(string $value): ?string {
    $value = str_replace(',', '', trim($value));
    if (!preg_match('/^(0|[1-9]\d{0,11})(?:\.(\d{1,2}))?$/', $value, $m)) return null;
    $whole = $m[1];
    $fraction = str_pad($m[2] ?? '', 2, '0');
    $out = $whole . '.' . $fraction;
    return $out === '0.00' ? null : $out;
}

function html_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function random_token(int $bytes = 32): string {
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function public_token_hash(string $token): string {
    return hash('sha256', $token);
}

function request_header(string $name): string {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

function apply_public_api_cors(): void {
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') return;
    $allowed = yeedi_config()['cors_allowed_origins'] ?? [];
    if (in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }
}

function build_upi_uri(?string $upiId, ?string $payeeName, string $amount, string $paymentId): ?string {
    $upiId = trim((string)$upiId);
    if ($upiId === '') return null;
    $params = [
        'pa' => $upiId,
        'pn' => trim((string)$payeeName) !== '' ? trim((string)$payeeName) : 'YEEDI',
        'am' => $amount,
        'cu' => 'INR',
        'tn' => 'YEEDI ' . $paymentId,
    ];
    return 'upi://pay?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}
