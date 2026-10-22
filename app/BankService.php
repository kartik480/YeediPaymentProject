<?php
declare(strict_types=1);
final class BankService {
    public const DEFAULT_ROTATION_MINUTES = 30;
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $db): void {
        if (self::$schemaReady) return;
        $cols = [];
        foreach ($db->query('SHOW COLUMNS FROM bank_profiles') as $r) {
            $cols[strtolower((string)$r['Field'])] = true;
        }
        $add = [
            'mcc' => 'VARCHAR(16) NULL',
            'provider_name' => 'VARCHAR(120) NULL',
            'provider_base_url' => 'VARCHAR(255) NULL',
            'create_order_path' => 'VARCHAR(255) NULL',
            'status_path' => 'VARCHAR(255) NULL',
            'refund_path' => 'VARCHAR(255) NULL',
            'api_key_encrypted' => 'TEXT NULL',
            'api_secret_encrypted' => 'TEXT NULL',
            'qr_image_path' => 'VARCHAR(255) NULL',
        ];
        foreach ($add as $col => $def) {
            if (!isset($cols[$col])) {
                $db->exec('ALTER TABLE bank_profiles ADD COLUMN `'.$col.'` '.$def);
            }
        }
        self::$schemaReady = true;
    }

    public static function parseUpiUri(string $uri): array {
        $uri = trim($uri);
        $p = parse_url($uri);
        if (!$p || strtolower((string)($p['scheme'] ?? '')) !== 'upi' || strtolower((string)($p['host'] ?? '')) !== 'pay') {
            throw new InvalidArgumentException('QR is not a standard upi://pay URI');
        }
        parse_str((string)($p['query'] ?? ''), $params);
        $vpa = trim((string)($params['pa'] ?? ''));
        $pn = trim((string)($params['pn'] ?? ''));
        if ($vpa === '' || !str_contains($vpa, '@')) throw new InvalidArgumentException('UPI payee address (pa) not found');
        if ($pn === '') $pn = $vpa;
        $signed = false;
        foreach (array_keys($params) as $k) {
            if (in_array(strtolower((string)$k), ['sign', 'signature'], true)) { $signed = true; break; }
        }
        return ['uri' => $uri, 'params' => $params, 'vpa' => $vpa, 'payee_name' => $pn, 'currency' => (string)($params['cu'] ?? 'INR'), 'mcc' => trim((string)($params['mc'] ?? '')), 'signed' => $signed];
    }

    public static function buildUpiUri(string $vpa, string $payeeName, string $mcc = '', string $currency = 'INR'): string {
        $parts = ['pa='.rawurlencode($vpa), 'pn='.rawurlencode($payeeName), 'cu='.rawurlencode($currency !== '' ? $currency : 'INR')];
        if ($mcc !== '') $parts[] = 'mc='.rawurlencode($mcc);
        return 'upi://pay?'.implode('&', $parts);
    }

    /** MCC from bank row or stored QR params (mc=). */
    public static function bankMcc(array $bank): string {
        $mcc = trim((string)($bank['mcc'] ?? ''));
        if ($mcc !== '') {
            return $mcc;
        }
        if (!empty($bank['qr_params_json'])) {
            $params = json_decode((string)$bank['qr_params_json'], true) ?: [];
            return trim((string)($params['mc'] ?? ''));
        }
        return '';
    }

    public static function inputFromPost(array $post): array {
        return [
            'code' => trim((string)($post['code'] ?? '')),
            'bank_name' => trim((string)($post['bank_name'] ?? '')),
            'upi_uri' => trim((string)($post['upi_uri'] ?? '')),
            'vpa' => trim((string)($post['vpa'] ?? '')),
            'payee_name' => trim((string)($post['payee_name'] ?? '')),
            'mcc' => trim((string)($post['mcc'] ?? '')),
            'rotation_order' => max(1, (int)($post['rotation_order'] ?? 1)),
            'rotation_minutes' => max(1, (int)($post['rotation_minutes'] ?? self::DEFAULT_ROTATION_MINUTES)),
            'enabled' => isset($post['enabled']) ? 1 : 0,
            'provider_name' => trim((string)($post['provider_name'] ?? '')),
            'provider_base_url' => trim((string)($post['provider_base_url'] ?? '')),
            'create_order_path' => trim((string)($post['create_order_path'] ?? '')),
            'status_path' => trim((string)($post['status_path'] ?? '')),
            'refund_path' => trim((string)($post['refund_path'] ?? '')),
            'api_key' => (string)($post['api_key'] ?? ''),
            'api_secret' => (string)($post['api_secret'] ?? ''),
            'webhook_secret' => (string)($post['webhook_secret'] ?? ''),
        ];
    }

    public static function resolvePayload(array $in, ?array $existing = null): array {
        $uri = trim((string)($in['upi_uri'] ?? ''));
        $vpa = trim((string)($in['vpa'] ?? ''));
        $pn = trim((string)($in['payee_name'] ?? ''));
        $mcc = trim((string)($in['mcc'] ?? ''));
        if ($uri !== '') {
            $p = self::parseUpiUri($uri);
            if ($vpa === '') $vpa = $p['vpa'];
            if ($pn === '') $pn = $p['payee_name'];
            if ($mcc === '') $mcc = $p['mcc'];
            if ($vpa === '' || !str_contains($vpa, '@')) throw new InvalidArgumentException('UPI ID / VPA is required');
            if ($pn === '') $pn = $vpa;
            $sameAsUri = $vpa === $p['vpa'] && $pn === $p['payee_name'] && $mcc === $p['mcc'];
            if ($sameAsUri) {
                return ['uri' => $p['uri'], 'params' => $p['params'], 'vpa' => $vpa, 'payee_name' => $pn, 'currency' => $p['currency'], 'mcc' => $mcc, 'signed' => $p['signed']];
            }
            $uri = self::buildUpiUri($vpa, $pn, $mcc, $p['currency'] ?: 'INR');
            $p = self::parseUpiUri($uri);
            $p['mcc'] = $mcc;
            return $p;
        }
        if ($existing && !empty($existing['qr_payload'])) {
            $keep = true;
            if ($vpa !== '' && $vpa !== (string)$existing['vpa']) $keep = false;
            if ($pn !== '' && $pn !== (string)$existing['payee_name']) $keep = false;
            $oldMcc = trim((string)($existing['mcc'] ?? ''));
            if ($oldMcc === '' && !empty($existing['qr_params_json'])) {
                $oldParams = json_decode((string)$existing['qr_params_json'], true) ?: [];
                $oldMcc = trim((string)($oldParams['mc'] ?? ''));
            }
            if ($mcc !== $oldMcc) $keep = false;
            if ($keep) {
                $p = self::parseUpiUri((string)$existing['qr_payload']);
                return ['uri' => $p['uri'], 'params' => $p['params'], 'vpa' => $vpa !== '' ? $vpa : $p['vpa'], 'payee_name' => $pn !== '' ? $pn : $p['payee_name'], 'currency' => $p['currency'], 'mcc' => $mcc, 'signed' => $p['signed']];
            }
        }
        if ($vpa === '' || !str_contains($vpa, '@')) throw new InvalidArgumentException('UPI ID / VPA is required (or paste an original upi:// URI)');
        if ($pn === '') throw new InvalidArgumentException('Payee Name is required');
        $currency = 'INR';
        $uri = self::buildUpiUri($vpa, $pn, $mcc, $currency);
        $p = self::parseUpiUri($uri);
        $p['mcc'] = $mcc;
        return $p;
    }

    public static function assertUniqueCode(PDO $db, string $desired, int $exceptId = 0): string {
        $code = substr(normalize_code($desired), 0, 32);
        if ($code === '') throw new InvalidArgumentException('Bank Code is required');
        $st = $db->prepare('SELECT id FROM bank_profiles WHERE code=?');
        $st->execute([$code]);
        $found = $st->fetch();
        if ($found && (int)$found['id'] !== $exceptId) {
            throw new InvalidArgumentException('Bank code already exists');
        }
        return $code;
    }

    public static function uniqueCode(PDO $db, string $desired, int $exceptId = 0): string {
        $base = substr(normalize_code($desired) ?: 'BANK', 0, 20);
        $st = $db->prepare('SELECT id FROM bank_profiles WHERE code=?');
        $st->execute([$base]);
        $found = $st->fetch();
        if (!$found || ($exceptId > 0 && (int)$found['id'] === $exceptId)) return $base;
        $code = $base;
        $n = 2;
        while (true) {
            $code = substr($base, 0, 16).$n;
            $st->execute([$code]);
            $found = $st->fetch();
            if (!$found || ($exceptId > 0 && (int)$found['id'] === $exceptId)) return $code;
            $n++;
        }
    }

    private static function requireName(array $in): string {
        $name = trim((string)($in['bank_name'] ?? ''));
        if ($name === '') throw new InvalidArgumentException('Bank Name is required');
        return $name;
    }

    public static function add(PDO $db, array $in): int {
        self::ensureSchema($db);
        $name = self::requireName($in);
        $p = self::resolvePayload($in);
        $code = self::assertUniqueCode($db, (string)($in['code'] ?? '') ?: $name);
        $secret = trim((string)($in['webhook_secret'] ?? ''));
        if ($secret === '') $secret = bin2hex(random_bytes(24));
        $enabled = array_key_exists('enabled', $in) ? (int)(bool)$in['enabled'] : 1;
        $st = $db->prepare('INSERT INTO bank_profiles(code,bank_name,label,vpa,payee_name,currency,qr_payload,qr_params_json,enabled,rotation_order,rotation_minutes,signed_qr,webhook_secret,mcc,provider_name,provider_base_url,create_order_path,status_path,refund_path,api_key_encrypted,api_secret_encrypted,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
        $st->execute([
            $code,
            $name,
            trim((string)($in['label'] ?? '')),
            $p['vpa'],
            $p['payee_name'],
            $p['currency'],
            $p['uri'],
            json_encode($p['params'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $enabled,
            max(1, (int)($in['rotation_order'] ?? 1)),
            max(1, (int)($in['rotation_minutes'] ?? self::DEFAULT_ROTATION_MINUTES)),
            $p['signed'] ? 1 : 0,
            $secret,
            $p['mcc'] !== '' ? $p['mcc'] : null,
            self::nullIfBlank($in['provider_name'] ?? ''),
            self::nullIfBlank($in['provider_base_url'] ?? ''),
            self::nullIfBlank($in['create_order_path'] ?? ''),
            self::nullIfBlank($in['status_path'] ?? ''),
            self::nullIfBlank($in['refund_path'] ?? ''),
            self::encryptIfPresent((string)($in['api_key'] ?? '')),
            self::encryptIfPresent((string)($in['api_secret'] ?? '')),
        ]);
        $id = (int)$db->lastInsertId();
        $cfg = $db->query('SELECT manual_active_bank_id FROM system_config WHERE id=1')->fetch();
        if (!$cfg) {
            $db->exec('INSERT INTO system_config(id,rotation_enabled,updated_at) VALUES(1,0,UTC_TIMESTAMP())');
            $cfg = ['manual_active_bank_id' => null];
        }
        if (empty($cfg['manual_active_bank_id']) && $enabled) {
            $st = $db->prepare('UPDATE system_config SET manual_active_bank_id=?,updated_at=UTC_TIMESTAMP() WHERE id=1');
            $st->execute([$id]);
        }
        return $id;
    }

    public static function update(PDO $db, int $id, array $in): void {
        self::ensureSchema($db);
        $existing = self::get($db, $id);
        if (!$existing) throw new RuntimeException('Bank not found');
        $name = self::requireName($in);
        $p = self::resolvePayload($in, $existing);
        $code = self::assertUniqueCode($db, (string)($in['code'] ?? '') ?: $name, $id);
        $secret = trim((string)($in['webhook_secret'] ?? ''));
        if ($secret === '') $secret = (string)$existing['webhook_secret'];
        $apiKey = trim((string)($in['api_key'] ?? ''));
        $apiSecret = trim((string)($in['api_secret'] ?? ''));
        $st = $db->prepare('UPDATE bank_profiles SET code=?,bank_name=?,vpa=?,payee_name=?,currency=?,qr_payload=?,qr_params_json=?,enabled=?,rotation_order=?,rotation_minutes=?,signed_qr=?,webhook_secret=?,mcc=?,provider_name=?,provider_base_url=?,create_order_path=?,status_path=?,refund_path=?,api_key_encrypted=?,api_secret_encrypted=? WHERE id=?');
        $st->execute([
            $code,
            $name,
            $p['vpa'],
            $p['payee_name'],
            $p['currency'],
            $p['uri'],
            json_encode($p['params'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            array_key_exists('enabled', $in) ? (int)(bool)$in['enabled'] : (int)$existing['enabled'],
            max(1, (int)($in['rotation_order'] ?? $existing['rotation_order'])),
            max(1, (int)($in['rotation_minutes'] ?? $existing['rotation_minutes'])),
            $p['signed'] ? 1 : 0,
            $secret,
            $p['mcc'] !== '' ? $p['mcc'] : null,
            self::nullIfBlank($in['provider_name'] ?? ''),
            self::nullIfBlank($in['provider_base_url'] ?? ''),
            self::nullIfBlank($in['create_order_path'] ?? ''),
            self::nullIfBlank($in['status_path'] ?? ''),
            self::nullIfBlank($in['refund_path'] ?? ''),
            $apiKey !== '' ? Crypto::encrypt($apiKey) : ($existing['api_key_encrypted'] ?? null),
            $apiSecret !== '' ? Crypto::encrypt($apiSecret) : ($existing['api_secret_encrypted'] ?? null),
            $id,
        ]);
    }

    public static function applyRouting(PDO $db, string $mode, int $bankId): void {
        self::ensureSchema($db);
        $mode = strtoupper(trim($mode));
        if ($mode === 'AUTO') {
            if (!(int)$db->query('SELECT COUNT(*) FROM bank_profiles WHERE enabled=1')->fetchColumn()) {
                throw new RuntimeException('No enabled banks to rotate');
            }
            $db->exec('UPDATE bank_profiles SET rotation_minutes='.self::DEFAULT_ROTATION_MINUTES.' WHERE enabled=1');
            $banks = $db->query('SELECT id FROM bank_profiles WHERE enabled=1 ORDER BY rotation_order,id')->fetchAll();
            $offset = 0;
            $found = false;
            foreach ($banks as $b) {
                if ($bankId > 0 && (int)$b['id'] === $bankId) {
                    $found = true;
                    break;
                }
                $offset += 3600;
            }
            if (!$found) $offset = 0;
            $start = gmdate('Y-m-d H:i:s', time() - $offset);
            $st = $db->prepare('UPDATE system_config SET rotation_enabled=1,rotation_started_at=?,manual_active_bank_id=?,updated_at=UTC_TIMESTAMP() WHERE id=1');
            $st->execute([$start, $found ? $bankId : null]);
            return;
        }
        if ($bankId < 1) throw new RuntimeException('Select a bank for MANUAL routing');
        $b = self::get($db, $bankId);
        if (!$b) throw new RuntimeException('Bank not found');
        $db->prepare('UPDATE bank_profiles SET enabled=1 WHERE id=?')->execute([$bankId]);
        $db->prepare('UPDATE system_config SET manual_active_bank_id=?,rotation_enabled=0,updated_at=UTC_TIMESTAMP() WHERE id=1')->execute([$bankId]);
    }

    public static function all(PDO $db): array {
        self::ensureSchema($db);
        return $db->query('SELECT * FROM bank_profiles ORDER BY rotation_order,id')->fetchAll();
    }

    /** Drop unused bank profile, or disable if it already has transactions. */
    public static function remove(PDO $db, int $id): void {
        self::ensureSchema($db);
        $b = self::get($db, $id);
        if (!$b) {
            throw new RuntimeException('Bank not found');
        }
        $active = self::active($db);
        if ($active && (int)$active['id'] === $id) {
            throw new RuntimeException('Cannot remove the active bank. Make another bank active first.');
        }
        $cfg = $db->query('SELECT manual_active_bank_id FROM system_config WHERE id=1')->fetch() ?: [];
        if ((int)($cfg['manual_active_bank_id'] ?? 0) === $id) {
            $db->exec('UPDATE system_config SET manual_active_bank_id=NULL,updated_at=UTC_TIMESTAMP() WHERE id=1');
        }
        $st = $db->prepare('SELECT COUNT(*) FROM transactions WHERE bank_id=?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            $db->prepare('UPDATE bank_profiles SET enabled=0 WHERE id=?')->execute([$id]);
            return;
        }
        $db->prepare('DELETE FROM bank_profiles WHERE id=?')->execute([$id]);
    }

    public static function get(PDO $db, int $id): ?array {
        self::ensureSchema($db);
        $st = $db->prepare('SELECT * FROM bank_profiles WHERE id=?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function active(PDO $db, ?DateTimeImmutable $at = null): ?array {
        self::ensureSchema($db);
        self::ensureHourlyRotation($db);
        $cfg = $db->query('SELECT * FROM system_config WHERE id=1')->fetch();
        if (!$cfg) {
            $db->exec('INSERT INTO system_config(id,rotation_enabled,updated_at) VALUES(1,0,UTC_TIMESTAMP())');
            $cfg = ['rotation_enabled' => 0, 'manual_active_bank_id' => null, 'rotation_started_at' => null];
        }
        $banks = $db->query('SELECT * FROM bank_profiles WHERE enabled=1 ORDER BY rotation_order,id')->fetchAll();
        if (!$banks) return null;
        if (!(int)$cfg['rotation_enabled'] || count($banks) < 2) {
            if (!empty($cfg['manual_active_bank_id'])) {
                foreach ($banks as $b) if ((int)$b['id'] === (int)$cfg['manual_active_bank_id']) return $b;
            }
            return $banks[0];
        }
        $rot = array_values(array_filter($banks, fn($b) => (int)$b['rotation_minutes'] > 0));
        if (!$rot) return $banks[0];
        $start = !empty($cfg['rotation_started_at']) ? new DateTimeImmutable((string)$cfg['rotation_started_at'], new DateTimeZone('UTC')) : new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $now = $at ?: new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $elapsed = max(0, $now->getTimestamp() - $start->getTimestamp());
        $total = array_sum(array_map(fn($b) => max(1, (int)$b['rotation_minutes']) * 60, $rot));
        $pos = $total ? ($elapsed % $total) : 0;
        $cursor = 0;
        foreach ($rot as $b) {
            $cursor += max(1, (int)$b['rotation_minutes']) * 60;
            if ($pos < $cursor) return $b;
        }
        return $rot[0];
    }

    /** Keep rotation slots at 30 minutes when 2+ banks are enabled. Does not override MANUAL mode. */
    public static function ensureHourlyRotation(PDO $db): void {
        self::ensureSchema($db);
        $n = (int)$db->query('SELECT COUNT(*) FROM bank_profiles WHERE enabled=1')->fetchColumn();
        if ($n < 2) return;
        $db->exec('UPDATE bank_profiles SET rotation_minutes='.self::DEFAULT_ROTATION_MINUTES.' WHERE enabled=1 AND (rotation_minutes IS NULL OR rotation_minutes<>'.self::DEFAULT_ROTATION_MINUTES.')');
    }

    /** @return array{on:bool,seconds_left:int,next:?array,active:?array} */
    public static function rotationInfo(PDO $db): array {
        $active = self::active($db);
        $cfg = $db->query('SELECT * FROM system_config WHERE id=1')->fetch() ?: [];
        $banks = $db->query('SELECT * FROM bank_profiles WHERE enabled=1 ORDER BY rotation_order,id')->fetchAll();
        $on = (int)($cfg['rotation_enabled'] ?? 0) === 1 && count($banks) >= 2;
        if (!$on) {
            return ['on' => false, 'seconds_left' => 0, 'next' => null, 'active' => $active];
        }
        $rot = array_values(array_filter($banks, fn($b) => (int)$b['rotation_minutes'] > 0));
        $start = !empty($cfg['rotation_started_at']) ? new DateTimeImmutable((string)$cfg['rotation_started_at'], new DateTimeZone('UTC')) : new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $elapsed = max(0, $now->getTimestamp() - $start->getTimestamp());
        $total = array_sum(array_map(fn($b) => max(1, (int)$b['rotation_minutes']) * 60, $rot));
        $pos = $total ? ($elapsed % $total) : 0;
        $cursor = 0;
        $idx = 0;
        foreach ($rot as $i => $b) {
            $slot = max(1, (int)$b['rotation_minutes']) * 60;
            $cursor += $slot;
            if ($pos < $cursor) {
                $left = $cursor - $pos;
                $next = $rot[($i + 1) % count($rot)] ?? null;
                return ['on' => true, 'seconds_left' => $left, 'next' => $next, 'active' => $active];
            }
            $idx = $i;
        }
        $next = $rot[($idx + 1) % max(1, count($rot))] ?? null;
        return ['on' => true, 'seconds_left' => 0, 'next' => $next, 'active' => $active];
    }

    public static function exactIntent(array $bank): string {
        $uri = trim((string)$bank['qr_payload']);
        self::parseUpiUri($uri);
        return $uri;
    }

    /** QR for one payment. Bank-issued links keep their own tr; only the amount is added. */
    public static function customerAmountIntent(array $bank, ?float $amount, string $reference): string {
        $payload = trim((string)($bank['qr_payload'] ?? ''));
        if ($payload !== '' && preg_match('/(?:^|[?&])tr=[^&]+/i', $payload)) {
            return self::fillPayeeName(self::insertAmount($payload, $amount), $bank);
        }
        $vpa = trim((string)($bank['vpa'] ?? ''));
        if ($vpa === '') {
            return $payload !== '' ? self::insertAmount($payload, $amount) : self::exactIntent($bank);
        }
        $pn = trim((string)($bank['payee_name'] ?? ''));
        if ($pn === '' || str_contains($pn, '@')) {
            $pn = trim((string)($bank['bank_name'] ?? 'YEEDIPAY'));
        }
        if ($pn === '') $pn = 'YEEDIPAY';
        $pairs = ['pa' => $vpa, 'pn' => $pn, 'cu' => 'INR'];
        $mcc = self::bankMcc($bank);
        if ($mcc !== '') {
            $pairs['mc'] = $mcc;
        }
        if ($amount !== null && $amount > 0) {
            $pairs['am'] = number_format($amount, 2, '.', '');
        }
        $ref = trim($reference);
        if ($ref !== '') $pairs['tn'] = $ref;
        $query = [];
        foreach ($pairs as $key => $value) {
            $query[] = $key.'='.rawurlencode((string)$value);
        }
        return 'upi://pay?'.implode('&', $query);
    }

    public static function insertAmount(string $uri, ?float $amount): string {
        $uri = trim($uri);
        if ($amount === null || $amount <= 0) return $uri;
        $amt = number_format($amount, 2, '.', '');
        if (preg_match('/(?:^|[?&])am=/i', $uri)) {
            return (string)preg_replace('/((?:^|[?&])am=)[^&]*/i', '${1}'.$amt, $uri, 1);
        }
        if (preg_match('/[?&]cu=/i', $uri, $m, PREG_OFFSET_CAPTURE)) {
            $at = (int)$m[0][1];
            return substr($uri, 0, $at + 1).'am='.$amt.substr($uri, $at);
        }
        return $uri.(str_contains($uri, '?') ? '&' : '?').'am='.$amt;
    }

    /** GPay hides the bank list when the payee name on a merchant QR is blank. */
    public static function fillPayeeName(string $uri, array $bank): string {
        $pn = trim((string)($bank['payee_name'] ?? ''));
        if ($pn === '' || str_contains($pn, '@')) {
            $pn = trim((string)($bank['bank_name'] ?? ''));
        }
        if ($pn === '') $pn = 'YEEDIPAY';
        $encoded = rawurlencode($pn);
        if (preg_match('/(?:^|[?&])pn=([^&]*)/i', $uri, $m)) {
            if (trim(rawurldecode((string)$m[1])) !== '') return $uri;
            return (string)preg_replace('/((?:^|[?&])pn=)[^&]*/i', '${1}'.$encoded, $uri, 1);
        }
        if (preg_match('/((?:^|[?&])pa=[^&]*)/i', $uri, $m, PREG_OFFSET_CAPTURE)) {
            $at = (int)$m[0][1] + strlen((string)$m[0][0]);
            return substr($uri, 0, $at).'&pn='.$encoded.substr($uri, $at);
        }
        return $uri.(str_contains($uri, '?') ? '&' : '?').'pn='.$encoded;
    }

    public static function intentWithAmount(string $uri, ?float $amount): string {
        $uri = trim($uri);
        $parsed = self::parseUpiUri($uri);
        if ($amount === null || $amount <= 0) {
            return $uri;
        }
        if (!empty($parsed['signed'])) {
            return $uri;
        }
        $amt = number_format($amount, 2, '.', '');
        $qPos = strpos($uri, '?');
        $base = $qPos === false ? $uri : substr($uri, 0, $qPos);
        $query = $qPos === false ? '' : substr($uri, $qPos + 1);
        if (preg_match('/(?:^|&)am=/i', $query)) {
            $query = preg_replace('/((?:^|&)am=)[^&]*/i', '${1}'.$amt, $query, 1);
        } elseif (preg_match('/((?:^|&)cu=)/i', $query, $m, PREG_OFFSET_CAPTURE)) {
            $at = (int)$m[1][1];
            $query = substr($query, 0, $at).'am='.$amt.'&'.substr($query, $at);
        } else {
            $query .= ($query !== '' ? '&' : '').'am='.$amt;
        }
        if (!preg_match('/(?:^|&)cu=/i', $query)) {
            $query .= '&cu=INR';
        }
        return $base.'?'.$query;
    }

    public static function statusApiLabel(array $bank): string {
        $has = trim((string)($bank['provider_base_url'] ?? '')) !== '' || trim((string)($bank['status_path'] ?? '')) !== '';
        if ($has) {
            $name = trim((string)($bank['provider_name'] ?? ''));
            return $name !== '' ? $name : 'Provider API';
        }
        return 'Reconciliation/Webhook only';
    }

    /** @param array<string,mixed> $file */
    public static function saveQrImage(array $file, string $code): ?string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('QR image upload failed');
        }
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            throw new InvalidArgumentException('QR image must be PNG, JPG, WEBP, or GIF');
        }
        $root = defined('YEEDIPAY_ROOT') ? YEEDIPAY_ROOT : dirname(__DIR__);
        $dir = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'bank_qr';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create bank QR storage folder');
        }
        $safeCode = normalize_code($code) ?: 'BANK';
        $name = $safeCode.'_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $rel = 'storage/bank_qr/'.$name;
        $dest = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
            throw new RuntimeException('Could not save QR image');
        }
        return $rel;
    }

    public static function attachQrImage(PDO $db, int $bankId, array $file, string $decodedUri = ''): ?string {
        self::ensureSchema($db);
        $bank = self::get($db, $bankId);
        if (!$bank) {
            throw new RuntimeException('Bank not found');
        }
        $rel = self::saveQrImage($file, (string)$bank['code']);
        if ($rel === null) {
            return null;
        }
        $db->prepare('UPDATE bank_profiles SET qr_image_path=? WHERE id=?')->execute([$rel, $bankId]);
        $uri = trim($decodedUri);
        if ($uri !== '') {
            try {
                $p = self::parseUpiUri($uri);
                $mccCol = $p['mcc'] !== '' ? $p['mcc'] : null;
                $signed = !empty($p['signed']) ? 1 : 0;
                $db->prepare('UPDATE bank_profiles SET vpa=?, payee_name=?, currency=?, qr_payload=?, qr_params_json=?, mcc=COALESCE(?, mcc), signed_qr=? WHERE id=?')->execute([
                    $p['vpa'],
                    $p['payee_name'],
                    $p['currency'],
                    $p['uri'],
                    json_encode($p['params'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $mccCol,
                    $signed,
                    $bankId,
                ]);
            } catch (Throwable $e) {
                // Image is saved; URI decode is best-effort from browser helper.
            }
        }
        return $rel;
    }

    public static function streamQrImage(PDO $db, int $id): never {
        self::ensureSchema($db);
        $bank = self::get($db, $id);
        if (!$bank) {
            throw new RuntimeException('Bank not found');
        }
        $rel = trim((string)($bank['qr_image_path'] ?? ''));
        if ($rel === '') {
            throw new RuntimeException('No QR image on file');
        }
        $root = realpath(defined('YEEDIPAY_ROOT') ? YEEDIPAY_ROOT : dirname(__DIR__)) ?: (defined('YEEDIPAY_ROOT') ? YEEDIPAY_ROOT : dirname(__DIR__));
        $abs = realpath($root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel));
        $dir = realpath($root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'bank_qr');
        if (!$abs || !$dir || !str_starts_with($abs, $dir.DIRECTORY_SEPARATOR) || !is_file($abs)) {
            throw new RuntimeException('QR image not found');
        }
        $mime = match (strtolower(pathinfo($abs, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/png',
        };
        header('Content-Type: '.$mime);
        header('Content-Length: '.(string)filesize($abs));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
        exit;
    }

    /** ICICI Eazypay standees — one VPA, payee name per merchant QR. */
    public const ICICI_EAZYPAY_VPA = 'msgreenrisingagrilimited.eazypay@icici';

    /** @return list<array{code:string,bank_name:string,payee_name:string,rotation_order:int}> */
    public static function iciciEazypayStandeeDefinitions(): array {
        return [
            ['code' => 'BALAJICAFE', 'bank_name' => 'BALAJI CAFE', 'payee_name' => 'BALAJI CAFE', 'rotation_order' => 1],
            ['code' => 'KAVITHAENT', 'bank_name' => 'KAVITHA ENTERPRISES', 'payee_name' => 'KAVITHA ENTERPRISES', 'rotation_order' => 2],
            ['code' => 'SIRIENT', 'bank_name' => 'SIRI ENTERPRISES', 'payee_name' => 'SIRI ENTERPRISES', 'rotation_order' => 3],
        ];
    }

    /** Insert or refresh the three ICICI standee banks for Admin → Banks & Routing. */
    public static function ensureIciciEazypayStandees(PDO $db): void {
        self::ensureSchema($db);
        $vpa = self::ICICI_EAZYPAY_VPA;
        foreach (self::iciciEazypayStandeeDefinitions() as $d) {
            $st = $db->prepare('SELECT id FROM bank_profiles WHERE code=? LIMIT 1');
            $st->execute([$d['code']]);
            $id = (int)$st->fetchColumn();
            $mcc = '';
            if ($id > 0) {
                $row = self::get($db, $id);
                $mcc = $row ? self::bankMcc($row) : '';
            }
            $uri = self::buildUpiUri($vpa, $d['payee_name'], $mcc, 'INR');
            $parsed = self::parseUpiUri($uri);
            $paramsJson = json_encode($parsed['params'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($id > 0) {
                $db->prepare('UPDATE bank_profiles SET bank_name=?, vpa=?, payee_name=?, currency=?, qr_payload=?, qr_params_json=?, rotation_order=?, enabled=1 WHERE id=?')
                    ->execute([$d['bank_name'], $vpa, $d['payee_name'], 'INR', $parsed['uri'], $paramsJson, $d['rotation_order'], $id]);
                continue;
            }
            self::add($db, [
                'code' => $d['code'],
                'bank_name' => $d['bank_name'],
                'vpa' => $vpa,
                'payee_name' => $d['payee_name'],
                'upi_uri' => $parsed['uri'],
                'enabled' => 1,
                'rotation_order' => $d['rotation_order'],
                'rotation_minutes' => self::DEFAULT_ROTATION_MINUTES,
            ]);
        }
    }

    private static function nullIfBlank(mixed $v): ?string {
        $s = trim((string)$v);
        return $s === '' ? null : $s;
    }

    private static function encryptIfPresent(string $plain): ?string {
        $plain = trim($plain);
        return $plain === '' ? null : Crypto::encrypt($plain);
    }
}
