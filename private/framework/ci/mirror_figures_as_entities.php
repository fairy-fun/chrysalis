<?php

declare(strict_types=1);
require_once dirname(__DIR__,3).'/private/framework/api/api_bootstrap.php';
$pdo=makePdo();
$pdo->exec("INSERT INTO entity_type_classvals (id,code,label) VALUES ('entity_type_figure','figure','Figure') ON DUPLICATE KEY UPDATE code=VALUES(code)");
$count=$pdo->exec("INSERT INTO entities (id,entity_type_id) SELECT DISTINCT classval_id,'entity_type_figure' FROM figures f LEFT JOIN entities e ON e.id=f.classval_id WHERE f.classval_id IS NOT NULL AND f.classval_id<>'' AND e.id IS NULL");
echo 'OK: Mirrored figures: '.(string)$count.PHP_EOL;
