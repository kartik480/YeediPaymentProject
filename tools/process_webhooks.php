<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require_once dirname(__DIR__).'/app/bootstrap.php';
try{
    $db=Database::pdo();
    $synced=MerchantApiService::sweepFinalOrders($db,200);
    $delivered=WebhookService::deliverPending($db,20);
    echo "Synced {$synced} final merchant order(s); delivered {$delivered} webhook(s).\n";
}catch(Throwable $e){fwrite(STDERR,"ERROR: ".$e->getMessage()."\n");exit(1);}
