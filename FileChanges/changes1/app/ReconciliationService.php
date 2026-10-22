<?php
declare(strict_types=1);
final class ReconciliationService {
    private static function clean(mixed $v): string { return strtoupper(trim((string)$v)); }
    public static function normalizeStatus(array $bank,mixed $v): ?string { $v=self::clean($v);$succ=array_filter(array_map('trim',explode(',',strtoupper((string)$bank['success_values']))));$fail=array_filter(array_map('trim',explode(',',strtoupper((string)$bank['failed_values']))));if(in_array($v,$succ,true))return 'SUCCESS';if(in_array($v,$fail,true))return 'FAILED';if(in_array($v,['PENDING','PROCESSING','INITIATED'],true))return 'PENDING';if($v==='EXPIRED')return 'EXPIRED';return null; }
    public static function resolve(PDO $db,array $bank,string $reference='',string $rrn='',string $upiTxnId=''): array {
        if($reference!==''){$st=$db->prepare('SELECT * FROM transactions WHERE bank_id=? AND reference=? LIMIT 1');$st->execute([$bank['id'],$reference]);if($r=$st->fetch())return [$r,'reference'];}
        foreach([[$rrn,'rrn'],[$upiTxnId,'upi_txn_id']] as [$value,$label]){$value=self::clean($value);if($value==='')continue;$sql='SELECT * FROM transactions WHERE bank_id=? AND (rrn=? OR upi_txn_id=?) LIMIT 2';$st=$db->prepare($sql);$st->execute([$bank['id'],$value,$value]);$rows=$st->fetchAll();if(count($rows)===1)return [$rows[0],$label];if(count($rows)>1)return [null,'ambiguous_'.$label];}
        return [null,'not_found'];
    }
    public static function apply(PDO $db,array $bank,array $in,string $source='MANUAL'): array {
        [$t,$match]=self::resolve($db,$bank,trim((string)($in['reference']??'')),(string)($in['rrn']??''),(string)($in['upi_txn_id']??''));if(!$t)return [false,str_starts_with($match,'ambiguous_')?'Ambiguous transaction key; review manually.':'No matching YEEDIPAY transaction for this bank/reference/RRN/UPI transaction ID'];$status=self::normalizeStatus($bank,$in['status']??'');if(!$status)return [false,'Unrecognized status'];
        if($status==='SUCCESS' && strtoupper(trim($source))!=='BANK_SMS_AUTO')return [false,'SUCCESS requires the authenticated Android bank SMS auto-match flow'];
        $rrn=self::clean($in['rrn']??'');$upi=self::clean($in['upi_txn_id']??'');foreach([[$rrn,'rrn'],[$upi,'upi_txn_id']] as [$v,$f])if($v!==''){$st=$db->prepare("SELECT reference FROM transactions WHERE bank_id=? AND id<>? AND {$f}=? LIMIT 1");$st->execute([$bank['id'],$t['id'],$v]);if($other=$st->fetchColumn())return [false,strtoupper($f).' is already linked to '.$other];}
        $amount=null;if(isset($in['amount'])&&trim((string)$in['amount'])!==''){$amount=money_or_null($in['amount']);if($amount===null)return [false,'Invalid amount'];}$old=$t['status'];$raw=isset($in['raw'])?substr(json_encode($in['raw'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),0,12000):null;
        TransactionService::ensureSchema($db);
        $st=$db->prepare('UPDATE transactions SET status=?,confirmed_amount=COALESCE(?,confirmed_amount),upi_txn_id=COALESCE(NULLIF(?,\'\'),upi_txn_id),rrn=COALESCE(NULLIF(?,\'\'),rrn),payer_vpa=COALESCE(NULLIF(?,\'\'),payer_vpa),confirmation_source=?,raw_confirmation=COALESCE(?,raw_confirmation),updated_at=UTC_TIMESTAMP() WHERE id=?');$st->execute([$status,$amount,$upi,$rrn,trim((string)($in['payer_vpa']??'')),$source.':'.$match,$raw,$t['id']]);
        if($status==='SUCCESS'){
            LimitService::evaluateConfirmed($db,(int)$t['id']);
            TransactionService::assignAccountNumber($db,(int)$t['customer_id']);
            try { WalletService::onTransactionSuccess($db,(int)$t['id']); } catch (Throwable $e) {}
        }elseif(in_array($status,['FAILED','EXPIRED'],true)){
            $db->prepare("UPDATE transactions SET limit_breach=0,limit_note='',settlement_status='N/A' WHERE id=?")->execute([$t['id']]);
            try { WalletService::onTransactionClosed($db,(int)$t['id'], $status); } catch (Throwable $e) {}
        }
        if($status!==$old && in_array($status,['SUCCESS','FAILED','EXPIRED'],true)){
            try { SmsService::enqueueTxn($db,(int)$t['id'],$status); } catch (Throwable $e) {}
            try { EmailService::enqueueTxn($db,(int)$t['id'],$status); } catch (Throwable $e) {}
            if($status==='SUCCESS') PaymentConfirmService::activateService($db,(int)$t['customer_id']);
        }
        return [true,'OK'];
    }
}
