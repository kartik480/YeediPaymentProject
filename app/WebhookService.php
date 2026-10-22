<?php
declare(strict_types=1);

final class WebhookService {
    public static function ensureSchema(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS merchant_webhook_deliveries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id VARCHAR(80) NOT NULL,
            merchant_account_id BIGINT UNSIGNED NOT NULL,
            merchant_order_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            webhook_url VARCHAR(500) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            signature VARCHAR(128) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
            attempts INT NOT NULL DEFAULT 0,
            next_retry_at DATETIME NULL,
            response_code INT NULL,
            response_body VARCHAR(1000) NULL,
            last_error VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_merchant_webhook_event(event_id),
            INDEX idx_merchant_webhook_pending(status,next_retry_at,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function sign(string $payload,string $secret): string {
        return hash_hmac('sha256',$payload,$secret);
    }

    public static function isAllowedWebhookUrl(string $url): bool {
        $p=parse_url(trim($url));
        if(!$p||empty($p['host'])||isset($p['user'])||isset($p['pass']))return false;
        $scheme=strtolower((string)($p['scheme']??''));
        if($scheme!=='https'&&!($scheme==='http'&&Env::get('WEBHOOK_ALLOW_HTTP','0')==='1'))return false;
        $host=strtolower(trim((string)$p['host'],'[]'));
        if($host===''||$host==='localhost'||str_ends_with($host,'.localhost')||str_ends_with($host,'.local')||str_ends_with($host,'.internal'))return false;
        if(filter_var($host,FILTER_VALIDATE_IP)){
            return filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)!==false;
        }
        return str_contains($host,'.');
    }

    public static function queue(PDO $db,array $merchant,array $order,string $eventType,array $payload): void {
        self::ensureSchema($db);
        $url=trim((string)($merchant['webhook_url']??''));
        if($url===''||!self::isAllowedWebhookUrl($url))return;
        $secret=Crypto::decrypt((string)($merchant['webhook_secret_encrypted']??''));
        if($secret==='')return;
        $eventId='evt_'.bin2hex(random_bytes(12));
        $payload['event_id']=$eventId;
        $payload['event']=$eventType;
        $json=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if($json===false)return;
        $st=$db->prepare("INSERT INTO merchant_webhook_deliveries(event_id,merchant_account_id,merchant_order_id,event_type,webhook_url,payload_json,signature,status,attempts,next_retry_at,created_at,updated_at)
                          VALUES(?,?,?,?,?,?,?,'PENDING',0,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $st->execute([$eventId,(int)$merchant['id'],(int)$order['id'],$eventType,$url,$json,self::sign($json,$secret)]);
    }

    public static function deliverPending(PDO $db,int $limit=5): int {
        self::ensureSchema($db);
        $limit=max(1,min(20,$limit));
        $rows=$db->query("SELECT d.* FROM merchant_webhook_deliveries d
                          WHERE d.status IN ('PENDING','RETRY')
                            AND (d.next_retry_at IS NULL OR d.next_retry_at<=UTC_TIMESTAMP())
                          ORDER BY d.id ASC LIMIT {$limit}")->fetchAll();
        $done=0;
        foreach($rows as $row){
            $claim=$db->prepare("UPDATE merchant_webhook_deliveries SET status='SENDING',updated_at=UTC_TIMESTAMP()
                                 WHERE id=? AND status IN ('PENDING','RETRY')
                                   AND (next_retry_at IS NULL OR next_retry_at<=UTC_TIMESTAMP())");
            $claim->execute([(int)$row['id']]);
            if($claim->rowCount()!==1)continue;
            if(self::deliverOne($db,$row))$done++;
        }
        return $done;
    }

    private static function deliverOne(PDO $db,array $row): bool {
        $url=(string)$row['webhook_url'];
        if(!self::isAllowedWebhookUrl($url)){
            self::markFailed($db,(int)$row['id'],(int)$row['attempts']+1,0,'','Webhook URL is not allowed',true);
            return false;
        }
        $payload=(string)$row['payload_json'];
        $headers=['Content-Type: application/json','User-Agent: YEEDIPAY-Webhook/2.0','X-Yeedipay-Event: '.(string)$row['event_type'],'X-Yeedipay-Event-Id: '.(string)$row['event_id'],'X-Yeedipay-Signature: '.(string)$row['signature']];
        $code=0;$body='';$error='';
        if(function_exists('curl_init')){
            $ch=curl_init($url);
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false]);
            $res=curl_exec($ch);
            if($res===false)$error=(string)curl_error($ch);else$body=(string)$res;
            $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
            curl_close($ch);
        }else{
            $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>implode("\r\n",$headers)."\r\n",'content'=>$payload,'timeout'=>8,'ignore_errors'=>true,'follow_location'=>0]]);
            $res=@file_get_contents($url,false,$ctx);
            if($res===false)$error='HTTP request failed';else$body=(string)$res;
            if(!empty($http_response_header[0])&&preg_match('/\s(\d{3})\s/',(string)$http_response_header[0],$m))$code=(int)$m[1];
        }
        $attempts=(int)$row['attempts']+1;
        if($code>=200&&$code<300){
            $db->prepare("UPDATE merchant_webhook_deliveries SET status='DELIVERED',attempts=?,response_code=?,response_body=?,last_error=NULL,updated_at=UTC_TIMESTAMP() WHERE id=?")
               ->execute([$attempts,$code,substr($body,0,1000),(int)$row['id']]);
            return true;
        }
        self::markFailed($db,(int)$row['id'],$attempts,$code,$body,$error,false);
        return false;
    }

    private static function markFailed(PDO $db,int $id,int $attempts,int $code,string $body,string $error,bool $permanent): void {
        $max=max(1,Env::int('WEBHOOK_MAX_ATTEMPTS',6));
        $giveUp=$permanent||$attempts>=$max;
        if($giveUp){
            $db->prepare("UPDATE merchant_webhook_deliveries SET status='FAILED',attempts=?,response_code=?,response_body=?,last_error=?,next_retry_at=NULL,updated_at=UTC_TIMESTAMP() WHERE id=?")
               ->execute([$attempts,$code?:null,substr($body,0,1000),substr($error,0,500),$id]);
            return;
        }
        $next=gmdate('Y-m-d H:i:s',time()+min(3600,30*(2**max(0,$attempts-1))));
        $db->prepare("UPDATE merchant_webhook_deliveries SET status='RETRY',attempts=?,response_code=?,response_body=?,last_error=?,next_retry_at=?,updated_at=UTC_TIMESTAMP() WHERE id=?")
           ->execute([$attempts,$code?:null,substr($body,0,1000),substr($error,0,500),$next,$id]);
    }
}
