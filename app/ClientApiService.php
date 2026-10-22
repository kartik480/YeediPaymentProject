<?php
declare(strict_types=1);

final class ClientApiService {
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $db): void {
        if (self::$schemaReady) return;
        $db->exec("CREATE TABLE IF NOT EXISTS client_api_keys (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            key_id VARCHAR(32) NOT NULL,
            secret_hash CHAR(64) NOT NULL,
            client_name VARCHAR(160) NOT NULL,
            customer_code VARCHAR(32) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            last_used_at DATETIME NULL,
            UNIQUE KEY uq_client_api_key_id (key_id),
            UNIQUE KEY uq_client_api_secret_hash (secret_hash),
            INDEX idx_client_api_customer (customer_code, enabled)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS client_payment_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            api_key_id BIGINT UNSIGNED NOT NULL,
            order_id VARCHAR(100) NOT NULL,
            transaction_reference VARCHAR(120) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_client_order (api_key_id, order_id),
            UNIQUE KEY uq_client_transaction (transaction_reference),
            INDEX idx_client_request_created (created_at),
            CONSTRAINT fk_client_request_key FOREIGN KEY (api_key_id)
                REFERENCES client_api_keys(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $configuredKey = trim((string)Env::get('CLIENT_API_KEY', ''));
        if (preg_match('/^yp_live_(ck_[a-f0-9]{12})_[A-Za-z0-9_-]{40,}$/', $configuredKey, $match)) {
            $db->prepare(
                'INSERT INTO client_api_keys
                 (key_id, secret_hash, client_name, customer_code, enabled, created_at)
                 VALUES (?, ?, ?, ?, 1, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE secret_hash=VALUES(secret_hash),
                     client_name=VALUES(client_name), customer_code=VALUES(customer_code), enabled=1'
            )->execute([
                $match[1],
                hash('sha256', $configuredKey),
                (string)Env::get('CLIENT_API_NAME', 'CUST0003 Integration Client'),
                normalize_code((string)Env::get('CLIENT_API_CUSTOMER', 'CUST0003')),
            ]);
        }
        self::$schemaReady = true;
    }

    /** @return array{api_key:string,key_id:string,customer_code:string} */
    public static function createKey(PDO $db, string $clientName, string $customerCode): array {
        self::ensureSchema($db);
        $clientName = trim($clientName);
        $customerCode = normalize_code($customerCode);
        if ($clientName === '') throw new InvalidArgumentException('Client name is required');
        if ($customerCode === '') throw new InvalidArgumentException('Customer code is required');

        $keyId = 'ck_'.strtolower(bin2hex(random_bytes(6)));
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $apiKey = 'yp_live_'.$keyId.'_'.$secret;
        $db->prepare(
            'INSERT INTO client_api_keys
             (key_id, secret_hash, client_name, customer_code, enabled, created_at)
             VALUES (?, ?, ?, ?, 1, UTC_TIMESTAMP())'
        )->execute([$keyId, hash('sha256', $apiKey), $clientName, $customerCode]);
        return ['api_key' => $apiKey, 'key_id' => $keyId, 'customer_code' => $customerCode];
    }

    public static function handleCreatePayment(PDO $db): never {
        if (request_method() !== 'POST') {
            json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
        }
        self::ensureSchema($db);
        $key = self::authenticate($db);
        if (!$key) json_response(['ok' => false, 'error' => 'invalid_api_key'], 401);

        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $input = json_decode((string)file_get_contents('php://input'), true);
            if (!is_array($input)) json_response(['ok' => false, 'error' => 'invalid_json'], 400);
        } else {
            $input = $_POST;
        }

        try {
            $amount = TransactionService::parseEnteredAmount($input['amount'] ?? '');
            $orderId = trim((string)($input['order_id'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/', $orderId)) {
                throw new InvalidArgumentException('order_id must contain 1-100 letters, numbers, dots, underscores or hyphens');
            }

            $existing = $db->prepare(
                'SELECT transaction_reference, amount
                 FROM client_payment_requests WHERE api_key_id=? AND order_id=? LIMIT 1'
            );
            $existing->execute([(int)$key['id'], $orderId]);
            $request = $existing->fetch();
            if ($request) {
                if (abs((float)$request['amount'] - $amount) > 0.009) {
                    json_response(['ok' => false, 'error' => 'order_id_amount_conflict'], 409);
                }
                self::touch($db, (int)$key['id']);
                self::respondWithPayment($db, (string)$key['customer_code'], (string)$request['transaction_reference'], true);
            }

            $customer = TransactionService::customerByCode($db, (string)$key['customer_code']);
            if (!$customer || !(int)$customer['active']) {
                json_response(['ok' => false, 'error' => 'customer_not_available'], 404);
            }
            $created = TransactionService::createAttempt(
                $db,
                $customer,
                $amount,
                $orderId,
                'UPI',
                'UNSETTLED',
                true
            );
            $db->prepare(
                'INSERT INTO client_payment_requests
                 (api_key_id, order_id, transaction_reference, amount, created_at)
                 VALUES (?, ?, ?, ?, UTC_TIMESTAMP())'
            )->execute([(int)$key['id'], $orderId, $created['reference'], number_format($amount, 2, '.', '')]);
            self::touch($db, (int)$key['id']);
            self::respondWithPayment($db, (string)$key['customer_code'], (string)$created['reference'], false);
        } catch (InvalidArgumentException $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::write('client_api', 'payment_link_failed', ['message' => $e->getMessage()]);
            json_response(['ok' => false, 'error' => 'payment_link_failed'], 500);
        }
    }

    private static function authenticate(PDO $db): ?array {
        $authorization = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        $apiKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
        if ($apiKey === '' && preg_match('/^Bearer\s+(.+)$/i', $authorization, $match)) {
            $apiKey = trim($match[1]);
        }
        if (!preg_match('/^yp_live_(ck_[a-f0-9]{12})_([A-Za-z0-9_-]{40,})$/', $apiKey, $parts)) {
            return null;
        }
        $query = $db->prepare('SELECT * FROM client_api_keys WHERE key_id=? AND enabled=1 LIMIT 1');
        $query->execute([$parts[1]]);
        $row = $query->fetch();
        if (!$row || !hash_equals((string)$row['secret_hash'], hash('sha256', $apiKey))) return null;
        return $row;
    }

    private static function touch(PDO $db, int $id): void {
        $db->prepare('UPDATE client_api_keys SET last_used_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);
    }

    private static function respondWithPayment(PDO $db, string $customerCode, string $reference, bool $existing): never {
        $transaction = TransactionService::txnByRef($db, $reference);
        if (!$transaction) json_response(['ok' => false, 'error' => 'payment_not_found'], 404);
        $base = rtrim((string)Env::get('PAY_PUBLIC_URL', 'https://ygames.yeedipay.in'), '/');
        $path = '/p/'.rawurlencode($customerCode).'?ref='.rawurlencode($reference);
        json_response([
            'ok' => true,
            'existing' => $existing,
            'payment_reference' => $reference,
            'status' => (string)$transaction['status'],
            'amount' => (string)$transaction['requested_amount'],
            'buyer_name' => TransactionService::displayBuyerName($transaction),
            'verified_utr' => TransactionService::displayVerifiedUtr($transaction),
            'payment_url' => $base.$path,
            'expires_in' => BankSmsService::qrSecondsLeft($transaction),
        ]);
    }
}
