<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
require_once dirname(__DIR__).'/app/bootstrap.php';
$db=Database::pdo();
BankSmsService::ensureSchema($db);
echo "DEVICES\n";
foreach($db->query("SELECT id,name,device_id,bank_code,account_last4,active,last_seen_at,updated_at FROM bank_sms_devices ORDER BY id DESC") as $row){
    printf("#%d %s bank=%s account=*%s active=%d last_seen=%s updated=%s\n",$row['id'],$row['name'],$row['bank_code'],$row['account_last4'],$row['active'],$row['last_seen_at']??'-',$row['updated_at']);
}
echo "\nRECENT BANK SMS EVENTS\n";
foreach($db->query("SELECT id,bank_code,account_last4,amount,utr,sms_time,match_status,transaction_id,candidate_refs,created_at FROM portal_bank_sms_events ORDER BY id DESC LIMIT 15") as $row){
    printf("#%d bank=%s account=*%s amount=%s utr=%s match=%s txn=%s candidates=%s sms=%s\n",$row['id'],$row['bank_code'],$row['account_last4'],$row['amount'],$row['utr'],$row['match_status'],$row['transaction_id']??'-',$row['candidate_refs']??'-',$row['sms_time']);
}
echo "\nRECENT TRANSACTIONS\n";
foreach($db->query("SELECT t.id,t.reference,t.status,t.requested_amount,t.customer_paid_ack_at,t.sms_match,t.rrn,t.created_at,b.code bank_code,c.code customer_code FROM transactions t JOIN bank_profiles b ON b.id=t.bank_id JOIN customers c ON c.id=t.customer_id ORDER BY t.id DESC LIMIT 15") as $row){
    printf("#%d %s customer=%s bank=%s amount=%s status=%s ack=%s sms_match=%s rrn=%s created=%s\n",$row['id'],$row['reference'],$row['customer_code'],$row['bank_code'],$row['requested_amount'],$row['status'],$row['customer_paid_ack_at']??'-',$row['sms_match']??'-',$row['rrn']??'-',$row['created_at']);
}
