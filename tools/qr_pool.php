<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
require_once dirname(__DIR__).'/app/bootstrap.php';
$db=Database::pdo();
$command=strtolower((string)($argv[1]??'list'));
if($command==='list'){
    foreach(BankService::all($db) as $bank)printf("%s\t%s\tenabled=%d\torder=%d\tminutes=%d\n",$bank['code'],$bank['bank_name'],$bank['enabled'],$bank['rotation_order'],$bank['rotation_minutes']);
    exit;
}
if($command==='enable-auto'){BankService::applyRouting($db,'AUTO',0);echo "AUTO rotation enabled: ".BankService::DEFAULT_ROTATION_MINUTES." minutes per enabled QR\n";exit;}
if($command==='name'){
    $code=strtoupper(trim((string)($argv[2]??'')));$name=trim((string)($argv[3]??''));
    if($code===''||$name===''){fwrite(STDERR,"Usage: php tools/qr_pool.php name QR_CODE \"Display Name\"\n");exit(2);}
    $st=$db->prepare('UPDATE bank_profiles SET bank_name=? WHERE code=?');$st->execute([$name,$code]);
    echo $st->rowCount()?"Updated {$code}\n":"QR code not found or name unchanged\n";exit($st->rowCount()?0:3);
}
if(in_array($command,['enable','disable'],true)){
    $code=strtoupper(trim((string)($argv[2]??'')));if($code===''){fwrite(STDERR,"Usage: php tools/qr_pool.php {$command} QR_CODE\n");exit(2);}
    $st=$db->prepare('UPDATE bank_profiles SET enabled=?,rotation_minutes=? WHERE code=?');$st->execute([$command==='enable'?1:0,BankService::DEFAULT_ROTATION_MINUTES,$code]);
    echo $st->rowCount()?"Updated {$code}\n":"QR code not found\n";exit($st->rowCount()?0:3);
}
if($command==='add'){
    $code=trim((string)($argv[2]??''));$name=trim((string)($argv[3]??''));$uri=trim((string)($argv[4]??''));$order=max(1,(int)($argv[5]??1));
    if($code===''||$name===''||$uri===''){fwrite(STDERR,"Usage: php tools/qr_pool.php add QR_CODE \"Bank/QR Name\" \"upi://pay?...\" 1\n");exit(2);}
    $id=BankService::add($db,['code'=>$code,'bank_name'=>$name,'upi_uri'=>$uri,'rotation_order'=>$order,'rotation_minutes'=>BankService::DEFAULT_ROTATION_MINUTES,'enabled'=>1]);
    echo "Added QR profile id={$id} code=".strtoupper($code)."\n";exit;
}
fwrite(STDERR,"Commands: list | add | name | enable | disable | enable-auto\n");exit(2);
