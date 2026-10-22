<?php
declare(strict_types=1);

final class Log {
    public static function write(string $channel, string $event, array $context = []): void {
        $dir = YEEDIPAY_ROOT.'/storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $line = gmdate('c').' '.$channel.' '.$event.' '.json_encode($context, JSON_UNESCAPED_SLASHES);
        @file_put_contents($dir.'/app.log', $line.PHP_EOL, FILE_APPEND);
    }

    public static function safeThrowable(Throwable $e): string {
        return $e->getMessage();
    }
}

final class Auth {
    public static function customerLogin(int $id): void {
        $_SESSION['customer_id'] = $id;
    }

    public static function customerLoggedIn(int $customerId): bool {
        return $customerId > 0 && (int)($_SESSION['customer_id'] ?? 0) === $customerId;
    }

    public static function customerLogout(): void {
        unset($_SESSION['customer_id'], $_SESSION['customer_code'], $_SESSION['portal_otp_for'], $_SESSION['portal_otp_login']);
    }
}

final class Crypto {
    public static function encrypt(string $plain): string {
        return base64_encode($plain);
    }

    public static function decrypt(string $cipher): string {
        $plain = base64_decode($cipher, true);
        return $plain === false ? '' : $plain;
    }
}

final class LimitService {
    /** @return array{0:bool,1:string} */
    public static function check(PDO $db, array $customer, ?float $amount): array {
        return [true, ''];
    }

    public static function evaluateConfirmed(PDO $db, int $transactionId): void {}
}

final class PaymentConfirmService {
    public static function intentForAttempt(array $bank, ?float $amount, string $reference): string {
        return BankService::customerAmountIntent($bank, $amount, $reference);
    }

    public static function refresh(PDO $db, array $transaction): array {
        return $transaction;
    }

    public static function activateService(PDO $db, int $customerId): void {
        $db->prepare('UPDATE customers SET service_activated=1, service_activated_at=COALESCE(service_activated_at, UTC_TIMESTAMP()) WHERE id=?')
           ->execute([$customerId]);
    }
}

final class SmsService {
    public static function enqueueTxn(PDO $db, int $transactionId, string $status): void {}

    public static function sendOtp(PDO $db, string $phone, string $otp): void {}
}

final class EmailService {
    public static function ensureSchema(PDO $db): void {}

    public static function enqueueTxn(PDO $db, int $transactionId, string $status): void {}
}

final class WalletService {
    public static function ensureSchema(PDO $db): void {}

    public static function onTransactionSuccess(PDO $db, int $transactionId): void {}

    public static function onTransactionClosed(PDO $db, int $transactionId, string $status): void {}
}
