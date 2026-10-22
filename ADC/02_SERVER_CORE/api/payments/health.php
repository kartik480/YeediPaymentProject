<?php
require_once dirname(__DIR__, 2) . '/db.php';
try {
    yeedi_db()->query('SELECT 1');
    json_response(['ok'=>true,'service'=>'YEEDI SMS AUTO V5 CORE','time'=>gmdate('Y-m-d\TH:i:s\Z')]);
} catch (Throwable $e) {
    json_response(['ok'=>false,'error'=>'database_unavailable'], 503);
}
