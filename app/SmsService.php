<?php
declare(strict_types=1);
final class SmsService {
    public const DLT_OTP_TEMPLATE_ID = '1707173641664963524';
    public const DLT_SUCCESS_TEMPLATE_ID = '1707174530074657515';
    public const DLT_OTP_MESSAGE = 'OTP for verification is {otp}. Please do not share this otp with anyone. --JAYALAKSHMI MUTUALLY AIDED COOPERATIVE THRIFT AND CREDIT SOCIETY LTD';
    public const DLT_SUCCESS_MESSAGE = 'Dear Customer, Transaction with reference number {reference} for Rs.{amount} has been credited to the beneficiary account XXXXX{account_tail} on {sms_date} -JAYALAKSHMI MUTUALLY AIDED COOPERATIVE THRIFT AND CREDIT SOCIETY LTD';
    public const DEFAULT_SUCCESS_MESSAGE = self::DLT_SUCCESS_MESSAGE;
    private const LEGACY_SUCCESS_MESSAGE = 'YEEDIPAY payment SUCCESS. Ref {reference}. Amount Rs.{amount}.';
    private const LEGACY_SUCCESS_MESSAGE_2 = 'YEEDIPAY payment SUCCESS. Rs.{amount} received. A/c {account_number}. UTR {rrn}. Ref {reference}.';

    public static function settings(PDO $db): array {
        $r=$db->query('SELECT * FROM sms_gateway_settings WHERE id=1')->fetch();
        if(!$r){$db->exec("INSERT INTO sms_gateway_settings(id,enabled,gateway_name,http_method,content_type,auth_header_name,auth_prefix,sender_id,request_template,otp_message,pending_message,success_message,failed_message,expired_message,updated_at) VALUES(1,0,'Generic SMS Gateway','POST','json','Authorization','Bearer ','YEEDIPAY','{\"phone\":\"{phone}\",\"message\":\"{message}\",\"sender_id\":\"{sender_id}\",\"template_id\":\"{template_id}\"}','".str_replace("'","''",self::DLT_OTP_MESSAGE)."','YEEDIPAY payment initiated. Ref {reference}. Status PENDING. Please wait for verification.','".str_replace("'","''",self::DLT_SUCCESS_MESSAGE)."','YEEDIPAY payment FAILED. Ref {reference}.','YEEDIPAY payment verification window expired. Ref {reference}. Please contact support if payment was made.',UTC_TIMESTAMP())");$r=$db->query('SELECT * FROM sms_gateway_settings WHERE id=1')->fetch();}
        return self::syncDltTemplates($db, $r);
    }
    private static function render(string $text,array $ctx): string { foreach($ctx as $k=>$v)$text=str_replace('{'.$k.'}',(string)$v,$text);return $text; }

    private static function dltVar(string $v, int $max = 30): string {
        $v = trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
        if (function_exists('mb_strlen') && mb_strlen($v) > $max) $v = mb_substr($v, 0, $max);
        elseif (strlen($v) > $max) $v = substr($v, 0, $max);
        return $v === '' ? '-' : $v;
    }

    private static function accountTail(string $acc): string {
        $acc = preg_replace('/[^A-Za-z0-9]/', '', $acc) ?? '';
        if ($acc === '') return '0000';
        return self::dltVar(strlen($acc) > 6 ? substr($acc, -6) : $acc);
    }

    private static function smsDate(): string {
        try {
            $tz = new DateTimeZone((string)Env::get('APP_TIMEZONE', 'Asia/Kolkata'));
        } catch (Throwable $e) {
            $tz = new DateTimeZone('Asia/Kolkata');
        }
        return (new DateTime('now', $tz))->format('d-m-Y');
    }

