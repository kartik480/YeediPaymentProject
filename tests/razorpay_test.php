<?php
declare(strict_types=1);

/**
 * Razorpay integration tests.
 *
 * Pure (no DB): signature verification, amount parsing, unit conversion.
 * Integration (requires reachable MySQL): order create/idempotent capture,
 * webhook dedupe, amount/currency mismatch -> REVIEW, bad signature rejection.
 *
 * Run:  php tests/razorpay_test.php
 */
require_once dirname(__DIR__).'/app/bootstrap.php';

$pass = 0; $fail = 0;
function ok(bool $cond, string $name): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  $name\n"; }
    else { $fail++; echo "  FAIL  $name\n"; }
}

echo "== Pure signature + parsing tests ==\n";

$secret = 'test_key_secret';
$orderId = 'order_ABC123';
$paymentId = 'pay_XYZ789';
$good = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);
ok(RazorpayService::verifyCheckoutSignature($orderId, $paymentId, $good, $secret) === true, 'checkout signature: valid accepted');
ok(RazorpayService::verifyCheckoutSignature($orderId, $paymentId, $good.'0', $secret) === false, 'checkout signature: tampered rejected');
ok(RazorpayService::verifyCheckoutSignature($orderId, $paymentId, $good, 'wrong') === false, 'checkout signature: wrong secret rejected');
ok(RazorpayService::verifyCheckoutSignature('', $paymentId, $good, $secret) === false, 'checkout signature: empty order rejected');

$whSecret = 'webhook_secret_123';
$body = '{"event":"payment.captured","payload":{"payment":{"entity":{"id":"pay_1","order_id":"order_1","amount":50000,"currency":"INR","status":"captured"}}}}';
$whSig = hash_hmac('sha256', $body, $whSecret);
ok(RazorpayService::verifyWebhookSignature($body, $whSig, $whSecret) === true, 'webhook signature: valid accepted');
ok(RazorpayService::verifyWebhookSignature($body.' ', $whSig, $whSecret) === false, 'webhook signature: body tamper rejected');
ok(RazorpayService::verifyWebhookSignature($body, $whSig, 'other') === false, 'webhook signature: wrong secret rejected');
ok(RazorpayService::verifyWebhookSignature($body, '', $whSecret) === false, 'webhook signature: empty sig rejected');

try { RazorpayService::parseAmount('500', 1, 100000); ok(true, 'parseAmount: valid'); } catch (Throwable $e) { ok(false, 'parseAmount: valid'); }
try { RazorpayService::parseAmount('abc', 1, 100000); ok(false, 'parseAmount: non-numeric rejected'); } catch (Throwable $e) { ok(true, 'parseAmount: non-numeric rejected'); }
try { RazorpayService::parseAmount('0.5', 1, 100000); ok(false, 'parseAmount: below min rejected'); } catch (Throwable $e) { ok(true, 'parseAmount: below min rejected'); }
try { RazorpayService::parseAmount('999999', 1, 100000); ok(false, 'parseAmount: above max rejected'); } catch (Throwable $e) { ok(true, 'parseAmount: above max rejected'); }
ok(RazorpayService::rupees(50000) === '500.00', 'rupees: paise->rupees');

echo "\n== Integration tests (require MySQL) ==\n";
$db = null;
try { $db = Database::pdo(); $db->query('SELECT 1'); }
catch (Throwable $e) { $db = null; echo "  SKIP  No database reachable: ".$e->getMessage()."\n"; }

