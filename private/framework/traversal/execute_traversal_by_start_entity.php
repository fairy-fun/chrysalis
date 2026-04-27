<?php

declare(strict_types=1);

function execute_traversal_by_traversal_id_and_start_entity(
    PDO $pdo,
    int $traversalId,
    string $startEntityId
): array {
    if ($traversalId < 1) {
        throw new InvalidArgumentException('traversal_id must be a positive integer');
    }

    $startEntityId = trim($startEntityId);

    if ($startEntityId === '') {
        throw new InvalidArgumentException('start_entity_id must be a non-empty string');
    }

    $pathStmt = $pdo->prepare(
        "SELECT p.id
         FROM sxnzlfun_chrysalis.entity_traversal_paths p
         WHERE p.traversal_id = :traversal_id
         ORDER BY p.priority ASC, p.id ASC"
    );

    $pathStmt->execute([
        ':traversal_id' => $traversalId
    ]);

    $pathIds = array_map('intval', $pathStmt->fetchAll(PDO::FETCH_COLUMN));

    if ($pathIds === []) {
        throw new RuntimeException('Traversal path not found');
    }

    $rows = [];

    foreach ($pathIds as $pathId) {
        $result = resolve_entity_traversal_full($pdo, $pathId);
        $rootAlias = (string)($result['plan']['root']['alias'] ?? '');

        if ($rootAlias === '') {
            throw new RuntimeException('Root alias not resolved');
        }

        $sql = (string)$result['sql'];
        $needle = "WHERE p.id = " . $pathId . "\nORDER BY";
        $replacement = "WHERE p.id = " . $pathId . "\n  AND " . $rootAlias . ".id = :start_entity_id\nORDER BY";
        $replacement = "WHERE p.id = " . $pathId . "\n  AND " . $rootAlias . ".entity_id = :start_entity_id\nORDER BY";
        $filteredSql = str_replace($needle, $replacement, $sql);

        if ($filteredSql === $sql) {
            throw new RuntimeException('Unable to apply start entity filter');
        }

        $stmt = $pdo->prepare($filteredSql);
        $stmt->execute([':start_entity_id' => $startEntityId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['_traversal_path_id'] = $pathId;
            $rows[] = $row;
        }
    }

    return $rows;
}
