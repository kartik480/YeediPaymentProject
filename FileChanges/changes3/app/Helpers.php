<?php
declare(strict_types=1);
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function unified_mode(): bool { return defined('YEEDIPAY_UNIFIED') && YEEDIPAY_UNIFIED; }
function app_base_path(): string {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $file = strtolower(basename($script));
    if (!in_array($file, ['index.php', 'router.php'], true)) return '';
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return ($base === '' || $base === '.' || $base === '/') ? '' : $base;
}
function url(string $path=''): string {
    if ($path === '' || ($path[0] ?? '') !== '/') return $path;
    return app_base_path().$path;
}
function redirect(string $url, int $code=303): never {
    if ($url !== '' && ($url[0] ?? '') === '/' && !str_starts_with($url, '//')) $url = app_base_path().$url;
    header('Location: '.$url, true, $code);
    exit;
}
function asset_url(string $path): string {
    $path = ltrim($path, '/');
    if (!str_starts_with($path, 'assets/')) $path = 'assets/'.$path;
    if (unified_mode()) {
        $surface = defined('YEEDIPAY_SURFACE') ? (string)YEEDIPAY_SURFACE : 'customer';
        $folder = match ($surface) {
            'admin' => '/admin/public/',
            'super-admin' => '/super-admin/public/',
            default => '/customer/public/',
        };
        return app_base_path().$folder.$path;
    }
    return '/'.$path;
}
function brand_logo_url(): string {
    return url('/image/yeedi.png');
}
function json_response(array $data, int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit; }
function health_response(string $service): never {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $dbok = Database::ping();
    $database = $dbok ? 'connected' : 'disconnected';
    json_response([
        'status' => $dbok ? 'ok' : 'degraded',
        'server' => 'running',
        'database' => $database,
        'timestamp' => gmdate('c'),
        'ok' => $dbok,
        'service' => $service,
        'db' => $dbok,
    ], $dbok ? 200 : 503);
}
function render_uncaught(Throwable $e, string $css = 'app.css'): never {
    $isDb = $e instanceof PDOException;
    $isExpected = !$isDb && ($e instanceof InvalidArgumentException || $e instanceof RuntimeException);
    if (!$isExpected) {
        Log::write('request', 'failed', [
            'type' => $e::class,
            'error' => Log::safeThrowable($e),
            'path' => (string)($_SERVER['REQUEST_URI'] ?? ''),
        ]);
    }
    if ($isDb) {
        http_response_code(503);
        $message = 'The service is temporarily unavailable. Please try again in a moment.';
    } elseif ($isExpected) {
        http_response_code(400);
        $message = $e->getMessage();
    } else {
        http_response_code(500);
        $message = 'An unexpected error occurred.';
    }
    echo '<!doctype html><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="'.e(asset_url($css)).'"><div class="wrap"><div class="error"><b>Error:</b> '.e($message).'</div><p><a href="javascript:history.back()">Go back</a></p></div>';
    exit;
}
function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="'.e(csrf_token()).'">'; }
function require_csrf(): void { $a=(string)($_POST['_csrf']??''); $b=(string)($_SESSION['_csrf']??''); if (!$a || !$b || !hash_equals($b,$a)) { http_response_code(419); exit('CSRF validation failed'); } }
function flash(string $type, string $message): void { $_SESSION['_flash'][]=['type'=>$type,'message'=>$message]; }
function take_flashes(): array { $f=$_SESSION['_flash']??[]; unset($_SESSION['_flash']); return $f; }
function now_utc(): string { return gmdate('Y-m-d H:i:s'); }
function company_name(): string { return (string)Env::get('COMPANY_NAME','YEEDIPAY'); }
function local_request_origin(): ?string {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $name = preg_replace('/:\d+$/', '', $host) ?? '';
    if (!in_array($name, ['localhost', '127.0.0.1', '[::1]'], true)) {
        return null;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    return ($https ? 'https' : 'http').'://'.$host;
}
function configured_public_base(string $localKey, string $liveKey, string $liveDefault): string {
    if (local_request_origin() !== null) {
        $localUrl = trim((string)Env::get($localKey, ''));
        if ($localUrl !== '') return rtrim($localUrl, '/');
        return (string)local_request_origin();
    }
    return rtrim((string)Env::get($liveKey, $liveDefault), '/');
}
function admin_url(string $path=''): string {
    $base = configured_public_base('LOCAL_ADMIN_PUBLIC_URL', 'ADMIN_PUBLIC_URL', 'https://admin.yeedipay.in');
    return $base.($path === '' ? '/admin' : $path);
}
function pay_url(string $path=''): string {
    $base = configured_public_base('LOCAL_PAY_PUBLIC_URL', 'PAY_PUBLIC_URL', 'https://customer.yeedipay.in');
    return $base.($path === '' ? '/' : $path);
}
function sms_demo_url(string $path=''): string {
    if (local_request_origin() !== null) {
        $localSms = trim((string)Env::get('LOCAL_SMS_DEMO_PUBLIC_URL', ''));
        if ($localSms !== '') return rtrim($localSms, '/').$path;
    }
    $configured = trim((string)Env::get('SMS_DEMO_PUBLIC_URL',''));
    if ($configured !== '') return rtrim($configured,'/').$path;
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    if ($host !== '' && (str_contains($host, 'localhost') || str_starts_with($host, '127.0.0.1'))) {
        return $scheme.'://'.$host.url('/sms').$path;
    }
    return 'https://sms-demo.yeedipay.in'.$path;
}
function normalize_code(string $s): string { return strtoupper(preg_replace('/[^A-Za-z0-9]/','',$s) ?? ''); }
function normalize_utr(string $s): string { return strtoupper(substr(preg_replace('/[^A-Za-z0-9_\-\/]/','',trim($s)) ?? '',0,160)); }
function mask_phone(string $p): string { $n=strlen($p); return $n<=4?$p:str_repeat('*',$n-4).substr($p,-4); }
function mask_account(string $a): string { $a=preg_replace('/\s+/','',trim($a))??''; $n=strlen($a); return $n<=4?$a:str_repeat('*',$n-4).substr($a,-4); }
function random_reference(string $customerCode, string $bankCode): string {
    $cust=str_pad(substr(normalize_code($customerCode),-5),5,'0',STR_PAD_LEFT);
    $bank=str_pad(substr(normalize_code($bankCode),0,3),3,'X');
    return 'YDP'.$cust.$bank.gmdate('mdHis').strtoupper(substr(bin2hex(random_bytes(4)),0,6));
}
function money_or_null(mixed $v): ?string {
    $s=trim((string)$v); if ($s==='') return null;
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/',$s)) throw new InvalidArgumentException('Invalid amount');
    $f=(float)$s; if ($f<=0) return null; return number_format($f,2,'.','');
}
function positive_int_or_null(mixed $v): ?int { $s=trim((string)$v); if ($s==='') return null; if(!ctype_digit($s)) throw new InvalidArgumentException('Invalid count'); $i=(int)$s; return $i>0?$i:null; }
function local_period_bounds(string $period, string $start='', string $end=''): array {
    $tz=new DateTimeZone(Env::get('APP_TIMEZONE','Asia/Kolkata') ?? 'Asia/Kolkata');
    $utc=new DateTimeZone('UTC'); $now=new DateTimeImmutable('now',$tz);
    if($period==='day') $s=$now->setTime(0,0,0);
    elseif($period==='week') $s=$now->modify('monday this week')->setTime(0,0,0);
    elseif($period==='month') $s=$now->modify('first day of this month')->setTime(0,0,0);
    elseif($period==='custom' && $start!=='' && $end!=='') { $s=new DateTimeImmutable($start.' 00:00:00',$tz); $e=(new DateTimeImmutable($end.' 00:00:00',$tz))->modify('+1 day'); return [$s->setTimezone($utc)->format('Y-m-d H:i:s'),$e->setTimezone($utc)->format('Y-m-d H:i:s')]; }
    else return [null,null];
    return [$s->setTimezone($utc)->format('Y-m-d H:i:s'), $now->setTimezone($utc)->modify('+1 second')->format('Y-m-d H:i:s')];
}
function view(string $file, array $vars=[]): never { extract($vars, EXTR_SKIP); $flashes=take_flashes(); include $file; exit; }
function route_path(): string {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = app_base_path();
    if ($base !== '' && str_starts_with($uri, $base)) $uri = substr($uri, strlen($base)) ?: '/';
    return rtrim($uri, '/') ?: '/';
}
function request_method(): string { return strtoupper($_SERVER['REQUEST_METHOD']??'GET'); }
function client_ip(): string { return (string)($_SERVER['REMOTE_ADDR']??''); }
function amount_fmt(mixed $v): string { return $v===null||$v===''?'':number_format((float)$v,2); }
