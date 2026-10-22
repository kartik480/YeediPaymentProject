<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';

$fail=[]; $warn=[]; $pass=[];
$check=function(bool $ok,string $label,string $fix='')use(&$fail,&$pass){if($ok)$pass[]=$label;else$fail[]=$label.($fix!==''?' — '.$fix:'');};
$check(PHP_VERSION_ID>=80200,'PHP 8.2+','Enable PHP 8.2 or newer in BigRock');
$check(extension_loaded('pdo_mysql'),'pdo_mysql extension','Enable pdo_mysql');
$check(extension_loaded('openssl'),'OpenSSL extension','Enable OpenSSL; YEEDIPAY will not store new secrets without it');
if(!extension_loaded('curl'))$warn[]='cURL extension is not enabled; webhook fallback can work, but cURL is recommended.'; else $pass[]='cURL extension';
$check(is_dir(YEEDIPAY_ROOT.'/storage') && is_writable(YEEDIPAY_ROOT.'/storage'),'storage writable','Set storage writable for PHP (commonly 755/775 depending on hosting)');

$dbUser=trim((string)Env::get('DB_USER','')); $dbPass=(string)Env::get('DB_PASS',''); $appKey=trim((string)Env::get('APP_KEY',''));
$check($dbUser!=='' && stripos($dbUser,'CHANGE_ME')===false,'DB_USER configured','Edit .env');
$check($dbPass!=='' && stripos($dbPass,'CHANGE_ME')===false,'DB_PASS configured','Edit .env');
$check(strlen($appKey)>=32 && stripos($appKey,'CHANGE_')===false && $appKey!=='yeedipay-change-this-key','APP_KEY strong/configured','Use a random 32+ character secret in .env');
$payUrl=trim((string)Env::get('PAY_PUBLIC_URL',''));
$check(str_starts_with(strtolower($payUrl),'https://'),'PAY_PUBLIC_URL uses HTTPS','Set your real HTTPS payment domain');
$ttl=BankSmsService::qrTtlSeconds();
$check($ttl>=60 && $ttl<=900,"Payment QR TTL {$ttl}s");

try{
    $db=Database::pdo();
    $pass[]='Database connection';
    $banks=(int)$db->query('SELECT COUNT(*) FROM bank_profiles WHERE enabled=1')->fetchColumn();
    if($banks<1)$fail[]='No enabled backend QR/bank profile — add at least one real QR before live payments'; else $pass[]="Enabled backend QR profiles: {$banks}";
    if($banks>=2){
        $cfg=$db->query('SELECT rotation_enabled FROM system_config WHERE id=1')->fetch();
        if(!$cfg || !(int)$cfg['rotation_enabled'])$warn[]='2+ QR profiles exist but AUTO rotation is not enabled.'; else $pass[]='30-minute AUTO rotation enabled';
    }
}catch(Throwable $e){$fail[]='Database connection failed — check DB_HOST/DB_NAME/DB_USER/DB_PASS';}

foreach($pass as $m)echo "[PASS] {$m}\n";
foreach($warn as $m)echo "[WARN] {$m}\n";
foreach($fail as $m)echo "[FAIL] {$m}\n";
echo "\nRESULT: ".($fail?'NOT READY':'READY')."\n";
exit($fail?1:0);
