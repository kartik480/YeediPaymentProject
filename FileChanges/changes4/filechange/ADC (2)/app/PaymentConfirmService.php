<?php
declare(strict_types=1);

final class PaymentConfirmService {
    public static function intentForAttempt(array $bank, ?float $amount, string $reference): string {
        $static = BankService::customerAmountIntent($bank, $amount, $reference);
        $base = trim((string)($bank['provider_base_url'] ?? ''));
        $path = trim((string)($bank['create_order_path'] ?? ''));
        if ($base === '' || $path === '') {
            return $static;
        }
        $url = self::joinUrl($base, $path);
        $body = [
            'reference' => $reference,
            'amount' => $amount === null ? null : number_format($amount, 2, '.', ''),
            'vpa' => $bank['vpa'] ?? '',
            'currency' => $bank['currency'] ?? 'INR',
        ];
        $res = self::http($bank, 'POST', $url, $body);
        if (!is_array($res)) {
            return $static;
        }
        foreach (['intent_uri', 'upi_uri', 'qr', 'qr_payload', 'payment_url'] as $k) {
            $v = trim((string)($res[$k] ?? ''));
            if ($v !== '' && (str_starts_with(strtolower($v), 'upi:') || str_starts_with(strtolower($v), 'http'))) {
                return $v;
            }
        }
        return $static;
    }

    public static function refresh(PDO $db, array $t): array {
        // Collection confirmation is locked to the authenticated Android bank-SMS
        // auto-match flow. Provider status APIs cannot change PENDING to SUCCESS.
        return TransactionService::txnByRef($db, (string)($t['reference'] ?? '')) ?: $t;
    }

    public static function activateService(PDO $db, int $customerId): void {
        TransactionService::ensureSchema($db);
        $db->prepare('UPDATE customers SET service_activated=1, service_activated_at=COALESCE(service_activated_at, UTC_TIMESTAMP()) WHERE id=?')->execute([$customerId]);
    }

    private static function joinUrl(string $base, string $path): string {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return rtrim($base, '/').'/'.ltrim($path, '/');
    }

    private static function http(array $bank, string $method, string $url, ?array $json): ?array {
        $headers = ['Accept: application/json'];
        $key = '';
        if (!empty($bank['api_key_encrypted'])) {
            try { $key = Crypto::decrypt((string)$bank['api_key_encrypted']); } catch (Throwable $e) { $key = ''; }
        }
        if ($key !== '') {
            $headers[] = 'Authorization: Bearer '.$key;
        }
        $payload = $json !== null ? json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        if ($payload !== '') {
            $headers[] = 'Content-Type: application/json';
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if ($method !== 'GET' && $payload !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($resp === false || $code < 200 || $code >= 300) {
                return null;
            }
            $decoded = json_decode((string)$resp, true);
            return is_array($decoded) ? $decoded : null;
        }
        $opts = ['http' => ['method' => $method, 'timeout' => 12, 'ignore_errors' => true, 'header' => implode("\r\n", $headers)]];
        if ($method !== 'GET' && $payload !== '') {
            $opts['http']['content'] = $payload;
        }
        $resp = @file_get_contents($url, false, stream_context_create($opts));
        if ($resp === false) {
            return null;
        }
        $decoded = json_decode($resp, true);
        return is_array($decoded) ? $decoded : null;
    }
}
