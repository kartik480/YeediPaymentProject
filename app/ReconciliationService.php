<?php
declare(strict_types=1);
final class ReconciliationService {
    private static function clean(mixed $v): string { return strtoupper(trim((string)$v)); }
    public static function normalizeStatus(array $bank,mixed $v): ?string { $v=self::clean($v);$succ=array_filter(array_map('trim',explode(',',strtoupper((string)$bank['success_values']))));$fail=array_filter(array_map('trim',explode(',',strtoupper((string)$bank['failed_values']))));if(in_array($v,$succ,true))return 'SUCCESS';if(in_array($v,$fail,true))return 'FAILED';if(in_array($v,['PENDING','PROCESSING','INITIATED'],true))return 'PENDING';if($v==='EXPIRED')return 'EXPIRED';return null; }
    public static function resolve(PDO $db,array $bank,string $reference='',string $customerUtr='',string $rrn='',string $upiTxnId=''): array {
        if($reference!==''){$st=$db->prepare('SELECT * FROM transactions WHERE bank_id=? AND reference=? LIMIT 1');$st->execute([$bank['id'],$reference]);if($r=$st->fetch())return [$r,'reference'];}
        foreach([[$customerUtr,'customer_utr'],[$rrn,'rrn'],[$upiTxnId,'upi_txn_id']] as [$value,$label]){$value=self::clean($value);if($value==='')continue;$sql='SELECT * FROM transactions WHERE bank_id=? AND (customer_reported_utr=? OR rrn=? OR upi_txn_id=?) LIMIT 2';$st=$db->prepare($sql);$st->execute([$bank['id'],$value,$value,$value]);$rows=$st->fetchAll();if(count($rows)===1)return [$rows[0],$label];if(count($rows)>1)return [null,'ambiguous_'.$label];}
        return [null,'not_found'];
    }
    public static function apply(PDO $db,array $bank,array $in,string $source='MANUAL'): array {
        [$t,$match]=self::resolve($db,$bank,trim((string)($in['reference']??'')),(string)($in['customer_utr']??''),(string)($in['rrn']??''),(string)($in['upi_txn_id']??''));if(!$t)return [false,str_starts_with($match,'ambiguous_')?'Ambiguous transaction key; review manually.':'No matching YEEDIPAY transaction for this bank/reference/UTR/RRN/UPI transaction ID'];$status=self::normalizeStatus($bank,$in['status']??'');if(!$status)return [false,'Unrecognized status'];
        $sourceNorm=strtoupper(trim($source));
        $adminManual=in_array($sourceNorm,['MANUAL','CSV','WEBHOOK'],true);
        if($status==='SUCCESS'&&$sourceNorm!=='BANK_SMS_AUTO'&&!$adminManual)return [false,'SUCCESS requires the authenticated Android bank SMS auto-match flow'];
        $oldStatus=strtoupper((string)($t['status']??''));
        $priorSource=strtoupper((string)($t['confirmation_source']??''));
        if($status==='SUCCESS'&&(in_array($oldStatus,['FAILED','EXPIRED'],true)||str_starts_with($priorSource,'CUSTOMER_CANCEL')))return [false,'A closed payment cannot be marked paid'];
        if($status==='SUCCESS'&&$oldStatus==='PENDING'&&BankSmsService::qrSecondsLeft($t)<=0&&!$adminManual)return [false,'Payment window has expired'];
        $rrn=self::clean($in['rrn']??'');$upi=self::clean($in['upi_txn_id']??'');if($rrn!==''&&$upi==='')$upi=$rrn;if($upi!==''&&$rrn==='')$rrn=$upi;foreach([[$rrn,'rrn'],[$upi,'upi_txn_id']] as [$v,$f])if($v!==''){$st=$db->prepare("SELECT reference FROM transactions WHERE bank_id=? AND id<>? AND {$f}=? LIMIT 1");$st->execute([$bank['id'],$t['id'],$v]);if($other=$st->fetchColumn())return [false,strtoupper($f).' is already linked to '.$other];}
        $amount=null;if(isset($in['amount'])&&trim((string)$in['amount'])!==''){$amount=money_or_null($in['amount']);if($amount===null)return [false,'Invalid amount'];}$old=$t['status'];$raw=isset($in['raw'])?substr(json_encode($in['raw'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),0,12000):null;
        TransactionService::ensureSchema($db);
        $guard=$status==='SUCCESS'?" AND status='PENDING'":'';
        $st=$db->prepare('UPDATE transactions SET status=?,confirmed_amount=COALESCE(?,confirmed_amount),upi_txn_id=COALESCE(NULLIF(?,\'\'),upi_txn_id),rrn=COALESCE(NULLIF(?,\'\'),rrn),payer_vpa=COALESCE(NULLIF(?,\'\'),payer_vpa),confirmation_source=?,raw_confirmation=COALESCE(?,raw_confirmation),updated_at=UTC_TIMESTAMP() WHERE id=?'.$guard);$st->execute([$status,$amount,$upi,$rrn,trim((string)($in['payer_vpa']??'')),$source.':'.$match,$raw,$t['id']]);
        if($status==='SUCCESS'&&$st->rowCount()<1)return [false,'A closed payment cannot be marked paid'];
        if($status==='SUCCESS'){
            try { LimitService::evaluateConfirmed($db,(int)$t['id']); } catch (Throwable $e) {}
            try { TransactionService::assignAccountNumber($db,(int)$t['customer_id']); } catch (Throwable $e) {}
            try { WalletService::onTransactionSuccess($db,(int)$t['id']); } catch (Throwable $e) {}
        }elseif(in_array($status,['FAILED','EXPIRED'],true)){
            $db->prepare("UPDATE transactions SET limit_breach=0,limit_note='',settlement_status='N/A' WHERE id=?")->execute([$t['id']]);
            try { WalletService::onTransactionClosed($db,(int)$t['id'], $status); } catch (Throwable $e) {}
        }
        if($status!==$old && in_array($status,['SUCCESS','FAILED','EXPIRED'],true)){
            if (!($status==='SUCCESS' && str_starts_with($source, 'CUSTOMER_UTR'))) {
                try { SmsService::enqueueTxn($db,(int)$t['id'],$status); } catch (Throwable $e) {}
            }
            try { EmailService::enqueueTxn($db,(int)$t['id'],$status); } catch (Throwable $e) {}
            if($status==='SUCCESS') { try { PaymentConfirmService::activateService($db,(int)$t['customer_id']); } catch (Throwable $e) {} }
            try{$fresh=TransactionService::txnByRef($db,(string)$t['reference'])?:$t;MerchantApiService::onTransactionStatusChanged($db,$fresh,$status);}catch(Throwable $e){error_log('Merchant webhook queue failed: '.$e->getMessage());}
        }
        try { TransactionService::syncIdentityFields($db, (int)$t['id']); } catch (Throwable $e) {}
        return [true,'OK'];
    }
    public static function importCsv(PDO $db,array $bank,string $path): array {
        $fh=fopen($path,'rb');if(!$fh)throw new RuntimeException('Cannot read CSV');$header=fgetcsv($fh);if(!$header)throw new RuntimeException('CSV is empty');$header=array_map(fn($x)=>strtolower(trim((string)$x)),$header);$ok=$bad=0;$errors=[];$line=1;while(($row=fgetcsv($fh))!==false){$line++;$data=[];foreach($header as $i=>$k)$data[$k]=$row[$i]??'';$status=$data['status']??'';$match=($data['reference']??'')?:($data['customer_utr']??'')?:($data['utr']??'')?:($data['rrn']??'')?:($data['upi_txn_id']??'');if($status===''||$match===''){$bad++;$errors[]="Line {$line}: status + match key required";continue;}[$r,$msg]=self::apply($db,$bank,['reference'=>$data['reference']??'','customer_utr'=>($data['customer_utr']??'')?:($data['utr']??''),'rrn'=>$data['rrn']??'','upi_txn_id'=>$data['upi_txn_id']??'','status'=>$status,'amount'=>$data['amount']??'','payer_vpa'=>$data['payer_vpa']??'','raw'=>$data],'CSV');if($r)$ok++;else{$bad++;$errors[]="Line {$line}: {$msg}";}}
        fclose($fh);return ['ok'=>$ok,'bad'=>$bad,'errors'=>array_slice($errors,0,20)];
    }
}
