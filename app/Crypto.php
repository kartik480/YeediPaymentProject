<?php
declare(strict_types=1);
final class Crypto {
    private static function key(): string { return hash('sha256',(string)Env::get('APP_KEY','change-me'),true); }
    public static function encrypt(string $plain): string {
        if ($plain==='') return '';
        if (!function_exists('openssl_encrypt')) throw new RuntimeException('OpenSSL is required to store encrypted secrets');
        $iv=random_bytes(12); $tag='';
        $ct=openssl_encrypt($plain,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);
        if($ct===false) throw new RuntimeException('Encryption failed');
        return base64_encode($iv.$tag.$ct);
    }
    public static function decrypt(string $enc): string {
        if ($enc==='') return '';
        if (!function_exists('openssl_decrypt')) return '';
        $raw=base64_decode($enc,true); if($raw===false || strlen($raw)<29) return '';
        $iv=substr($raw,0,12); $tag=substr($raw,12,16); $ct=substr($raw,28);
        $pt=openssl_decrypt($ct,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);
        return $pt===false?'':$pt;
    }
}