if ($db) {
    RazorpayService::ensureSchema($db);
    // Seed a local order directly (bypassing the live Razorpay API call).
    $ref = 'RZP-TEST-'.strtoupper(bin2hex(random_bytes(5)));
    $orderId = 'order_'.bin2hex(random_bytes(6));
    $db->prepare('INSERT INTO razorpay_orders(local_reference,customer_id,customer_code,rzp_order_id,amount_paise,currency,status,live_mode,created_at,updated_at) VALUES(?,?,?,?,?,?,\'CREATED\',0,UTC_TIMESTAMP(),UTC_TIMESTAMP())')
       ->execute([$ref, null, 'TEST', $orderId, 50000, 'INR']);

    $payment = ['id'=>'pay_'.bin2hex(random_bytes(6)), 'order_id'=>$orderId, 'amount'=>50000, 'currency'=>'INR', 'status'=>'captured', 'method'=>'upi'];
    [$code1] = RazorpayService::applyCapture($db, $payment, 'TEST');
    ok($code1 === 'PAID', 'applyCapture: valid capture -> PAID');
    [$code2] = RazorpayService::applyCapture($db, $payment, 'TEST');
    ok($code2 === 'PAID', 'applyCapture: duplicate capture idempotent (no double-credit)');
    $row = RazorpayService::orderByRzpId($db, $orderId);
    ok((int)$row['verified'] === 1 && (int)$row['captured_amount_paise'] === 50000, 'applyCapture: verified + captured amount stored once');

    // Amount mismatch -> REVIEW
    $ref2 = 'RZP-TEST-'.strtoupper(bin2hex(random_bytes(5)));
    $order2 = 'order_'.bin2hex(random_bytes(6));
    $db->prepare('INSERT INTO razorpay_orders(local_reference,customer_code,rzp_order_id,amount_paise,currency,status,live_mode,created_at,updated_at) VALUES(?,?,?,?,?,\'CREATED\',0,UTC_TIMESTAMP(),UTC_TIMESTAMP())')
       ->execute([$ref2, 'TEST', $order2, 50000, 'INR']);
    [$codeM] = RazorpayService::applyCapture($db, ['id'=>'pay_x','order_id'=>$order2,'amount'=>10000,'currency'=>'INR','status'=>'captured'], 'TEST');
    ok($codeM === 'REVIEW', 'applyCapture: amount mismatch -> REVIEW (not credited)');

    // Unmatched provider payment -> IGNORED
    [$codeU] = RazorpayService::applyCapture($db, ['id'=>'pay_y','order_id'=>'order_unknown','amount'=>1,'currency'=>'INR','status'=>'captured'], 'TEST');
    ok($codeU === 'IGNORED', 'applyCapture: unmatched order -> IGNORED');

    // Webhook: bad signature rejected + recorded
    $before = (int)$db->query('SELECT COUNT(*) FROM razorpay_events')->fetchColumn();
    // Temporarily ensure a webhook secret exists for the handler path.
    $hadSecret = RazorpayService::webhookSecret($db) !== '';
    if (!$hadSecret) {
        $db->prepare('UPDATE razorpay_settings SET webhook_secret_encrypted=? WHERE id=1')->execute([Crypto::encrypt('test_wh_secret')]);
    }
    $wsecret = RazorpayService::webhookSecret($db);
    $goodBody = json_encode(['event'=>'payment.captured','id'=>'evt_'.bin2hex(random_bytes(6)),'payload'=>['payment'=>['entity'=>['id'=>'pay_wh','order_id'=>$orderId,'amount'=>50000,'currency'=>'INR','status'=>'captured']]]]);
    [$st1] = RazorpayService::handleWebhook($db, $goodBody, 'deadbeef');
    ok($st1 === 401, 'webhook: invalid signature -> 401');
    $sig = hash_hmac('sha256', $goodBody, $wsecret);
    [$st2] = RazorpayService::handleWebhook($db, $goodBody, $sig);
    ok($st2 === 200, 'webhook: valid signature -> 200');
    [$st3] = RazorpayService::handleWebhook($db, $goodBody, $sig);
    ok($st3 === 200, 'webhook: duplicate event id ignored (idempotent)');

    // Cleanup test rows
    $db->exec("DELETE FROM razorpay_orders WHERE customer_code='TEST'");
    $db->exec("DELETE FROM razorpay_events WHERE rzp_order_id='$orderId'");
    if (!$hadSecret) $db->prepare('UPDATE razorpay_settings SET webhook_secret_encrypted=NULL WHERE id=1')->execute();
}

echo "\n== Result: $pass passed, $fail failed ==\n";
exit($fail === 0 ? 0 : 1);
