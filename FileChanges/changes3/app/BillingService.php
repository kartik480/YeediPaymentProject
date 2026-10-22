<?php
declare(strict_types=1);

final class BillingService {
    private static function feeFor(float $amount): array {
        $mode=strtoupper(trim((string)Env::get('PLATFORM_FEE_MODE','PERCENT')));
        $percent=max(0.0,(float)Env::get('PLATFORM_FEE_PERCENT','1.00'));
        $fixed=max(0.0,(float)Env::get('PLATFORM_FEE_FIXED','0.00'));
        $gstPct=max(0.0,(float)Env::get('GST_PERCENT','18.00'));
        $fee=$mode==='FIXED'?$fixed:($amount*$percent/100.0);
        $gst=$fee*$gstPct/100.0;
        return [round($fee,2),round($gst,2),round($fee+$gst,2)];
    }
    public static function customerFeeEntries(PDO $db,int $customerId): array {
        $st=$db->prepare("SELECT t.id,t.reference,t.created_at,t.confirmed_amount,t.requested_amount,b.bank_name FROM transactions t JOIN bank_profiles b ON b.id=t.bank_id WHERE t.customer_id=? AND t.status='SUCCESS' ORDER BY t.id DESC LIMIT 1000");$st->execute([$customerId]);$out=[];
        foreach($st->fetchAll() as $r){$amount=(float)($r['confirmed_amount']??$r['requested_amount']??0);[$fee,$gst,$total]=self::feeFor($amount);$r['amount']=$amount;$r['platform_fee']=$fee;$r['gst']=$gst;$r['total_fee']=$total;$out[]=$r;}return $out;
    }
    public static function customerPlatformSummary(PDO $db,int $customerId): array {
        $entries=self::customerFeeEntries($db,$customerId);$collection=$fee=$gst=$total=0.0;foreach($entries as $r){$collection+=(float)$r['amount'];$fee+=(float)$r['platform_fee'];$gst+=(float)$r['gst'];$total+=(float)$r['total_fee'];}
        return ['transactions'=>count($entries),'collection'=>round($collection,2),'platform_fee'=>round($fee,2),'gst'=>round($gst,2),'total_fee'=>round($total,2),'mode'=>(string)Env::get('PLATFORM_FEE_MODE','PERCENT'),'rate'=>(float)Env::get('PLATFORM_FEE_PERCENT','1.00'),'gst_rate'=>(float)Env::get('GST_PERCENT','18.00')];
    }
    public static function downloadCustomerFeesCsv(array $c,array $entries): never { header('Content-Type:text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9_-]/','',(string)$c['code']).'-billing.csv"');$o=fopen('php://output','wb');fwrite($o,"\xEF\xBB\xBF");fputcsv($o,['date','reference','bank','collection','platform_fee','gst','total_fee']);foreach($entries as $r)fputcsv($o,[$r['created_at'],$r['reference'],$r['bank_name'],number_format((float)$r['amount'],2,'.',''),number_format((float)$r['platform_fee'],2,'.',''),number_format((float)$r['gst'],2,'.',''),number_format((float)$r['total_fee'],2,'.','')]);exit; }
    public static function downloadCustomerFeesPdf(array $c,array $summary,array $entries): never { $pdf=new PdfDocument();$pdf->text(40,50,'YEEDIPAY - PLATFORM FEES & GST',18,true);$pdf->text(40,76,(string)$c['name'].' / '.(string)$c['code'],11,true);$pdf->text(40,98,'Collections: INR '.number_format((float)$summary['collection'],2),10);$pdf->text(230,98,'Fees: INR '.number_format((float)$summary['platform_fee'],2),10);$pdf->text(390,98,'GST: INR '.number_format((float)$summary['gst'],2),10);$pdf->line(40,112,555);$y=135;$pdf->text(40,$y,'Date',9,true);$pdf->text(130,$y,'Reference',9,true);$pdf->textRight(430,$y,'Collection',9,true);$pdf->textRight(495,$y,'Fee',9,true);$pdf->textRight(555,$y,'GST',9,true);$y+=16;foreach(array_slice($entries,0,28) as $r){$pdf->text(40,$y,substr((string)$r['created_at'],0,10),8);$pdf->text(130,$y,$pdf->fit((string)$r['reference'],190,8),8);$pdf->textRight(430,$y,number_format((float)$r['amount'],2),8);$pdf->textRight(495,$y,number_format((float)$r['platform_fee'],2),8);$pdf->textRight(555,$y,number_format((float)$r['gst'],2),8);$y+=20;}if(count($entries)>28)$pdf->text(40,790,'PDF shows latest 28 rows. Download CSV for the complete list.',8);$pdf->download((string)$c['code'].'-billing.pdf'); }
}
