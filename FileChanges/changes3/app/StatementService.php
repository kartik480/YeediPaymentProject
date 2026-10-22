<?php
declare(strict_types=1);

final class StatementService {
    public static function filtersFromGet(): array { $period=strtolower(trim((string)($_GET['period']??'month')));if(!in_array($period,['day','week','month','custom'],true))$period='month';return ['period'=>$period,'status'=>strtoupper(trim((string)($_GET['status']??''))),'bank_id'=>(($_GET['bank_id']??'')!==''?(int)$_GET['bank_id']:null),'start'=>trim((string)($_GET['start']??'')),'end'=>trim((string)($_GET['end']??''))]; }
    public static function rows(PDO $db,int $customerId,array $f): array { return TransactionService::query($db,$customerId,(string)$f['period'],(string)$f['status'],$f['bank_id']??null,(string)$f['start'],(string)$f['end'],5000); }
    public static function ledgerRows(PDO $db,int $customerId,string $start='',string $end=''): array {
        $period=($start!==''&&$end!=='')?'custom':'custom';if($start===''||$end===''){ $start='2000-01-01';$end=date('Y-m-d'); }
        $rows=TransactionService::query($db,$customerId,$period,'',null,$start,$end,5000);$rows=array_reverse($rows);$balance=0.0;$out=[];
        foreach($rows as $r){$credit=0.0;$debit=0.0;if(strtoupper((string)$r['status'])==='SUCCESS'){$credit=(float)($r['confirmed_amount']??$r['requested_amount']??0);if(strtoupper((string)($r['settlement_status']??''))==='SETTLED')$debit=$credit;}$balance+=$credit-$debit;$r['credit']=$credit;$r['debit']=$debit;$r['balance']=round($balance,2);$out[]=$r;}return array_reverse($out);
    }
    public static function download(array $c,array $rows,array $f): never { $pdf=new PdfDocument();$pdf->text(40,50,'YEEDIPAY - ACCOUNT STATEMENT',18,true);$pdf->text(40,76,(string)$c['name'].' / '.(string)$c['code'],11,true);$pdf->text(40,96,'Generated: '.date('d-m-Y H:i'),9);$pdf->line(40,112,555);$y=135;$pdf->text(40,$y,'Date',9,true);$pdf->text(120,$y,'Reference',9,true);$pdf->text(330,$y,'Status',9,true);$pdf->textRight(555,$y,'Amount',9,true);$y+=16;foreach(array_slice($rows,0,29) as $r){$pdf->text(40,$y,substr((string)$r['created_at'],0,10),8);$pdf->text(120,$y,$pdf->fit((string)$r['reference'],195,8),8);$pdf->text(330,$y,(string)$r['status'],8);$pdf->textRight(555,$y,number_format((float)($r['confirmed_amount']??$r['requested_amount']??0),2),8);$y+=20;}if(count($rows)>29)$pdf->text(40,790,'PDF shows latest 29 rows. Download CSV for the complete list.',8);$pdf->download((string)$c['code'].'-statement.pdf'); }
}
