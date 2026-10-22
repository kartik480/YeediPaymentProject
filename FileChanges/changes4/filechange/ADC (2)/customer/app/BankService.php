<?php
declare(strict_types=1);
final class BankService {
    public static function parseUpiUri(string $uri): array {
        $uri=trim($uri);
        $p=parse_url($uri);
        if(!$p || strtolower((string)($p['scheme']??''))!=='upi' || strtolower((string)($p['host']??''))!=='pay') throw new InvalidArgumentException('QR is not a standard upi://pay URI');
        parse_str((string)($p['query']??''),$params);
        $vpa=trim((string)($params['pa']??'')); $pn=trim((string)($params['pn']??''));
        if($vpa==='' || !str_contains($vpa,'@')) throw new InvalidArgumentException('UPI payee address (pa) not found');
        if($pn==='') $pn=$vpa;
        $signed=false; foreach(array_keys($params) as $k){ if(in_array(strtolower((string)$k),['sign','signature'],true)){$signed=true;break;} }
        return ['uri'=>$uri,'params'=>$params,'vpa'=>$vpa,'payee_name'=>$pn,'currency'=>(string)($params['cu']??'INR'),'signed'=>$signed];
    }
    public static function uniqueCode(PDO $db, string $desired): string {
        $base=substr(normalize_code($desired) ?: 'BANK',0,20); $code=$base; $n=2;
        while(true){$st=$db->prepare('SELECT 1 FROM bank_profiles WHERE code=?');$st->execute([$code]);if(!$st->fetchColumn())return $code;$code=substr($base,0,16).$n;$n++;}
    }
    public static function add(PDO $db, array $in): int {
        $p=self::parseUpiUri($in['upi_uri']); $code=self::uniqueCode($db,$in['code']?:$in['bank_name']);
        $secret=bin2hex(random_bytes(24));
        $st=$db->prepare('INSERT INTO bank_profiles(code,bank_name,label,vpa,payee_name,currency,qr_payload,qr_params_json,qr_image_path,enabled,rotation_order,rotation_minutes,signed_qr,webhook_secret,created_at) VALUES(?,?,?,?,?,?,?,?,?,1,?,?,?, ?,UTC_TIMESTAMP())');
        $st->execute([$code,trim($in['bank_name']),trim($in['label']??''),$p['vpa'],$p['payee_name'],$p['currency'],$p['uri'],json_encode($p['params'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$in['qr_image_path']??null,max(1,(int)$in['rotation_order']),max(1,(int)$in['rotation_minutes']),$p['signed']?1:0,$secret]);
        $id=(int)$db->lastInsertId();
        $cfg=$db->query('SELECT manual_active_bank_id FROM system_config WHERE id=1')->fetch();
        if(!$cfg){$db->exec("INSERT INTO system_config(id,rotation_enabled,updated_at) VALUES(1,0,UTC_TIMESTAMP())");$cfg=['manual_active_bank_id'=>null];}
        if(empty($cfg['manual_active_bank_id'])){$st=$db->prepare('UPDATE system_config SET manual_active_bank_id=?,updated_at=UTC_TIMESTAMP() WHERE id=1');$st->execute([$id]);}
        return $id;
    }
    public static function all(PDO $db): array { return $db->query('SELECT * FROM bank_profiles ORDER BY rotation_order,id')->fetchAll(); }
    public static function get(PDO $db,int $id): ?array { $st=$db->prepare('SELECT * FROM bank_profiles WHERE id=?');$st->execute([$id]);$r=$st->fetch();return $r?:null; }
    public static function active(PDO $db, ?DateTimeImmutable $at=null): ?array {
        $cfg=$db->query('SELECT * FROM system_config WHERE id=1')->fetch();
        if(!$cfg){$db->exec("INSERT INTO system_config(id,rotation_enabled,updated_at) VALUES(1,0,UTC_TIMESTAMP())");$cfg=['rotation_enabled'=>0,'manual_active_bank_id'=>null,'rotation_started_at'=>null];}
        $banks=$db->query('SELECT * FROM bank_profiles WHERE enabled=1 ORDER BY rotation_order,id')->fetchAll(); if(!$banks)return null;
        if(!(int)$cfg['rotation_enabled']){
            if(!empty($cfg['manual_active_bank_id'])) foreach($banks as $b) if((int)$b['id']===(int)$cfg['manual_active_bank_id']) return $b;
            return $banks[0];
        }
        $rot=array_values(array_filter($banks,fn($b)=>(int)$b['rotation_minutes']>0)); if(!$rot)return $banks[0];
        $start=!empty($cfg['rotation_started_at'])?new DateTimeImmutable($cfg['rotation_started_at'],new DateTimeZone('UTC')):new DateTimeImmutable('now',new DateTimeZone('UTC'));
        $now=$at?:new DateTimeImmutable('now',new DateTimeZone('UTC')); $elapsed=max(0,$now->getTimestamp()-$start->getTimestamp());
        $total=array_sum(array_map(fn($b)=>(int)$b['rotation_minutes']*60,$rot));$pos=$total?($elapsed%$total):0;$cursor=0;
        foreach($rot as $b){$cursor+=(int)$b['rotation_minutes']*60;if($pos<$cursor)return $b;}return $rot[0];
    }
    public static function exactIntent(array $bank): string { self::parseUpiUri((string)$bank['qr_payload']); return (string)$bank['qr_payload']; }
}
