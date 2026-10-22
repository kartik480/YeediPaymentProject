<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require_once dirname(__DIR__).'/app/bootstrap.php';
if($argc<3){fwrite(STDERR,"Usage: php tools/create_merchant.php CUST0004 \"Merchant Name\" [webhook_url] [return_url] [LIVE|SANDBOX]\n");exit(2);}
try{
    $db=Database::pdo();
    $out=MerchantApiService::provision($db,(string)$argv[1],(string)$argv[2],(string)($argv[3]??''),(string)($argv[4]??''),(string)($argv[5]??'LIVE'));
    echo "MERCHANT CREATED\n";
    foreach($out as $k=>$v)echo strtoupper($k)."=".$v."\n";
    echo "\nSave API_SECRET and WEBHOOK_SECRET now. They are not printed again by the portal.\n";
}catch(Throwable $e){fwrite(STDERR,"ERROR: ".$e->getMessage()."\n");exit(1);}
