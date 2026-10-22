<?php
declare(strict_types=1);
final class LimitService {
    private static function pendingCount(PDO $db,int $cid): int { $st=$db->prepare("SELECT COUNT(*) FROM transactions WHERE customer_id=? AND status='PENDING'");$st->execute([$cid]);return (int)$st->fetchColumn(); }
    public static function hasMoneyLimits(array $c): bool { foreach(['per_txn_limit','daily_limit','weekly_limit','monthly_limit'] as $k) if(!empty($c[$k]) && (float)$c[$k]>0)return true; return false; }
    public static function periodExposure(PDO $db,int $cid,string $period): array {
        [$s,$e]=local_period_bounds($period); if(!$s)return [0.0,0];
        $sql="SELECT COALESCE(SUM(CASE WHEN status='SUCCESS' THEN COALESCE(confirmed_amount,requested_amount,0) ELSE 0 END),0) total, SUM(CASE WHEN status IN ('PENDING','SUCCESS') THEN 1 ELSE 0 END) cnt FROM transactions WHERE customer_id=? AND created_at>=? AND created_at<? AND status IN ('PENDING','SUCCESS')";
        $st=$db->prepare($sql);$st->execute([$cid,$s,$e]);$r=$st->fetch();return [(float)$r['total'],(int)$r['cnt']];
    }
    public static function check(PDO $db,array $c,?float $amount): array {
        if(!(int)$c['active'])return [false,'Customer payment link is disabled'];
        // Temporarily disabled: pending-attempt / reconciliation block
        // $max=(int)($c['max_pending_attempts']??5);if($max>0 && self::pendingCount($db,(int)$c['id']) >= $max)return [false,"Maximum pending attempts reached ({$max}). Please wait for reconciliation."];
        if($amount===null)return [false,'Select a listed payment amount.'];
        if($amount!==null){if($amount<=0)return [false,'Amount must be greater than zero.'];
            // Temporarily disabled: min / per-transaction / daily / weekly / monthly collection limits
            // if(!empty($c['min_txn_limit']) && $amount<(float)$c['min_txn_limit'])return [false,'Minimum transaction is Rs.'.amount_fmt($c['min_txn_limit']).'.'];
            // if(!empty($c['per_txn_limit']) && $amount>(float)$c['per_txn_limit'])return [false,'Per-transaction limit is Rs.'.amount_fmt($c['per_txn_limit']).'.'];
            // foreach([['day','daily_limit'],['week','weekly_limit'],['month','monthly_limit']] as [$p,$k]) if(!empty($c[$k])){[$used]=self::periodExposure($db,(int)$c['id'],$p);$remain=max(0,(float)$c[$k]-$used);if($used+$amount>(float)$c[$k])return [false,ucfirst($p).' limit is Rs.'.amount_fmt($c[$k]).'. Confirmed so far: Rs.'.number_format($used,2).'. Remaining: Rs.'.number_format($remain,2).'. Ask Admin to raise this limit if needed.'];}
        }
        // Temporarily disabled: daily attempt-count limit
        // if(!empty($c['daily_txn_count_limit'])){[, $cnt]=self::periodExposure($db,(int)$c['id'],'day');if($cnt>=(int)$c['daily_txn_count_limit'])return [false,'Daily transaction-attempt limit reached ('.(int)$c['daily_txn_count_limit'].').'];}
        return [true,'OK'];
    }
    public static function evaluateConfirmed(PDO $db,int $txnId): array {
        $st=$db->prepare('SELECT t.*,c.per_txn_limit,c.daily_limit,c.weekly_limit,c.monthly_limit FROM transactions t JOIN customers c ON c.id=t.customer_id WHERE t.id=?');$st->execute([$txnId]);$t=$st->fetch();if(!$t)return [false,''];
        $amt=(float)($t['confirmed_amount']??$t['requested_amount']??0);$notes=[];if(!empty($t['per_txn_limit'])&&$amt>(float)$t['per_txn_limit'])$notes[]='confirmed amount '.number_format($amt,2).' > per-txn '.number_format((float)$t['per_txn_limit'],2);
        foreach([['day','daily_limit'],['week','weekly_limit'],['month','monthly_limit']] as [$p,$k]){if(empty($t[$k]))continue;[$s,$e]=local_period_bounds($p);$q=$db->prepare("SELECT COALESCE(SUM(COALESCE(confirmed_amount,requested_amount,0)),0) FROM transactions WHERE customer_id=? AND status='SUCCESS' AND created_at>=? AND created_at<?");$q->execute([$t['customer_id'],$s,$e]);$total=(float)$q->fetchColumn();if($total>(float)$t[$k])$notes[]="$p successful total ".number_format($total,2).' > limit '.number_format((float)$t[$k],2);}
        $breach=$notes?1:0;$q=$db->prepare('UPDATE transactions SET limit_breach=?,limit_note=? WHERE id=?');$q->execute([$breach,implode('; ',$notes),$txnId]);return [(bool)$breach,implode('; ',$notes)];
    }
}
