<?php

declare(strict_types=1);

function governance_classval_id(
    PDO $pdo,
    string $table,
    string $code
): string {

    $allowedTables = [
        'epistemic_origin_classvals',
        'adjudication_status_classvals',
        'contradiction_state_classvals',
    ];

    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException(
            'Unsupported governance classval table: ' . $table
        );
    }

    static $cache = [];

    $key = $table . ':' . $code;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM {$table}
        WHERE code = :code
        LIMIT 1
    ");

    $stmt->execute([
        ':code' => $code,
    ]);

    $id = $stmt->fetchColumn();

    if (!is_string($id) || trim($id) === '') {
        throw new RuntimeException(
            'Missing governance classval: '
            . $table . ' / ' . $code
        );
    }

    $cache[$key] = $id;

    return $id;
}