    private static function creditTxnContext(array $t): array {
        $amtRaw = $t['confirmed_amount'] ?? $t['requested_amount'] ?? '';
        $amt = is_numeric((string)$amtRaw) ? number_format((float)$amtRaw, 2, '.', '') : (string)$amtRaw;
        $utr = (string)($t['rrn'] ?: ($t['customer_reported_utr'] ?? '') ?: ($t['upi_txn_id'] ?? '') ?: '');
        $name = trim((string)($t['customer_name'] ?? $t['name'] ?? 'Customer'));
        if ($name === '') $name = 'Customer';
        $ref = trim((string)($t['reference'] ?? ''));
        return [
            'reference' => self::dltVar($ref !== '' ? $ref : '-'),
            'amount' => self::dltVar($amt),
            'account_tail' => self::accountTail((string)($t['account_number'] ?? '')),
            'account_number' => (string)($t['account_number'] ?? ''),
            'sms_date' => self::smsDate(),
            'channel' => 'UPI',
            'purpose' => 'UPI',
            'rrn' => self::dltVar($utr),
            'utr' => self::dltVar($utr),
            'payer' => self::dltVar($name),
            'sent_to' => self::dltVar('JAYALAKSHMI SOCIETY'),
            'customer_name' => $name,
        ];
    }

    private static function syncDltTemplates(PDO $db, array $r): array {
        $tid = trim((string)($r['success_template_id'] ?? ''));
        $msg = (string)($r['success_message'] ?? '');
        $otpTid = trim((string)($r['otp_template_id'] ?? ''));
        $needs = $tid !== self::DLT_SUCCESS_TEMPLATE_ID
            || $otpTid !== self::DLT_OTP_TEMPLATE_ID
            || $tid === '1707174098811006215'
            || $tid === '1707174098811008215'
            || $tid === '123456789012'
            || $otpTid === '' || $otpTid === '123456789012'
            || $msg === self::LEGACY_SUCCESS_MESSAGE || $msg === self::LEGACY_SUCCESS_MESSAGE_2
            || $tid === '1707174290348220580'
            || str_contains($msg, 'YEEDIPAY payment SUCCESS')
            || str_contains($msg, 'Credited to your A/c No')
            || str_contains($msg, 'has been debited')
            || !str_contains($msg, 'has been credited to the beneficiary account');
        if (!$needs) return $r;
        $db->prepare('UPDATE sms_gateway_settings SET otp_template_id=?,success_template_id=?,pending_template_id=?,failed_template_id=?,expired_template_id=?,otp_message=?,success_message=?,updated_at=UTC_TIMESTAMP() WHERE id=1')->execute([
            self::DLT_OTP_TEMPLATE_ID,
            self::DLT_SUCCESS_TEMPLATE_ID,
            '',
            '',
            '',
            self::DLT_OTP_MESSAGE,
            self::DLT_SUCCESS_MESSAGE,
        ]);
        return $db->query('SELECT * FROM sms_gateway_settings WHERE id=1')->fetch() ?: $r;
    }

