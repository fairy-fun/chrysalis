<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../framework/bootstrap.php';

$pdo = db();
$schemaName = db_name();

/**
 * Adjust table/column names only if your schema differs.
 */
$sql = "
    SELECT directive_key, directive_text, target_procedure
    FROM system_directives
    WHERE target_procedure IS NOT NULL
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

foreach ($rows as $row) {
    $directiveKey = (string)$row['directive_key'];
    $directiveText = (string)$row['directive_text'];
    $targetProcedure = (string)$row['target_procedure'];

    $expectedText = fw_build_procedure_call_directive_text($schemaName, $targetProcedure);

    if ($directiveText !== $expectedText) {
        $errors[] = "Directive text drift for {$directiveKey}: expected '{$expectedText}', got '{$directiveText}'";
    }

    if (!fw_procedure_exists($pdo, $schemaName, $targetProcedure)) {
        $errors[] = "Missing target procedure for {$directiveKey}: {$schemaName}.{$targetProcedure}";
    }

    if (!fw_is_registered_active_procedure($pdo, $targetProcedure)) {
        $errors[] = "Inactive or missing registry entry for {$directiveKey}: {$targetProcedure}";
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "OK: directive registry drift check passed.\n");
exit(0);