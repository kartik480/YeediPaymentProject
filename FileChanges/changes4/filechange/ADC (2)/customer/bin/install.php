<?php
declare(strict_types=1);require_once __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
$db=Database::pdo();$sql=file_get_contents(__DIR__.'/../../database/schema.sql');
// Execute statements carefully; schema avoids procedure bodies.
$parts=preg_split('/;\s*(?:\r?\n|$)/',$sql);foreach($parts as $part){$part=trim($part);if($part==='')continue;try{$db->exec($part);}catch(PDOException $e){if(str_contains($e->getMessage(),'Duplicate key name')||str_contains($e->getMessage(),'already exists')||str_contains($e->getMessage(),'Duplicate foreign key'))continue;throw $e;}}
echo "Database schema installed.\n";
