<?php

declare(strict_types=1);

function assert_valid_classval(
    PDO $pdo,
    string $tableName,
    string $id,
    string $fieldName
): void {
    $tableName = trim($tableName);
    $id = trim($id);
    $fieldName = trim($fieldName);

    if ($id === '') {
        throw new InvalidArgumentException("{$fieldName} must be non-empty");
    }

    $allowedTables = [
        'calendar_time_label_classvals',
        'calendar_event_type_classvals',
        'calendar_domain_classvals',
        'calendar_class_type_classvals',
    ];

    if (!in_array($tableName, $allowedTables, true)) {
        throw new InvalidArgumentException("Unsupported classval table: {$tableName}");
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM sxnzlfun_chrysalis.{$tableName}
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id,
    ]);

    $found = $stmt->fetchColumn();

    if (!is_string($found) || trim($found) === '') {
        throw new InvalidArgumentException("Invalid {$fieldName}: {$id}");
    }
}