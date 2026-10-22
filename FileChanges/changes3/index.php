<?php
declare(strict_types=1);

// Customer-only BigRock build. Admin/Super Admin are deliberately not bundled here.
define('YEEDIPAY_UNIFIED', true);
$uri=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
$script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??''));
$file=strtolower(basename($script));$base='';
if(in_array($file,['index.php','router.php'],true)){$base=rtrim(str_replace('\\','/',dirname($script)),'/');if($base==='.'||$base==='/')$base='';}
if($base!==''&&str_starts_with($uri,$base))$uri=substr($uri,strlen($base))?:'/';
$uri=rtrim($uri,'/')?:'/';

if(preg_match('#^/video/([^/]+\.mp4)$#i',$uri,$m)){$name=basename(rawurldecode($m[1]));$path=__DIR__.'/video/'.$name;if(!is_file($path)){http_response_code(404);exit('Video not found');}header('Content-Type: video/mp4');header('Content-Length: '.filesize($path));header('Accept-Ranges: bytes');readfile($path);exit;}
if($uri==='/image/yeedi.png'){$path=__DIR__.'/image/yeedi.png';if(is_file($path)){header('Content-Type:image/png');header('Content-Length: '.filesize($path));readfile($path);exit;}}
if(preg_match('#^/(admin|super-admin|sms)(?:/|$)#i',$uri)){http_response_code(404);exit('This ZIP is the YEEDIPAY customer portal package. Admin/SMS console is not included.');}
define('YEEDIPAY_SURFACE','customer');
require __DIR__.'/customer/public/index.php';
