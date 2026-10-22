<?php
declare(strict_types=1);

final class MerchantApiService {
    private const API_SKEW_SECONDS=300;

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

    public static function provision(PDO $db,string $customerCode,string $displayName,string $webhookUrl='',string $returnUrl='',string $mode='LIVE'): array {
        self::ensureSchema($db);
        $customer=TransactionService::customerByCode($db,strtoupper(trim($customerCode)));
        if(!$customer)throw new RuntimeException('Customer code not found');
        $webhookUrl=trim($webhookUrl);$returnUrl=trim($returnUrl);
        if($webhookUrl!==''&&!WebhookService::isAllowedWebhookUrl($webhookUrl))throw new InvalidArgumentException('Webhook URL is not allowed');
        if($returnUrl!==''&&!WebhookService::isAllowedWebhookUrl($returnUrl))throw new InvalidArgumentException('Return URL is not allowed');
        $merchantId=self::nextMerchantId($db);
        $isTest=strtoupper(trim($mode))==='SANDBOX';
        $apiKey=($isTest?'yp_test_':'yp_live_').bin2hex(random_bytes(12));
        $apiSecret=bin2hex(random_bytes(32));
        $webhookSecret='whsec_'.bin2hex(random_bytes(24));
        $st=$db->prepare("INSERT INTO merchant_accounts(customer_id,merchant_id,display_name,mode,api_key_hash,api_key_hint,api_secret_encrypted,webhook_secret_encrypted,webhook_url,return_url,active,created_at,updated_at)
                          VALUES(?,?,?,?,?,?,?,?,?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $st->execute([(int)$customer['id'],$merchantId,trim($displayName)?:$customer['name'],$isTest?'SANDBOX':'LIVE',hash('sha256',$apiKey),substr($apiKey,-8),Crypto::encrypt($apiSecret),Crypto::encrypt($webhookSecret),$webhookUrl?:null,$returnUrl?:null]);
        return ['merchant_id'=>$merchantId,'api_key'=>$apiKey,'api_secret'=>$apiSecret,'webhook_secret'=>$webhookSecret];
    }

    private static function nextMerchantId(PDO $db): string {
        $n=(int)$db->query('SELECT COUNT(*) FROM merchant_accounts')->fetchColumn()+1;
        while(true){$id='MER'.str_pad((string)$n,4,'0',STR_PAD_LEFT);$st=$db->prepare('SELECT 1 FROM merchant_accounts WHERE merchant_id=?');$st->execute([$id]);if(!$st->fetchColumn())return $id;$n++;}
    }

    public static function canonicalSignature(string $timestamp,string $method,string $path,string $body,string $secret): string {
        return hash_hmac('sha256',$timestamp."\n".strtoupper($method)."\n".$path."\n".hash('sha256',$body),$secret);
    }

    public static function authenticate(PDO $db,string $rawBody=''): array {
        self::ensureSchema($db);
        $merchantId=trim((string)($_SERVER['HTTP_X_MERCHANT_ID']??''));
        $apiKey=trim((string)($_SERVER['HTTP_X_API_KEY']??''));
        $timestamp=trim((string)($_SERVER['HTTP_X_TIMESTAMP']??''));
        $signature=strtolower(trim((string)($_SERVER['HTTP_X_SIGNATURE']??'')));
        if($merchantId===''||$apiKey===''||$timestamp===''||$signature==='')throw new RuntimeException('Missing API authentication headers');
        if(!ctype_digit($timestamp)||abs(time()-(int)$timestamp)>self::API_SKEW_SECONDS)throw new RuntimeException('Invalid or stale API timestamp');
        $st=$db->prepare('SELECT m.*,c.code customer_code,c.name customer_name,c.active customer_active FROM merchant_accounts m JOIN customers c ON c.id=m.customer_id WHERE m.merchant_id=? AND m.api_key_hash=? LIMIT 1');
        $st->execute([$merchantId,hash('sha256',$apiKey)]);$merchant=$st->fetch();
        if(!$merchant||!(int)$merchant['active']||!(int)$merchant['customer_active'])throw new RuntimeException('Invalid merchant credentials');
        $secret=Crypto::decrypt((string)$merchant['api_secret_encrypted']);
        if($secret===''||!hash_equals(self::canonicalSignature($timestamp,request_method(),route_path(),$rawBody,$secret),$signature))throw new RuntimeException('Invalid API signature');
        return $merchant;
    }

    public static function handleCreate(PDO $db): never {
        $raw=(string)file_get_contents('php://input');
        try{$merchant=self::authenticate($db,$raw);}catch(Throwable $e){json_response(['success'=>false,'error'=>$e->getMessage()],401);}
        $data=json_decode($raw,true);
        if(!is_array($data))json_response(['success'=>false,'error'=>'Invalid JSON'],400);
        try{
            $orderId=self::validateOrderId((string)($data['order_id']??''));
            $amount=self::validateAmount($data['amount']??null);
            $existing=self::orderByMerchantOrderId($db,(int)$merchant['id'],$orderId);
            if($existing){self::assertIdempotentOrder($existing,$amount);json_response(self::apiOrderPayload($existing,$merchant),200);}
            $customer=TransactionService::customerByCode($db,(string)$merchant['customer_code']);
            if(!$customer)throw new RuntimeException('Merchant customer is unavailable');
            $configuredReturn=trim((string)($merchant['return_url']??''));
            $returnUrl=trim((string)($data['return_url']??''));
            if($returnUrl!==''){
                if($configuredReturn===''||!self::sameOrigin($returnUrl,$configuredReturn))throw new InvalidArgumentException('return_url must match the registered merchant domain');
            }else{$returnUrl=$configuredReturn;}
            $db->beginTransaction();
            try{
                $created=TransactionService::createAttempt($db,$customer,$amount,$orderId,'UPI','UNSETTLED',true);
                $st=$db->prepare("INSERT INTO merchant_orders(merchant_account_id,transaction_id,order_id,amount,currency,buyer_name,buyer_mobile,buyer_email,return_url,status,created_at,updated_at)
                                  VALUES(?,?,?,?,?,?,?,?,?,'PENDING',UTC_TIMESTAMP(),UTC_TIMESTAMP())");
                $apiBuyer = self::clean($data['customer_name'] ?? '', 180);
                $st->execute([(int)$merchant['id'],(int)$created['id'],$orderId,number_format($amount,2,'.',''),'INR',$apiBuyer !== '' ? $apiBuyer : null,self::clean($data['mobile']??'',32)?:null,self::clean($data['email']??'',180)?:null,$returnUrl?:null]);
                if ($apiBuyer !== '') {
                    $db->prepare('UPDATE transactions SET buyer_name=? WHERE id=?')->execute([$apiBuyer, (int)$created['id']]);
                }
                $db->commit();
            }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$duplicate=self::orderByMerchantOrderId($db,(int)$merchant['id'],$orderId);if($duplicate){self::assertIdempotentOrder($duplicate,$amount);json_response(self::apiOrderPayload($duplicate,$merchant),200);}throw $e;}
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
        if($txn){$txn=PaymentConfirmService::refresh($db,$txn);self::syncOrderFromTransaction($db,$txn);$order=self::orderByPaymentId($db,(int)$merchant['id'],(string)$order['reference'])?:$order;}
        try{WebhookService::deliverPending($db,3);}catch(Throwable $e){}
        json_response(self::apiOrderPayload($order,$merchant),200);
    }

    public static function onTransactionStatusChanged(PDO $db,array $transaction,string $status): void {
        self::ensureSchema($db);
        $order=self::orderByTransaction($db,(int)($transaction['id']??0));if(!$order)return;
        $merchant=self::merchantById($db,(int)$order['merchant_account_id']);if(!$merchant)return;
        $status=strtoupper(trim($status));
        $db->prepare('UPDATE merchant_orders SET status=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$status,(int)$order['id']]);
        if(!in_array($status,['SUCCESS','FAILED','EXPIRED'],true))return;
        $event='payment.'.strtolower($status==='SUCCESS'?'success':($status==='EXPIRED'?'expired':'failed'));
        WebhookService::queue($db,$merchant,$order,$event,self::webhookPayload($order,$merchant,$transaction));
    }

    public static function syncOrderFromTransaction(PDO $db,array $transaction): void {
        $status=strtoupper((string)($transaction['status']??'PENDING'));
        $order=self::orderByTransaction($db,(int)($transaction['id']??0));
        if(!$order||strtoupper((string)$order['status'])===$status)return;
        if(in_array($status,['SUCCESS','FAILED','EXPIRED'],true))self::onTransactionStatusChanged($db,$transaction,$status);
        else $db->prepare('UPDATE merchant_orders SET status=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$status,(int)$order['id']]);
    }

    public static function sweepFinalOrders(PDO $db,int $limit=100): int {
        self::ensureSchema($db);$limit=max(1,min(500,$limit));$n=0;
        $rows=$db->query("SELECT t.* FROM merchant_orders o JOIN transactions t ON t.id=o.transaction_id WHERE t.status IN ('SUCCESS','FAILED','EXPIRED') AND o.status<>t.status ORDER BY o.id ASC LIMIT {$limit}")->fetchAll();
        foreach($rows as $row){try{self::onTransactionStatusChanged($db,$row,(string)$row['status']);$n++;}catch(Throwable $e){}}
        return $n;
    }

    public static function orderByTransaction(PDO $db,int $transactionId): ?array {if($transactionId<=0)return null;$st=$db->prepare(self::orderSelect().' WHERE o.transaction_id=? LIMIT 1');$st->execute([$transactionId]);$r=$st->fetch();return $r?:null;}
    private static function orderByPaymentId(PDO $db,int $merchantId,string $paymentId): ?array {$st=$db->prepare(self::orderSelect().' WHERE o.merchant_account_id=? AND t.reference=? LIMIT 1');$st->execute([$merchantId,$paymentId]);$r=$st->fetch();return $r?:null;}
    private static function orderByMerchantOrderId(PDO $db,int $merchantId,string $orderId): ?array {$st=$db->prepare(self::orderSelect().' WHERE o.merchant_account_id=? AND o.order_id=? LIMIT 1');$st->execute([$merchantId,$orderId]);$r=$st->fetch();return $r?:null;}
    private static function merchantById(PDO $db,int $id): ?array {$st=$db->prepare('SELECT * FROM merchant_accounts WHERE id=? LIMIT 1');$st->execute([$id]);$r=$st->fetch();return $r?:null;}
    public static function forCustomer(PDO $db,int $customerId): array {
        self::ensureSchema($db);
        $st=$db->prepare('SELECT id,merchant_id,display_name,mode,api_key_hint,webhook_url,return_url,active,created_at FROM merchant_accounts WHERE customer_id=? ORDER BY id DESC');
        $st->execute([$customerId]);
        return $st->fetchAll();
    }
    private static function orderSelect(): string {return 'SELECT o.*,t.reference,t.status transaction_status,t.requested_amount,t.confirmed_amount,t.rrn,t.upi_txn_id,t.verified_utr,t.buyer_name,t.customer_reported_utr,t.created_at transaction_created_at,t.updated_at transaction_updated_at FROM merchant_orders o JOIN transactions t ON t.id=o.transaction_id';}

    private static function apiOrderPayload(array $order,array $merchant): array {
        $status=strtoupper((string)($order['transaction_status']??$order['status']??'PENDING'));$created=(string)($order['transaction_created_at']??$order['created_at']??gmdate('Y-m-d H:i:s'));
        $expires=(strtotime($created.' UTC')?:time())+BankSmsService::qrTtlSeconds();
        $txnRow=['reference'=>$order['reference']??'','status'=>$status,'verified_utr'=>$order['verified_utr']??'','rrn'=>$order['rrn']??'','upi_txn_id'=>$order['upi_txn_id']??'','customer_reported_utr'=>$order['customer_reported_utr']??'','buyer_name'=>$order['buyer_name']??'','customer_name'=>$order['buyer_name']??''];
        $utrLabel=TransactionService::displayVerifiedUtr($txnRow);
        $utr=$utrLabel==='Not Available'?'':$utrLabel;
        $buyer=TransactionService::displayBuyerName($txnRow);
        return ['success'=>true,'merchant_id'=>(string)$merchant['merchant_id'],'order_id'=>(string)$order['order_id'],'payment_id'=>(string)$order['reference'],'status'=>$status,'amount'=>number_format((float)($order['confirmed_amount']??$order['requested_amount']??$order['amount']),2,'.',''),'currency'=>'INR','payment_url'=>pay_url('/p/'.rawurlencode((string)$merchant['customer_code']).'?ref='.rawurlencode((string)$order['reference'])),'return_url'=>(string)($order['return_url']??''),'utr'=>$status==='SUCCESS'?$utr:'','verified_utr'=>$utr,'buyer_name'=>$buyer,'created_at'=>gmdate('c',strtotime($created.' UTC')?:time()),'expires_at'=>gmdate('c',$expires)];
    }

    private static function webhookPayload(array $order,array $merchant,array $transaction): array {
        $utr=trim((string)($transaction['rrn']??$transaction['upi_txn_id']??''));
        return ['merchant_id'=>(string)$merchant['merchant_id'],'order_id'=>(string)$order['order_id'],'payment_id'=>(string)$transaction['reference'],'amount'=>number_format((float)($transaction['confirmed_amount']??$transaction['requested_amount']??$order['amount']),2,'.',''),'currency'=>'INR','status'=>strtoupper((string)$transaction['status']),'utr'=>$utr,'paid_at'=>strtoupper((string)$transaction['status'])==='SUCCESS'?gmdate('c',strtotime((string)$transaction['updated_at'].' UTC')?:time()):null,'return_url'=>(string)($order['return_url']??'')];
    }

    public static function assertIdempotentOrder(array $existing,float $requestedAmount): void {if(abs(round((float)($existing['amount']??$existing['requested_amount']??0),2)-round($requestedAmount,2))>0.0001)throw new InvalidArgumentException('order_id already exists with a different amount');}
    private static function validateOrderId(string $value): string {$value=trim($value);if(!preg_match('/^[A-Za-z0-9._-]{3,80}$/',$value))throw new InvalidArgumentException('Invalid order_id');return $value;}
    private static function validateAmount(mixed $value): float {$value=trim((string)$value);if(!preg_match('/^\d+(?:\.\d{1,2})?$/',$value))throw new InvalidArgumentException('Invalid amount');$amount=(float)$value;if($amount<1||$amount>200000)throw new InvalidArgumentException('Amount must be between 1 and 200000');return $amount;}
    private static function clean(mixed $value,int $max): string {$value=trim(preg_replace('/[\x00-\x1F\x7F]/u','',(string)$value)??'');return function_exists('mb_substr')?mb_substr($value,0,$max):substr($value,0,$max);}
    private static function sameOrigin(string $a,string $b): bool {$x=parse_url($a);$y=parse_url($b);return (bool)($x&&$y&&strtolower((string)($x['scheme']??''))===strtolower((string)($y['scheme']??''))&&strtolower((string)($x['host']??''))===strtolower((string)($y['host']??''))&&(int)($x['port']??0)===(int)($y['port']??0));}
}
