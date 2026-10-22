<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
$db = Database::pdo();
$cmd = strtolower((string)($argv[1] ?? 'list'));

if ($cmd === 'list') {
    foreach (BankService::all($db) as $b) {
        printf("%s\t%s\tenabled=%d\torder=%d\tminutes=%d\n", $b['code'],$b['bank_name'],$b['enabled'],$b['rotation_order'],$b['rotation_minutes']);
    }
    exit(0);
}
if ($cmd === 'enable-auto') {
    BankService::applyRouting($db,'AUTO',0);
    echo "AUTO rotation enabled: 30 minutes per enabled QR\n";
    exit(0);
}
if (in_array($cmd,['enable','disable'],true)) {
    $code = strtoupper(trim((string)($argv[2] ?? '')));
    if ($code==='') { fwrite(STDERR,"Usage: php tools/qr_pool.php $cmd QR_CODE\n"); exit(2); }
    $st=$db->prepare('UPDATE bank_profiles SET enabled=?,rotation_minutes=? WHERE code=?');
    $st->execute([$cmd==='enable'?1:0,BankService::DEFAULT_ROTATION_MINUTES,$code]);
    echo $st->rowCount()?"Updated $code\n":"QR code not found\n";
    exit($st->rowCount()?0:3);
}
if ($cmd === 'add') {
    $code = trim((string)($argv[2] ?? ''));
    $name = trim((string)($argv[3] ?? ''));
    $uri = trim((string)($argv[4] ?? ''));
    $order = max(1,(int)($argv[5] ?? 1));
    if ($code===''||$name===''||$uri==='') {
        fwrite(STDERR,"Usage: php tools/qr_pool.php add QR_CODE \"Bank/QR Name\" \"upi://pay?...\" 1\n"); exit(2);
    }
    $id=BankService::add($db,['code'=>$code,'bank_name'=>$name,'upi_uri'=>$uri,'rotation_order'=>$order,'rotation_minutes'=>30,'enabled'=>1]);
    echo "Added QR profile id=$id code=".strtoupper($code)."\n";
    exit(0);
}
fwrite(STDERR,"Commands: list | add | enable | disable | enable-auto\n");
exit(2);
