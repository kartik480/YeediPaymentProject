<?php
declare(strict_types=1);
final class SmsService {
    public static function settings(PDO $db): array {
        $r=$db->query('SELECT * FROM sms_gateway_settings WHERE id=1')->fetch();
        if(!$r){$db->exec("INSERT INTO sms_gateway_settings(id,enabled,gateway_name,http_method,content_type,auth_header_name,auth_prefix,sender_id,request_template,otp_message,pending_message,success_message,failed_message,expired_message,updated_at) VALUES(1,0,'Generic SMS Gateway','POST','json','Authorization','Bearer ','YEEDIPAY','{\"phone\":\"{phone}\",\"message\":\"{message}\",\"sender_id\":\"{sender_id}\",\"template_id\":\"{template_id}\"}','Your YEEDIPAY OTP is {otp}. Valid for 5 minutes.','YEEDIPAY payment initiated. Ref {reference}. Status PENDING. Please wait for verification.','YEEDIPAY payment SUCCESS. Ref {reference}. Amount Rs.{amount}.','YEEDIPAY payment FAILED. Ref {reference}.','YEEDIPAY payment verification window expired. Ref {reference}. Please contact support if payment was made.',UTC_TIMESTAMP())");$r=$db->query('SELECT * FROM sms_gateway_settings WHERE id=1')->fetch();}
        return $r;
    }
    private static function render(string $text,array $ctx): string { foreach($ctx as $k=>$v)$text=str_replace('{'.$k.'}',(string)$v,$text);return $text; }
    private static function sendHttp(array $s,string $phone,string $message,string $templateId=''): array {
        if(!(int)$s['enabled'] || empty($s['endpoint_url']))throw new RuntimeException('SMS gateway is not configured/enabled.');
        $ctx=['phone'=>$phone,'message'=>$message,'sender_id'=>$s['sender_id']??'','template_id'=>$templateId];$body=self::render((string)$s['request_template'],$ctx);$method=strtoupper((string)$s['http_method']);$type=strtolower((string)$s['content_type']);
        $headers=[];$token=Crypto::decrypt((string)$s['auth_token_encrypted']);if(!empty($s['auth_header_name'])&&$token!=='')$headers[]=$s['auth_header_name'].': '.($s['auth_prefix']??'').$token;
        $url=(string)$s['endpoint_url']; $content='';
        if($type==='json'){$decoded=json_decode($body,true);if(!is_array($decoded))throw new RuntimeException('SMS JSON request template is invalid after replacement');$content=json_encode($decoded,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$headers[]='Content-Type: application/json';}
        elseif($type==='form'){$content=$body;$headers[]='Content-Type: application/x-www-form-urlencoded';}
        elseif($type==='query'){$url.=(str_contains($url,'?')?'&':'?').$body;}else throw new RuntimeException('SMS content type must be json, form or query');
        if(function_exists('curl_init')){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers]);if($method!=='GET'&&$type!=='query')curl_setopt($ch,CURLOPT_POSTFIELDS,$content);$resp=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);if($resp===false||$code<200||$code>=300)throw new RuntimeException('SMS HTTP error '.$code.' '.$err);return ['ok'=>true,'status_code'=>$code];}
        $opts=['http'=>['method'=>$method,'timeout'=>20,'ignore_errors'=>true,'header'=>implode("\r\n",$headers)]];if($method!=='GET'&&$type!=='query')$opts['http']['content']=$content;$resp=@file_get_contents($url,false,stream_context_create($opts));$code=0;if(isset($http_response_header[0])&&preg_match('/\s(\d{3})\s/',$http_response_header[0],$m))$code=(int)$m[1];if($resp===false||$code<200||$code>=300)throw new RuntimeException('SMS HTTP error '.$code);return ['ok'=>true,'status_code'=>$code];
    }
    public static function sendOtp(PDO $db,string $phone,string $otp): array { $s=self::settings($db);return self::sendHttp($s,$phone,self::render((string)$s['otp_message'],['otp'=>$otp]),(string)$s['otp_template_id']); }
    public static function sendTest(PDO $db,string $phone,string $message): array { $s=self::settings($db);return self::sendHttp($s,$phone,$message,''); }
    public static function enqueueTxn(PDO $db,int $txnId,string $status): void {
        $s=self::settings($db);if(!(int)$s['enabled'])return;$st=$db->prepare('SELECT t.*,c.code customer_code,c.name customer_name,c.phone,b.bank_name FROM transactions t JOIN customers c ON c.id=t.customer_id JOIN bank_profiles b ON b.id=t.bank_id WHERE t.id=?');$st->execute([$txnId]);$t=$st->fetch();if(!$t)return;
        $status=strtoupper($status);$map=['PENDING'=>['pending_message','pending_template_id'],'SUCCESS'=>['success_message','success_template_id'],'FAILED'=>['failed_message','failed_template_id'],'EXPIRED'=>['expired_message','expired_template_id']];if(!isset($map[$status]))return;[$mf,$tf]=$map[$status];$ctx=['reference'=>$t['reference'],'amount'=>$t['confirmed_amount']??$t['requested_amount']??'','status'=>$status,'customer_code'=>$t['customer_code'],'customer_name'=>$t['customer_name'],'bank'=>$t['bank_name'],'rrn'=>$t['rrn']??'','upi_txn_id'=>$t['upi_txn_id']??''];$msg=self::render((string)$s[$mf],$ctx);$key='txn:'.$txnId.':'.$status;
        $q=$db->prepare('INSERT IGNORE INTO sms_outbox(event_key,phone,message,template_id,status,attempts,next_attempt_at,created_at) VALUES(?,?,?,?,\'QUEUED\',0,UTC_TIMESTAMP(),UTC_TIMESTAMP())');$q->execute([$key,$t['phone'],$msg,(string)$s[$tf]]);
    }
    public static function processOutbox(PDO $db,int $limit=50): array {
        $s=self::settings($db);if(!(int)$s['enabled']||empty($s['endpoint_url']))return ['sent'=>0,'failed'=>0,'skipped'=>true];$q=$db->prepare("SELECT * FROM sms_outbox WHERE status IN ('QUEUED','RETRY') AND next_attempt_at<=UTC_TIMESTAMP() ORDER BY id LIMIT ?");$q->bindValue(1,$limit,PDO::PARAM_INT);$q->execute();$rows=$q->fetchAll();$sent=$failed=0;
        foreach($rows as $r){$attempt=(int)$r['attempts']+1;try{self::sendHttp($s,$r['phone'],$r['message'],$r['template_id']??'');$u=$db->prepare("UPDATE sms_outbox SET status='SENT',attempts=?,last_error='',sent_at=UTC_TIMESTAMP() WHERE id=?");$u->execute([$attempt,$r['id']]);$sent++;}catch(Throwable $e){$failed++;$status=$attempt>=5?'FAILED':'RETRY';$mins=min(60,2**$attempt);$u=$db->prepare("UPDATE sms_outbox SET status=?,attempts=?,last_error=?,next_attempt_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? MINUTE) WHERE id=?");$u->execute([$status,$attempt,substr($e->getMessage(),0,2000),$mins,$r['id']]);}}
        return ['sent'=>$sent,'failed'=>$failed,'skipped'=>false];
    }
}