    public static function normalizeMobile(string $phone): string {
        $d = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($d) === 10) return '91'.$d;
        if (strlen($d) === 11 && str_starts_with($d, '0')) return '91'.substr($d, 1);
        if (strlen($d) === 12 && str_starts_with($d, '91')) return $d;
        if (str_starts_with($d, '0091') && strlen($d) >= 14) return substr($d, 2);
        return $d;
    }

    private static function httpGet(string $url, bool $validateSend = true): array {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($resp === false) throw new RuntimeException('SMS HTTP error '.$err);
            if ($code < 200 || $code >= 300) throw new RuntimeException('SMS HTTP error '.$code.' '.substr((string)$resp, 0, 200));
            if ($validateSend) self::assertProviderAccepted((string)$resp);
            return ['ok' => true, 'status_code' => $code, 'body' => (string)$resp];
        }
        $resp = @file_get_contents($url);
        if ($resp === false) throw new RuntimeException('SMS HTTP error (file_get_contents failed)');
        if ($validateSend) self::assertProviderAccepted((string)$resp);
        return ['ok' => true, 'status_code' => 200, 'body' => (string)$resp];
    }

    private static function assertProviderAccepted(string $body): void {
        $raw = trim($body);
        if ($raw === '') {
            throw new RuntimeException('SMS provider returned an empty response');
        }
        $norm = str_replace("'", '"', $raw);
        $j = json_decode($norm, true);
        if (is_array($j) && (isset($j['Reports']) || isset($j['reports']))) {
            return;
        }
        if (is_array($j)) {
            $err = (string)($j['Error'] ?? $j['error'] ?? $j['ERROR'] ?? '');
            if ($err !== '') throw new RuntimeException('SMS provider: '.$err);
        } elseif (preg_match("/['\"]Error['\"]\s*:\s*['\"]([^'\"]+)/i", $raw, $m)) {
            throw new RuntimeException('SMS provider: '.$m[1]);
        }
        if (preg_match('/\b(invalid|not matched|reject)/i', $raw) && !preg_match('/\b(ok|success|sent|reports)\b/i', $raw)) {
            throw new RuntimeException('SMS provider: '.substr($raw, 0, 240));
        }
    }

    private static function sendSmslogin(array $s, string $phone, string $message, string $templateId = ''): array {
        $username = trim((string)Env::get('SMS_USERNAME', 'JAYALAXMI'));
        $apikey = trim((string)Env::get('SMS_APIKEY', ''));
        if ($apikey === '') $apikey = Crypto::decrypt((string)($s['auth_token_encrypted'] ?? ''));
        $senderid = trim((string)Env::get('SMS_SENDER_ID', (string)($s['sender_id'] ?? 'JLKCBK')));
        if ($senderid === '') $senderid = 'JLKCBK';
        $mobile = self::normalizeMobile($phone);
        $templateid = trim($templateId);
        if ($templateid === '') $templateid = trim((string)Env::get('SMS_SUCCESS_TEMPLATE_ID', ''));
        if ($templateid === '' || in_array($templateid, ['123456789012', '1707174098811006215', '1707174098811008215', '1707174290348220580'], true)) $templateid = self::DLT_SUCCESS_TEMPLATE_ID;
        if ($username === '' || $apikey === '') throw new RuntimeException('Set SMS_USERNAME and SMS_APIKEY in .env');
        if ($mobile === '') throw new RuntimeException('Mobile number is required');
        $url = 'https://smslogin.co/v3/api.php?username='.$username
            .'&apikey='.$apikey
            .'&senderid='.$senderid
            .'&mobile='.$mobile
            .'&message='.urlencode($message)
            .'&templateid='.$templateid;
        return self::httpGet($url);
    }

    private static function sendHttp(array $s, string $phone, string $message, string $templateId = ''): array {
        if (!(int)$s['enabled'] || empty($s['endpoint_url'])) throw new RuntimeException('SMS gateway is not configured/enabled.');
        $phone = self::normalizeMobile($phone);
        $endpoint = (string)$s['endpoint_url'];
        if (str_contains(strtolower($endpoint), 'smslogin.co')) {
            return self::sendSmslogin($s, $phone, $message, $templateId);
        }
        $token = Crypto::decrypt((string)($s['auth_token_encrypted'] ?? ''));
        $username = trim((string)Env::get('SMS_USERNAME', ''));
        $ctx = [
            'phone' => $phone,
            'message' => $message,
            'sender_id' => (string)($s['sender_id'] ?? ''),
            'template_id' => $templateId,
            'apikey' => $token,
            'username' => $username,
        ];
        $tpl = (string)$s['request_template'];
        $method = strtoupper((string)$s['http_method']);
        $type = strtolower((string)$s['content_type']);
        $headers = [];
        $headerName = trim((string)($s['auth_header_name'] ?? ''));
        if ($headerName !== '' && $token !== '') $headers[] = $headerName.': '.($s['auth_prefix'] ?? '').$token;
        $url = $endpoint;
        $content = '';
        if ($type === 'query') {
            $enc = [];
            foreach ($ctx as $k => $v) $enc[$k] = rawurlencode((string)$v);
            $qs = self::render($tpl, $enc);
            $url .= (str_contains($url, '?') ? '&' : '?').$qs;
            return self::httpGet($url);
        }
        $body = self::render($tpl, $ctx);
        if ($type === 'json') {
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) throw new RuntimeException('SMS JSON request template is invalid after replacement');
            $content = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
        } elseif ($type === 'form') {
            $content = $body;
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } else {
            throw new RuntimeException('SMS content type must be json, form or query');
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if ($method !== 'GET') curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($resp === false || $code < 200 || $code >= 300) throw new RuntimeException('SMS HTTP error '.$code.' '.$err);
            self::assertProviderAccepted((string)$resp);
            return ['ok' => true, 'status_code' => $code, 'body' => (string)$resp];
        }
        $opts = ['http' => ['method' => $method, 'timeout' => 20, 'ignore_errors' => true, 'header' => implode("\r\n", $headers)]];
        if ($method !== 'GET') $opts['http']['content'] = $content;
        $resp = @file_get_contents($url, false, stream_context_create($opts));
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $code = (int)$m[1];
        if ($resp === false || $code < 200 || $code >= 300) throw new RuntimeException('SMS HTTP error '.$code);
        self::assertProviderAccepted((string)$resp);
        return ['ok' => true, 'status_code' => $code, 'body' => (string)$resp];
    }

    public static function applyDemo(PDO $db): void {
        self::settings($db);
        $cfg = require YEEDIPAY_ROOT.'/sms/config.php';
        $token = (string)($cfg['token'] ?? '');
        $sql = 'UPDATE sms_gateway_settings SET enabled=1,gateway_name=?,endpoint_url=?,http_method=?,content_type=?,auth_header_name=?,auth_prefix=?,auth_token_encrypted=?,sender_id=?,request_template=?,updated_at=UTC_TIMESTAMP() WHERE id=1';
        $db->prepare($sql)->execute([
            'YEEDIPAY Demo SMS Gateway',
            sms_demo_url('/send.php'),
            'POST',
            'json',
            'Authorization',
            'Bearer ',
            Crypto::encrypt($token),
            'YEEDIPAY',
            '{"phone":"{phone}","message":"{message}","sender_id":"{sender_id}","template_id":"{template_id}"}',
        ]);
    }

    public static function applySmslogin(PDO $db): void {
        self::settings($db);
        $user = trim((string)Env::get('SMS_USERNAME', 'JAYALAXMI'));
        $key = trim((string)Env::get('SMS_APIKEY', ''));
        $sender = trim((string)Env::get('SMS_SENDER_ID', 'JLKCBK'));
        if ($key === '') throw new RuntimeException('Set SMS_APIKEY in .env');
        if ($user === '') throw new RuntimeException('Set SMS_USERNAME in .env');
        $sql = 'UPDATE sms_gateway_settings SET enabled=1,gateway_name=?,endpoint_url=?,http_method=?,content_type=?,auth_header_name=?,auth_prefix=?,auth_token_encrypted=?,sender_id=?,request_template=?,updated_at=UTC_TIMESTAMP() WHERE id=1';
        $db->prepare($sql)->execute([
            'smslogin.co',
            'https://smslogin.co/v3/api.php',
            'GET',
            'query',
            '',
            '',
            Crypto::encrypt($key),
            $sender !== '' ? $sender : 'JLKCBK',
            'username='.rawurlencode($user).'&apikey={apikey}&senderid={sender_id}&mobile={phone}&message={message}&templateid={template_id}',
        ]);
        $defaults = [
            'otp_template_id' => self::DLT_OTP_TEMPLATE_ID,
            'pending_template_id' => '',
            'success_template_id' => self::DLT_SUCCESS_TEMPLATE_ID,
            'failed_template_id' => '',
            'expired_template_id' => '',
        ];
        $tplMap = [
            'otp_template_id' => 'SMS_OTP_TEMPLATE_ID',
            'pending_template_id' => 'SMS_PENDING_TEMPLATE_ID',
            'success_template_id' => 'SMS_SUCCESS_TEMPLATE_ID',
            'failed_template_id' => 'SMS_FAILED_TEMPLATE_ID',
            'expired_template_id' => 'SMS_EXPIRED_TEMPLATE_ID',
        ];
        $sets = [];
        $args = [];
        foreach ($tplMap as $col => $envKey) {
            $v = trim((string)Env::get($envKey, ''));
            if ($v === '' || in_array($v, ['123456789012', '1707174098811006215', '1707174098811008215', '1707174290348220580'], true)) $v = $defaults[$col];
            $sets[] = $col.'=?';
            $args[] = $v;
        }
        $sets[] = 'otp_message=?';
        $args[] = self::DLT_OTP_MESSAGE;
        $successMsg = trim((string)Env::get('SMS_SUCCESS_MESSAGE', ''));
        $sets[] = 'success_message=?';
        $args[] = $successMsg !== '' ? $successMsg : self::DLT_SUCCESS_MESSAGE;
        if ($sets) {
            $db->prepare('UPDATE sms_gateway_settings SET '.implode(',', $sets).',updated_at=UTC_TIMESTAMP() WHERE id=1')->execute($args);
        }
    }

    public static function deliveryReport(PDO $db, string $campid): array {
        $s = self::settings($db);
        $campid = trim($campid);
        if ($campid === '') throw new RuntimeException('Campaign ID is required');
        $apikey = Crypto::decrypt((string)($s['auth_token_encrypted'] ?? ''));
        $user = trim((string)Env::get('SMS_USERNAME', 'JAYALAXMI'));
        if ($apikey === '' || $user === '') throw new RuntimeException('smslogin.co username/API key is not configured');
        $url = 'https://smslogin.co/v3/api.php?'.http_build_query([
            'username' => $user,
            'apikey' => $apikey,
            'campid' => $campid,
        ]);
        return self::httpGet($url, false);
    }

    public static function sendOtp(PDO $db, string $phone, string $otp): array {
        $s = self::settings($db);
        return self::sendHttp($s, $phone, self::render((string)$s['otp_message'], ['otp' => self::dltVar($otp)]), (string)$s['otp_template_id']);
    }
    public static function sampleSuccessSms(): string {
        return self::render(self::DLT_SUCCESS_MESSAGE, self::creditTxnContext([
            'reference' => 'YDP33234',
            'confirmed_amount' => '1.00',
            'account_number' => 'YD0001000001',
            'customer_name' => 'Customer',
            'rrn' => '123456789012',
        ]));
    }
    public static function sendTest(PDO $db, string $phone, string $message): array {
        $s = self::settings($db);
        $tpl = trim((string)($s['success_template_id'] ?? ''));
        if ($tpl === '') $tpl = trim((string)($s['otp_template_id'] ?? ''));
        return self::sendHttp($s, $phone, $message, $tpl);
    }

    /** Success SMS after this customer saved a valid UTR. Goes only to customers.phone. */
    public static function sendPaymentSuccess(PDO $db, int $txnId): bool {
        $s = self::settings($db);
        if (!(int)$s['enabled']) return false;
        $st = $db->prepare('SELECT t.*,c.phone,c.name customer_name,c.account_number FROM transactions t JOIN customers c ON c.id=t.customer_id WHERE t.id=?');
        $st->execute([$txnId]);
        $t = $st->fetch();
        if (!$t) return false;
        if (strtoupper((string)$t['status']) !== 'SUCCESS') return false;
        if (trim((string)($t['customer_reported_utr'] ?? '')) === '') return false;
        $phone = self::normalizeMobile((string)($t['phone'] ?? ''));
        if ($phone === '' || strlen($phone) < 12) return false;
        $msg = self::render(self::DLT_SUCCESS_MESSAGE, self::creditTxnContext($t));
        $key = 'txn:'.$txnId.':SUCCESS:utr';
        $q = $db->prepare("INSERT IGNORE INTO sms_outbox(event_key,phone,message,template_id,status,attempts,last_error,next_attempt_at,created_at) VALUES(?,?,?,?,'QUEUED',0,'',UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $q->execute([$key, $phone, $msg, self::DLT_SUCCESS_TEMPLATE_ID]);
        self::processOutbox($db, 10);
        return true;
    }

    public static function enqueueTxn(PDO $db, int $txnId, string $status): void {
        try {
            $s = self::settings($db);
            if (!(int)$s['enabled']) return;
            $st = $db->prepare('SELECT t.*,c.code customer_code,c.name customer_name,c.phone,c.account_number,b.bank_name FROM transactions t JOIN customers c ON c.id=t.customer_id JOIN bank_profiles b ON b.id=t.bank_id WHERE t.id=?');
            $st->execute([$txnId]);
            $t = $st->fetch();
            if (!$t) return;
            $phone = self::normalizeMobile((string)($t['phone'] ?? ''));
            if ($phone === '' || strlen($phone) < 12) return;
            $status = strtoupper($status);
            if ($status !== 'SUCCESS') return;
            $ctx = self::creditTxnContext($t);
            $msg = self::render(self::DLT_SUCCESS_MESSAGE, $ctx);
            $key = 'txn:'.$txnId.':'.$status;
            $q = $db->prepare("INSERT IGNORE INTO sms_outbox(event_key,phone,message,template_id,status,attempts,last_error,next_attempt_at,created_at) VALUES(?,?,?,?,'QUEUED',0,'',UTC_TIMESTAMP(),UTC_TIMESTAMP())");
            $q->execute([$key, $phone, $msg, self::DLT_SUCCESS_TEMPLATE_ID]);
            self::processOutbox($db, 10);
        } catch (Throwable $e) {
        }
    }
    public static function processOutbox(PDO $db, int $limit = 50): array {
        $s = self::settings($db);
        if (!(int)$s['enabled'] || empty($s['endpoint_url'])) return ['sent' => 0, 'failed' => 0, 'skipped' => true];
        $q = $db->prepare("SELECT * FROM sms_outbox WHERE status IN ('QUEUED','RETRY') AND next_attempt_at<=UTC_TIMESTAMP() ORDER BY id LIMIT ?");
        $q->bindValue(1, $limit, PDO::PARAM_INT);
        $q->execute();
        $rows = $q->fetchAll();
        $sent = $failed = 0;
        foreach ($rows as $r) {
            $attempt = (int)$r['attempts'] + 1;
            try {
                self::sendHttp($s, $r['phone'], $r['message'], $r['template_id'] ?? '');
                $u = $db->prepare("UPDATE sms_outbox SET status='SENT',attempts=?,last_error='',sent_at=UTC_TIMESTAMP() WHERE id=?");
                $u->execute([$attempt, $r['id']]);
                $sent++;
            } catch (Throwable $e) {
                $failed++;
                $status = $attempt >= 5 ? 'FAILED' : 'RETRY';
                $mins = min(60, 2 ** $attempt);
                $u = $db->prepare("UPDATE sms_outbox SET status=?,attempts=?,last_error=?,next_attempt_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? MINUTE) WHERE id=?");
                $u->execute([$status, $attempt, substr($e->getMessage(), 0, 2000), $mins, $r['id']]);
            }
        }
        return ['sent' => $sent, 'failed' => $failed, 'skipped' => false];
    }
}
