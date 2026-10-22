<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
require_once dirname(__DIR__).'/app/bootstrap.php';
$fail=[];$warn=[];$pass=[];
$check=function(bool $ok,string $label,string $fix='')use(&$fail,&$pass){if($ok)$pass[]=$label;else$fail[]=$label.($fix!==''?' — '.$fix:'');};
$check(PHP_VERSION_ID>=80200,'PHP 8.2+','Enable PHP 8.2 or newer');
$check(extension_loaded('pdo_mysql'),'pdo_mysql extension','Enable pdo_mysql');
$check(extension_loaded('openssl'),'OpenSSL extension','Enable OpenSSL');
if(extension_loaded('curl'))$pass[]='cURL extension';else$warn[]='cURL is not enabled; webhook fallback will be used.';
$check(is_dir(YEEDIPAY_ROOT.'/storage')&&is_writable(YEEDIPAY_ROOT.'/storage'),'storage writable','Set storage permissions for PHP');
$dbUser=trim((string)Env::get('DB_USER',''));$dbPass=(string)Env::get('DB_PASS','');$appKey=trim((string)Env::get('APP_KEY',''));
$check($dbUser!==''&&!str_contains(strtoupper($dbUser),'CHANGE_ME'),'DB_USER configured','Edit .env');
$check($dbPass!==''&&!str_contains(strtoupper($dbPass),'CHANGE_ME'),'DB_PASS configured','Edit .env');
$check(strlen($appKey)>=32&&!str_contains(strtoupper($appKey),'CHANGE_'),'APP_KEY strong/configured','Use a random 32+ character secret');
$payUrl=trim((string)Env::get('PAY_PUBLIC_URL',''));
$check(str_starts_with(strtolower($payUrl),'https://'),'PAY_PUBLIC_URL uses HTTPS','Set the real HTTPS payment domain');
$ttl=BankSmsService::qrTtlSeconds();$check($ttl>=60&&$ttl<=900,"Payment QR TTL {$ttl}s");
try{
    $db=Database::pdo();$pass[]='Database connection';MerchantApiService::ensureSchema($db);
    $banks=(int)$db->query('SELECT COUNT(*) FROM bank_profiles WHERE enabled=1')->fetchColumn();
    if($banks<1)$fail[]='No enabled backend QR/bank profile';else$pass[]="Enabled backend QR profiles: {$banks}";
    if($banks>=2){
        $cfg=$db->query('SELECT rotation_enabled FROM system_config WHERE id=1')->fetch();
        $wrongSlots=(int)$db->query('SELECT COUNT(*) FROM bank_profiles WHERE enabled=1 AND rotation_minutes<>30')->fetchColumn();
        if(!$cfg||!(int)$cfg['rotation_enabled'])$warn[]='2+ QR profiles exist but AUTO rotation is not enabled.';
        elseif($wrongSlots>0)$warn[]='AUTO rotation is enabled, but one or more QR slots are not 30 minutes.';
        else $pass[]='30-minute AUTO rotation enabled';
    }
}catch(Throwable $e){$fail[]='Database/schema check failed — verify DB credentials and permissions';}
foreach($pass as $message)echo "[PASS] {$message}\n";
foreach($warn as $message)echo "[WARN] {$message}\n";
foreach($fail as $message)echo "[FAIL] {$message}\n";
echo "\nRESULT: ".($fail?'NOT READY':'READY')."\n";exit($fail?1:0);
