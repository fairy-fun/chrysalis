<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../framework/bootstrap.php';

$pdo = db();
$schemaName = db_name();

$sql = "
    SELECT directive_key, directive_text, target_procedure
    FROM system_directives
    WHERE target_procedure IS NOT NULL
    ORDER BY directive_key ASC, target_procedure ASC
";

$stmt = $pdo->prepare($sql);
if (!$stmt instanceof PDOStatement) {
    fwrite(STDERR, "Failed to prepare directive registry drift query.\n");
    exit(2);
}

$ok = $stmt->execute();
if ($ok !== true) {
    fwrite(STDERR, "Failed to execute directive registry drift query.\n");
    exit(2);
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!is_array($rows)) {
    fwrite(STDERR, "Failed to fetch directive registry drift rows.\n");
    exit(2);
}
if (!defined('FW_AUDIT_ENTRYPOINT')) {
    throw new RuntimeException('FW_AUDIT_ENTRYPOINT is not defined');
}

if (!is_file(dirname(__DIR__, 3) . '/' . FW_AUDIT_ENTRYPOINT)) {
    throw new RuntimeException('Audit entrypoint file missing: ' . FW_AUDIT_ENTRYPOINT);
}
$errors = [];

foreach ($rows as $row) {
    $directiveKey = (string)($row['directive_key'] ?? '');
    $directiveText = (string)($row['directive_text'] ?? '');
    $targetProcedure = (string)($row['target_procedure'] ?? '');

    try {
        fw_validate_procedure_execution_directive(
            $pdo,
            $schemaName,
            $targetProcedure,
            $directiveText
        );
    } catch (Throwable $e) {
        $errors[] = "Directive validation failed for {$directiveKey}: " . $e->getMessage();
    }
}

sort($errors, SORT_STRING);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "OK: directive registry drift check passed.\n");
exit(0);