<?php
declare(strict_types=1);

final class WalletService {
    private static bool $ready=false;
    public static function ensureSchema(PDO $db): void {
        if(self::$ready)return;
        $db->exec("CREATE TABLE IF NOT EXISTS wallet_ledger (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL,
            transaction_id BIGINT UNSIGNED NULL, entry_type VARCHAR(40) NOT NULL, reference VARCHAR(80) NOT NULL,
            amount DECIMAL(14,2) NOT NULL, note VARCHAR(255) NULL, created_at DATETIME NOT NULL,
            UNIQUE KEY uq_wallet_entry(entry_type,reference), INDEX idx_wallet_customer(customer_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS wallet_recharges (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL,
            transaction_id BIGINT UNSIGNED NOT NULL, txn_reference VARCHAR(64) NOT NULL, amount DECIMAL(14,2) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'PENDING', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_wallet_recharge_txn(transaction_id), UNIQUE KEY uq_wallet_recharge_ref(txn_reference), INDEX idx_wallet_recharge_customer(customer_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS wallet_withdrawals (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL,
            reference VARCHAR(64) NOT NULL, amount DECIMAL(14,2) NOT NULL, account_name VARCHAR(180) NOT NULL,
            account_number VARCHAR(64) NOT NULL, ifsc VARCHAR(20) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_wallet_withdraw_ref(reference), INDEX idx_wallet_withdraw_customer(customer_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$ready=true;
    }
    public static function balance(PDO $db,int $customerId): float { self::ensureSchema($db);$st=$db->prepare('SELECT COALESCE(SUM(amount),0) FROM wallet_ledger WHERE customer_id=?');$st->execute([$customerId]);return round((float)$st->fetchColumn(),2); }
    public static function rechargesForCustomer(PDO $db,int $customerId): array { self::ensureSchema($db);$st=$db->prepare('SELECT * FROM wallet_recharges WHERE customer_id=? ORDER BY id DESC LIMIT 100');$st->execute([$customerId]);return $st->fetchAll(); }
    public static function withdrawalsForCustomer(PDO $db,int $customerId): array { self::ensureSchema($db);$st=$db->prepare('SELECT * FROM wallet_withdrawals WHERE customer_id=? ORDER BY id DESC LIMIT 100');$st->execute([$customerId]);return $st->fetchAll(); }
    public static function getRechargeByTxnRef(PDO $db,string $ref): ?array { self::ensureSchema($db);$st=$db->prepare('SELECT * FROM wallet_recharges WHERE txn_reference=?');$st->execute([$ref]);$r=$st->fetch();return $r?:null; }
    public static function startRecharge(PDO $db,array $customer,float $amount): array {
        self::ensureSchema($db);$t=TransactionService::createAttempt($db,$customer,$amount,'WALLET-RECHARGE','WALLET_RECHARGE','N/A',true);
        $db->prepare("INSERT INTO wallet_recharges(customer_id,transaction_id,txn_reference,amount,status,created_at,updated_at) VALUES(?,?,?,?, 'PENDING',UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([(int)$customer['id'],(int)$t['id'],(string)$t['reference'],number_format($amount,2,'.','')]);
        return self::getRechargeByTxnRef($db,(string)$t['reference'])??[];
    }
    public static function onTransactionSuccess(PDO $db,int $transactionId): void {
        self::ensureSchema($db);$st=$db->prepare("SELECT wr.*,t.confirmed_amount,t.requested_amount FROM wallet_recharges wr JOIN transactions t ON t.id=wr.transaction_id WHERE wr.transaction_id=? LIMIT 1");$st->execute([$transactionId]);$r=$st->fetch();if(!$r)return;
        $amt=(float)($r['confirmed_amount']??$r['requested_amount']??$r['amount']??0);if($amt<=0)return;
        $db->prepare("UPDATE wallet_recharges SET status='SUCCESS',updated_at=UTC_TIMESTAMP() WHERE id=?")->execute([(int)$r['id']]);
        try{$db->prepare("INSERT INTO wallet_ledger(customer_id,transaction_id,entry_type,reference,amount,note,created_at) VALUES(?,?, 'RECHARGE',?,?, 'Wallet recharge',UTC_TIMESTAMP())")->execute([(int)$r['customer_id'],$transactionId,(string)$r['txn_reference'],number_format($amt,2,'.','')]);}catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;}
    }
    public static function onTransactionClosed(PDO $db,int $transactionId,string $status): void { self::ensureSchema($db);$db->prepare('UPDATE wallet_recharges SET status=?,updated_at=UTC_TIMESTAMP() WHERE transaction_id=? AND status=\'PENDING\'')->execute([strtoupper($status),$transactionId]); }
    public static function createWithdrawal(PDO $db,array $customer,array $post): array {
        self::ensureSchema($db);$amount=(float)str_replace(',','',trim((string)($post['amount']??'')));if($amount<200)throw new InvalidArgumentException('Minimum withdrawal is ₹200');
        $bal=self::balance($db,(int)$customer['id']);if($amount>$bal+0.001)throw new InvalidArgumentException('Insufficient wallet balance');
        $name=trim((string)($post['account_name']??''));$acc=preg_replace('/\s+/','',trim((string)($post['account_number']??'')))??'';$ifsc=strtoupper(trim((string)($post['ifsc']??'')));
        if($name===''||strlen($acc)<6||!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/',$ifsc))throw new InvalidArgumentException('Enter valid beneficiary bank details');
        $ref='WD'.gmdate('ymdHis').strtoupper(substr(bin2hex(random_bytes(3)),0,6));
        $db->beginTransaction();try{$db->prepare("INSERT INTO wallet_withdrawals(customer_id,reference,amount,account_name,account_number,ifsc,status,created_at,updated_at) VALUES(?,?,?,?,?,?, 'PENDING',UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([(int)$customer['id'],$ref,number_format($amount,2,'.',''),substr($name,0,180),substr($acc,0,64),$ifsc]);$db->prepare("INSERT INTO wallet_ledger(customer_id,transaction_id,entry_type,reference,amount,note,created_at) VALUES(?,NULL,'WITHDRAWAL_HOLD',?,?,'Withdrawal request reserved',UTC_TIMESTAMP())")->execute([(int)$customer['id'],$ref,number_format(-$amount,2,'.','')]);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        $st=$db->prepare('SELECT * FROM wallet_withdrawals WHERE reference=?');$st->execute([$ref]);return $st->fetch()?:[];
    }
}
