<?php
declare(strict_types=1);

final class Log {
    public static function write(string $channel,string $event,array $context=[]): void { $dir=YEEDIPAY_ROOT.'/storage/logs';if(!is_dir($dir))@mkdir($dir,0755,true);@file_put_contents($dir.'/app.log',gmdate('c').' '.$channel.' '.$event.' '.json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND); }
    public static function safeThrowable(Throwable $e): string { return $e->getMessage(); }
}

final class Auth {
    public static function customerLogin(int $id): void { session_regenerate_id(true);$_SESSION['customer_id']=$id;$_SESSION['customer_login_at']=time(); }
    public static function customerLogout(): void { unset($_SESSION['customer_id'],$_SESSION['customer_login_at']);session_regenerate_id(true); }
    public static function customerId(): int { return (int)($_SESSION['customer_id']??0); }
    public static function isCustomer(int $id): bool { return $id>0 && self::customerId()===$id; }
}

final class Crypto {
    private static function key(): string { return hash('sha256',(string)Env::get('APP_KEY','yeedipay-change-this-key'),true); }
    public static function encrypt(string $plain): string { if($plain==='')return '';if(!function_exists('openssl_encrypt'))throw new RuntimeException('OpenSSL extension is required for secure secret storage');$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new RuntimeException('Could not encrypt secret');return 'v1:'.base64_encode($iv.$tag.$cipher); }
    public static function decrypt(string $cipher): string { if(str_starts_with($cipher,'v1:')&&function_exists('openssl_decrypt')){$raw=base64_decode(substr($cipher,3),true);if($raw!==false&&strlen($raw)>28){$iv=substr($raw,0,12);$tag=substr($raw,12,16);$body=substr($raw,28);$plain=openssl_decrypt($body,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);return $plain===false?'':$plain;}}if(str_starts_with($cipher,'b64:'))$cipher=substr($cipher,4);$plain=base64_decode($cipher,true);return $plain===false?'':$plain; }
}

final class AdcLink { public static function ensureSchema(PDO $db): void{} public static function attach(PDO $db,string $ref,array $bank,?float $amount): ?array{return null;} public static function syncReference(PDO $db,string $ref): void{} }
final class LimitService { public static function check(PDO $db,array $customer,?float $amount): array{return [true,''];} public static function evaluateConfirmed(PDO $db,int $transactionId): void{} }
final class PaymentConfirmService {
    public static function intentForAttempt(array $bank,?float $amount,string $reference): string { return BankService::customerAmountIntent($bank,$amount,$reference); }
    public static function refresh(PDO $db,array $transaction): array { $ref=trim((string)($transaction['reference']??''));if($ref==='')return $transaction;return TransactionService::txnByRef($db,$ref)?:$transaction; }
    public static function activateService(PDO $db,int $customerId): void { $db->prepare('UPDATE customers SET service_activated=1,service_activated_at=COALESCE(service_activated_at,UTC_TIMESTAMP()) WHERE id=?')->execute([$customerId]); }
}

final class SmsService {
    private static function send(string $phone,string $message): void {
        $phone=preg_replace('/\D+/','',$phone)??'';if($phone==='')throw new RuntimeException('Customer mobile number is missing');
        $url=trim((string)Env::get('SMS_API_URL',''));$token=trim((string)Env::get('SMS_API_TOKEN',''));
        if($url===''){Log::write('sms','queued_local',['phone'=>mask_phone($phone),'message'=>$message]);return;}
        $payload=json_encode(['to'=>$phone,'message'=>$message],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $headers="Content-Type: application/json\r\n".($token!==''?"Authorization: Bearer {$token}\r\n":'');
        $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>$headers,'content'=>$payload,'timeout'=>8,'ignore_errors'=>true]]);
        $r=@file_get_contents($url,false,$ctx);if($r===false)throw new RuntimeException('SMS provider request failed');
    }
    public static function enqueueTxn(PDO $db,int $transactionId,string $status): void { Log::write('sms','txn_status',['transaction_id'=>$transactionId,'status'=>$status]); }
    public static function sendOtp(PDO $db,string $phone,string $otp): void { self::send($phone,'YEEDIPAY OTP: '.$otp.'. Valid for 5 minutes. Do not share this OTP.');if((string)Env::get('DEV_SHOW_OTP','0')==='1')$_SESSION['dev_last_otp']=$otp; }
    public static function sendPaymentSuccess(PDO $db,int $transactionId): void { $st=$db->prepare('SELECT t.reference,COALESCE(t.confirmed_amount,t.requested_amount) amount,c.phone FROM transactions t JOIN customers c ON c.id=t.customer_id WHERE t.id=?');$st->execute([$transactionId]);$r=$st->fetch();if(!$r)return;self::send((string)$r['phone'],'YEEDIPAY payment successful. Ref '.$r['reference'].' Amount INR '.number_format((float)$r['amount'],2).'.'); }
}

final class EmailService {
    public static function ensureSchema(PDO $db): void{}
    public static function enqueueTxn(PDO $db,int $transactionId,string $status): void { Log::write('email','txn_status',['transaction_id'=>$transactionId,'status'=>$status]); }
}
