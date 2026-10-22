<?php
declare(strict_types=1);
require_once __DIR__.'/../app/BankService.php';

function fail_test(string $m): never { fwrite(STDERR,"FAIL: $m\n"); exit(1); }
function ok(bool $v,string $m): void { if(!$v) fail_test($m); }

ok(method_exists(BankService::class,'selectRotatingBank'),'BankService::selectRotatingBank() missing');

$banks = [
    ['id'=>1,'code'=>'QR1','rotation_minutes'=>30],
    ['id'=>2,'code'=>'QR2','rotation_minutes'=>30],
    ['id'=>3,'code'=>'QR3','rotation_minutes'=>30],
];
$tz = new DateTimeZone('UTC');
$start = new DateTimeImmutable('2026-10-10 00:00:00',$tz);
$cases = [
    ['2026-10-10 00:00:00','QR1'],
    ['2026-10-10 00:29:59','QR1'],
    ['2026-10-10 00:30:00','QR2'],
    ['2026-10-10 00:59:59','QR2'],
    ['2026-10-10 01:00:00','QR3'],
    ['2026-10-10 01:29:59','QR3'],
    ['2026-10-10 01:30:00','QR1'],
];
foreach($cases as [$when,$expected]) {
    $picked = BankService::selectRotatingBank($banks,$start,new DateTimeImmutable($when,$tz));
    ok(($picked['code']??'')===$expected,"$when expected $expected got ".($picked['code']??'NULL'));
}

$bankSrc = file_get_contents(__DIR__.'/../app/BankService.php');
ok(str_contains($bankSrc,'DEFAULT_ROTATION_MINUTES = 30'),'30-minute rotation constant missing');
ok(!str_contains($bankSrc,'ensureHourlyRotation'),'hourly rotation code still present');

$schema = file_get_contents(__DIR__.'/../database/schema.sql');
ok((bool)preg_match('/rotation_minutes INT NOT NULL DEFAULT 30/', $schema),'schema rotation default is not 30');

$merchantQr = file_get_contents(__DIR__.'/../customer/views/merchant_qr.php');
ok(!str_contains($merchantQr,'Active Bank'),'customer QR screen still exposes rotating Active Bank');
ok(!str_contains($merchantQr,'UPI ID'),'customer QR screen still exposes rotating UPI ID');
ok(str_contains($merchantQr, 'pay_url(\'/p/\'.$c[\'code\'])'),'customer QR does not use stable public payment link');

$txnSrc = file_get_contents(__DIR__.'/../app/TransactionService.php');
ok(str_contains($txnSrc,'INSERT INTO transactions(customer_id,bank_id,reference'),'transaction does not persist the selected backend bank_id');
ok(str_contains($txnSrc,'$st->execute([$c[\'id\'],$bank[\'id\']'),'transaction insert does not lock selected bank id');

ok(is_file(__DIR__.'/../database/qr_rotation_30min_patch.sql'),'rotation SQL patch missing');
ok(is_file(__DIR__.'/../docs/QR_ROTATION_30MIN_PATCH_TELUGU.md'),'Telugu patch guide missing');

echo "PASS qr_rotation_patch_test\n";
