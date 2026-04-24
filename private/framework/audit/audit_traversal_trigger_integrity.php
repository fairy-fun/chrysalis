<?php

declare(strict_types=1);

function audit_traversal_trigger_absence(PDO $pdo, string $schemaName): array
{
    $sql = "
        SELECT TRIGGER_NAME
        FROM INFORMATION_SCHEMA.TRIGGERS
        WHERE TRIGGER_SCHEMA = :schema_name
          AND TRIGGER_NAME LIKE 'trg_entity_traversal_steps_%'
        ORDER BY TRIGGER_NAME
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':schema_name' => $schemaName,
    ]);

    $found = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return [
        'ok' => count($found) === 0,
        'schema_name' => $schemaName,
        'unexpected_trigger_count' => count($found),
        'unexpected_triggers' => $found,
    ];
}

function assert_traversal_trigger_absence(PDO $pdo, string $schemaName): void
{
    $audit = audit_traversal_trigger_absence($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Legacy traversal triggers must not exist: '
        . implode(', ', $audit['unexpected_triggers'])
    );
}