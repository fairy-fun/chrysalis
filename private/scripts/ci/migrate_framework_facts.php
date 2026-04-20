<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

$pdo = db();

$sql = "
    SELECT *
    FROM frameworks
    WHERE framework_name LIKE 'fw_%'
       OR framework_text LIKE '%fw_register_system_procedure%'
       OR framework_text LIKE '%fw_upsert_system_directive%'
       OR framework_text LIKE '%validate_system_directive%'
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'count' => count($rows),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;