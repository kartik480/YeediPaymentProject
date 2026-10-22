<?php
declare(strict_types=1);

final class MerchantApiService {
    private const API_SKEW_SECONDS = 300;

    public static function ensureSchema(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS merchant_accounts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            merchant_id VARCHAR(32) NOT NULL,
            display_name VARCHAR(180) NOT NULL,
            mode VARCHAR(12) NOT NULL DEFAULT 'LIVE',
            api_key_hash CHAR(64) NOT NULL,
            api_key_hint VARCHAR(24) NOT NULL,
            api_secret_encrypted TEXT NOT NULL,
            webhook_secret_encrypted TEXT NOT NULL,
            webhook_url VARCHAR(500) NULL,
            return_url VARCHAR(500) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_merchant_id(merchant_id),
            UNIQUE KEY uq_merchant_api_key(api_key_hash),
            INDEX idx_merchant_customer(customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS merchant_orders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            merchant_account_id BIGINT UNSIGNED NOT NULL,
            transaction_id BIGINT UNSIGNED NOT NULL,
            order_id VARCHAR(80) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            currency VARCHAR(8) NOT NULL DEFAULT 'INR',
            buyer_name VARCHAR(180) NULL,
            buyer_mobile VARCHAR(32) NULL,
            buyer_email VARCHAR(180) NULL,
            return_url VARCHAR(500) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_merchant_order(merchant_account_id,order_id),
            UNIQUE KEY uq_merchant_transaction(transaction_id),
            INDEX idx_merchant_order_status(status,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        WebhookService::ensureSchema($db);
    }

    /** @return array{merchant_id:string,api_key:string,api_secret:string,webhook_secret:string} */
    public static function provision(PDO $db,string $customerCode,string $displayName,string $webhookUrl='',string $returnUrl='',string $mode='LIVE'): array {
        self::ensureSchema($db);
        $customer = TransactionService::customerByCode($db, strtoupper(trim($customerCode)));
        if (!$customer) throw new RuntimeException('Customer code not found');
        $webhookUrl = trim($webhookUrl); $returnUrl = trim($returnUrl);
        if ($webhookUrl !== '' && !WebhookService::isAllowedWebhookUrl($webhookUrl)) throw new InvalidArgumentException('Webhook URL must use HTTPS');
        if ($returnUrl !== '' && !self::isSafeReturnUrl($returnUrl)) throw new InvalidArgumentException('Return URL must use HTTPS');
        $merchantId = self::nextMerchantId($db);
        $prefix = strtoupper(trim($mode)) === 'SANDBOX' ? 'yp_test_' : 'yp_live_';
        $apiKey = $prefix.bin2hex(random_bytes(12));
        $apiSecret = bin2hex(random_bytes(32));
        $webhookSecret = 'whsec_'.bin2hex(random_bytes(24));
        $st=$db->prepare("INSERT INTO merchant_accounts(customer_id,merchant_id,display_name,mode,api_key_hash,api_key_hint,api_secret_encrypted,webhook_secret_encrypted,webhook_url,return_url,active,created_at,updated_at)
                          VALUES(?,?,?,?,?,?,?,?,?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $st->execute([(int)$customer['id'],$merchantId,trim($displayName)!==''?trim($displayName):(string)$customer['name'],strtoupper(trim($mode))==='SANDBOX'?'SANDBOX':'LIVE',hash('sha256',$apiKey),substr($apiKey,-8),Crypto::encrypt($apiSecret),Crypto::encrypt($webhookSecret),$webhookUrl!==''?$webhookUrl:null,$returnUrl!==''?$returnUrl:null]);
        return ['merchant_id'=>$merchantId,'api_key'=>$apiKey,'api_secret'=>$apiSecret,'webhook_secret'=>$webhookSecret];
    }

    public static function nextMerchantId(PDO $db): string {
        $n=(int)$db->query('SELECT COUNT(*) FROM merchant_accounts')->fetchColumn()+1;
        while(true){$id='MER'.str_pad((string)$n,4,'0',STR_PAD_LEFT);$st=$db->prepare('SELECT 1 FROM merchant_accounts WHERE merchant_id=?');$st->execute([$id]);if(!$st->fetchColumn())return $id;$n++;}
    }

    public static function canonicalSignature(string $timestamp,string $method,string $path,string $body,string $secret): string {
        $canonical=$timestamp."\n".strtoupper($method)."\n".$path."\n".hash('sha256',$body);
        return hash_hmac('sha256',$canonical,$secret);
    }

    /** @return array<string,mixed> */
    public static function authenticate(PDO $db,string $rawBody=''): array {
        self::ensureSchema($db);
        $merchantId=trim((string)($_SERVER['HTTP_X_MERCHANT_ID']??''));
        $apiKey=trim((string)($_SERVER['HTTP_X_API_KEY']??''));
        $timestamp=trim((string)($_SERVER['HTTP_X_TIMESTAMP']??''));
        $signature=strtolower(trim((string)($_SERVER['HTTP_X_SIGNATURE']??'')));
        if($merchantId===''||$apiKey===''||$timestamp===''||$signature==='') throw new RuntimeException('Missing API authentication headers');
        if(!ctype_digit($timestamp)||abs(time()-(int)$timestamp)>self::API_SKEW_SECONDS) throw new RuntimeException('Invalid or stale API timestamp');
        $st=$db->prepare('SELECT m.*,c.code customer_code,c.name customer_name,c.active customer_active FROM merchant_accounts m JOIN customers c ON c.id=m.customer_id WHERE m.merchant_id=? AND m.api_key_hash=? LIMIT 1');
        $st->execute([$merchantId,hash('sha256',$apiKey)]);$m=$st->fetch();
        if(!$m||!(int)$m['active']||!(int)$m['customer_active']) throw new RuntimeException('Invalid merchant credentials');
        $secret=Crypto::decrypt((string)$m['api_secret_encrypted']);
        if($secret==='') throw new RuntimeException('Merchant API secret unavailable');
        $expected=self::canonicalSignature($timestamp,request_method(),route_path(),$rawBody,$secret);
        if(!hash_equals($expected,$signature)) throw new RuntimeException('Invalid API signature');
        return $m;
    }

    public static function handleCreate(PDO $db): never {
        $raw=(string)file_get_contents('php://input');
        try{$merchant=self::authenticate($db,$raw);}catch(Throwable $e){json_response(['success'=>false,'error'=>$e->getMessage()],401);}
        $data=json_decode($raw,true);if(!is_array($data))json_response(['success'=>false,'error'=>'Invalid JSON'],400);
        try{
            $orderId=self::validateOrderId((string)($data['order_id']??''));
            $amount=self::validateAmount($data['amount']??null);
            $existing=self::orderByMerchantOrderId($db,(int)$merchant['id'],$orderId);
            if($existing){
                self::assertIdempotentOrder($existing,$amount);
                json_response(self::apiOrderPayload($existing,$merchant),200);
            }
            $customer=TransactionService::customerByCode($db,(string)$merchant['customer_code']);
            if(!$customer)throw new RuntimeException('Merchant customer is unavailable');
            TransactionService::ensureSchema($db); BankService::ensureSchema($db);
            $configuredReturn=trim((string)($merchant['return_url']??''));
            $returnUrl=trim((string)($data['return_url']??''));
            if($returnUrl!==''){
                if($configuredReturn==='')throw new InvalidArgumentException('return_url is not registered for this merchant');
                if(!self::sameReturnOrigin($returnUrl,$configuredReturn))throw new InvalidArgumentException('return_url must match the registered merchant domain');
            } else $returnUrl=$configuredReturn;
            if($returnUrl!==''&&!self::isSafeReturnUrl($returnUrl))throw new InvalidArgumentException('return_url must use HTTPS');
            $name=self::cleanText((string)($data['customer_name']??''),180);
            $mobile=self::cleanText((string)($data['mobile']??''),32);
            $email=self::cleanText((string)($data['email']??''),180);
            $db->beginTransaction();
            try {
                $created=TransactionService::createAttempt($db,$customer,$amount,$orderId,'UPI','UNSETTLED',true);
                $st=$db->prepare("INSERT INTO merchant_orders(merchant_account_id,transaction_id,order_id,amount,currency,buyer_name,buyer_mobile,buyer_email,return_url,status,created_at,updated_at)
                                  VALUES(?,?,?,?,?,?,?,?,?,'PENDING',UTC_TIMESTAMP(),UTC_TIMESTAMP())");
                $st->execute([(int)$merchant['id'],(int)$created['id'],$orderId,number_format($amount,2,'.',''),'INR',$name!==''?$name:null,$mobile!==''?$mobile:null,$email!==''?$email:null,$returnUrl!==''?$returnUrl:null]);
                $db->commit();
            } catch (Throwable $e) {
                if($db->inTransaction())$db->rollBack();
                $dupe=self::orderByMerchantOrderId($db,(int)$merchant['id'],$orderId);
                if($dupe)json_response(self::apiOrderPayload($dupe,$merchant),200);
                throw $e;
            }
            $order=self::orderByTransaction($db,(int)$created['id']);
            if(!$order)throw new RuntimeException('Could not create merchant order');
            json_response(self::apiOrderPayload($order,$merchant),201);
        }catch(Throwable $e){json_response(['success'=>false,'error'=>$e->getMessage()],422);}
    }

    public static function handleStatus(PDO $db,string $paymentId): never {
        try{$merchant=self::authenticate($db,'');}catch(Throwable $e){json_response(['success'=>false,'error'=>$e->getMessage()],401);}
        $order=self::orderByPaymentId($db,(int)$merchant['id'],strtoupper(trim($paymentId)));
        if(!$order)json_response(['success'=>false,'error'=>'Payment not found'],404);
        $txn=TransactionService::txnByRef($db,(string)$order['reference']);
        if($txn){$txn=TransactionService::refreshCustomerPayment($db,$txn);self::syncOrderFromTransaction($db,$txn);$order=self::orderByPaymentId($db,(int)$merchant['id'],(string)$order['reference'])?:$order;}
        try{WebhookService::deliverPending($db,3);}catch(Throwable $e){}
        json_response(self::apiOrderPayload($order,$merchant),200);
    }

    /** @param array<string,mixed> $t */
    public static function onTransactionStatusChanged(PDO $db,array $t,string $status): void {
        self::ensureSchema($db);
        $order=self::orderByTransaction($db,(int)($t['id']??0));
        if(!$order)return;
        $merchant=self::merchantById($db,(int)$order['merchant_account_id']);
        if(!$merchant)return;
        $status=strtoupper(trim($status));
        $db->prepare('UPDATE merchant_orders SET status=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$status,(int)$order['id']]);
        if(!in_array($status,['SUCCESS','FAILED','EXPIRED'],true))return;
        $fresh=self::orderByTransaction($db,(int)$t['id'])?:$order;
        $event='payment.'.strtolower($status==='SUCCESS'?'success':($status==='EXPIRED'?'expired':'failed'));
        $payload=self::webhookPayload($fresh,$merchant,$t,$event);
        WebhookService::queue($db,$merchant,$fresh,$event,$payload);
    }

    /** @param array<string,mixed> $t */
    public static function syncOrderFromTransaction(PDO $db,array $t): void {
        $status=strtoupper((string)($t['status']??'PENDING'));
        $order=self::orderByTransaction($db,(int)($t['id']??0));
        if(!$order||strtoupper((string)$order['status'])===$status)return;
        if(in_array($status,['SUCCESS','FAILED','EXPIRED'],true)) self::onTransactionStatusChanged($db,$t,$status);
        else $db->prepare('UPDATE merchant_orders SET status=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$status,(int)$order['id']]);
    }

    public static function sweepFinalOrders(PDO $db,int $limit=100): int {
        self::ensureSchema($db);$limit=max(1,min(500,$limit));$n=0;
        $rows=$db->query("SELECT t.* FROM merchant_orders o JOIN transactions t ON t.id=o.transaction_id WHERE t.status IN ('SUCCESS','FAILED','EXPIRED') AND o.status<>t.status ORDER BY o.id ASC LIMIT {$limit}")->fetchAll();
        foreach($rows as $t){try{self::onTransactionStatusChanged($db,$t,(string)$t['status']);$n++;}catch(Throwable $e){}}
        return $n;
    }

    /** @return array<string,mixed>|null */
    public static function orderByTransaction(PDO $db,int $transactionId): ?array {
        if($transactionId<=0)return null;
        $st=$db->prepare('SELECT o.*,t.reference,t.status transaction_status,t.requested_amount,t.confirmed_amount,t.rrn,t.upi_txn_id,t.created_at transaction_created_at,t.updated_at transaction_updated_at FROM merchant_orders o JOIN transactions t ON t.id=o.transaction_id WHERE o.transaction_id=? LIMIT 1');
        $st->execute([$transactionId]);$r=$st->fetch();return $r?:null;
    }
    /** @return array<string,mixed>|null */
    public static function orderByPaymentId(PDO $db,int $merchantAccountId,string $paymentId): ?array {
        $st=$db->prepare('SELECT o.*,t.reference,t.status transaction_status,t.requested_amount,t.confirmed_amount,t.rrn,t.upi_txn_id,t.created_at transaction_created_at,t.updated_at transaction_updated_at FROM merchant_orders o JOIN transactions t ON t.id=o.transaction_id WHERE o.merchant_account_id=? AND t.reference=? LIMIT 1');
        $st->execute([$merchantAccountId,$paymentId]);$r=$st->fetch();return $r?:null;
    }
    /** @return array<string,mixed>|null */
    public static function orderByMerchantOrderId(PDO $db,int $merchantAccountId,string $orderId): ?array {
        $st=$db->prepare('SELECT o.*,t.reference,t.status transaction_status,t.requested_amount,t.confirmed_amount,t.rrn,t.upi_txn_id,t.created_at transaction_created_at,t.updated_at transaction_updated_at FROM merchant_orders o JOIN transactions t ON t.id=o.transaction_id WHERE o.merchant_account_id=? AND o.order_id=? LIMIT 1');
        $st->execute([$merchantAccountId,$orderId]);$r=$st->fetch();return $r?:null;
    }
    /** @return array<string,mixed>|null */
    public static function merchantById(PDO $db,int $id): ?array {$st=$db->prepare('SELECT * FROM merchant_accounts WHERE id=? LIMIT 1');$st->execute([$id]);$r=$st->fetch();return $r?:null;}

    /** @param array<string,mixed> $order @param array<string,mixed> $merchant */
    private static function apiOrderPayload(array $order,array $merchant): array {
        $status=strtoupper((string)($order['transaction_status']??$order['status']??'PENDING'));
        $created=(string)($order['transaction_created_at']??$order['created_at']??now_utc());
        $expires=strtotime($created.' UTC');if($expires===false)$expires=time();$expires+=BankSmsService::qrTtlSeconds();
        $utr=trim((string)($order['rrn']??''));if($utr==='')$utr=trim((string)($order['upi_txn_id']??''));
        return [
            'success'=>true,'merchant_id'=>(string)$merchant['merchant_id'],'order_id'=>(string)$order['order_id'],
            'payment_id'=>(string)$order['reference'],'status'=>$status,
            'amount'=>number_format((float)($order['confirmed_amount']??$order['requested_amount']??$order['amount']),2,'.',''),
            'currency'=>'INR','payment_url'=>pay_url('/pay/'.(string)$order['reference']),
            'return_url'=>(string)($order['return_url']??''),'utr'=>$status==='SUCCESS'?$utr:'',
            'created_at'=>gmdate('c',strtotime($created.' UTC')?:time()),'expires_at'=>gmdate('c',$expires),
        ];
    }

    /** @param array<string,mixed> $order @param array<string,mixed> $merchant @param array<string,mixed> $t */
    private static function webhookPayload(array $order,array $merchant,array $t,string $event): array {
        $utr=trim((string)($t['rrn']??''));if($utr==='')$utr=trim((string)($t['upi_txn_id']??''));
        return ['merchant_id'=>(string)$merchant['merchant_id'],'order_id'=>(string)$order['order_id'],'payment_id'=>(string)$t['reference'],'amount'=>number_format((float)($t['confirmed_amount']??$t['requested_amount']??$order['amount']),2,'.',''),'currency'=>'INR','status'=>strtoupper((string)$t['status']),'utr'=>$utr,'paid_at'=>strtoupper((string)$t['status'])==='SUCCESS'?gmdate('c',strtotime((string)$t['updated_at'].' UTC')?:time()):null,'return_url'=>(string)($order['return_url']??'')];
    }

    /** Same order_id is idempotent only when the immutable amount is identical. */
    public static function assertIdempotentOrder(array $existing, float $requestedAmount): void {
        $old = round((float)($existing['amount'] ?? $existing['requested_amount'] ?? 0), 2);
        $new = round($requestedAmount, 2);
        if (abs($old - $new) > 0.0001) {
            throw new InvalidArgumentException('order_id already exists with a different amount');
        }
    }

    private static function validateOrderId(string $s): string {$s=trim($s);if(!preg_match('/^[A-Za-z0-9._-]{3,80}$/',$s))throw new InvalidArgumentException('Invalid order_id');return $s;}
    private static function validateAmount(mixed $v): float {$s=trim((string)$v);if(!preg_match('/^\d+(?:\.\d{1,2})?$/',$s))throw new InvalidArgumentException('Invalid amount');$f=(float)$s;if($f<1||$f>200000)throw new InvalidArgumentException('Amount must be between 1 and 200000');return $f;}
    private static function cleanText(string $s,int $max): string {$s=trim(preg_replace('/[\x00-\x1F\x7F]/u','',$s)??'');return function_exists('mb_substr')?mb_substr($s,0,$max):substr($s,0,$max);}
    private static function isSafeReturnUrl(string $url): bool { return WebhookService::isAllowedWebhookUrl($url); }
    private static function sameReturnOrigin(string $candidate,string $registered): bool {$a=parse_url($candidate);$b=parse_url($registered);if(!$a||!$b)return false;return strtolower((string)($a['scheme']??''))===strtolower((string)($b['scheme']??''))&&strtolower((string)($a['host']??''))===strtolower((string)($b['host']??''))&&(int)($a['port']??0)===(int)($b['port']??0);}
}
