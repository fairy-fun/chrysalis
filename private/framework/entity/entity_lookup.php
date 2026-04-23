<?php

declare(strict_types=1);

function find_exact_entity_by_canonical_label(
    PDO $pdo,
    string $rawLabel,
    string $entityTypeId
): ?string {
    $label = trim($rawLabel);
    $entityTypeId = trim($entityTypeId);

    if ($label === '') {
        throw new InvalidArgumentException('Entity label is required');
    }

    if ($entityTypeId === '') {
        throw new InvalidArgumentException('Entity type id is required');
    }

    $countSql = <<<'SQL'
    SELECT COUNT(*)
    FROM sxnzlfun_chrysalis.entities e
    INNER JOIN sxnzlfun_chrysalis.entity_texts et
        ON et.entity_id = e.id
    WHERE e.entity_type_id = :entity_type_id
      AND et.canonical_label = :canonical_label
    SQL;

    $countStmt = $pdo->prepare($countSql);
    $countStmt->bindValue(':entity_type_id', $entityTypeId, PDO::PARAM_STR);
    $countStmt->bindValue(':canonical_label', $label, PDO::PARAM_STR);
    $countStmt->execute();

    $matchCount = (int) $countStmt->fetchColumn();

    if ($matchCount > 1) {
        throw new RuntimeException('Ambiguous exact canonical label match for entity type');
    }

    if ($matchCount === 0) {
        return null;
    }

    $idSql = <<<'SQL'
    SELECT e.id
    FROM sxnzlfun_chrysalis.entities e
    INNER JOIN sxnzlfun_chrysalis.entity_texts et
        ON et.entity_id = e.id
    WHERE e.entity_type_id = :entity_type_id
      AND et.canonical_label = :canonical_label
    LIMIT 1
    SQL;

    $idStmt = $pdo->prepare($idSql);
    $idStmt->bindValue(':entity_type_id', $entityTypeId, PDO::PARAM_STR);
    $idStmt->bindValue(':canonical_label', $label, PDO::PARAM_STR);
    $idStmt->execute();

    $entityId = $idStmt->fetchColumn();

    if ($entityId === false) {
        throw new RuntimeException('Exact entity match disappeared during lookup');
    }

    return (string) $entityId;
}

function entity_exists_by_id(PDO $pdo, string $entityId): bool
{
    $sql = <<<'SQL'
    SELECT 1
    FROM sxnzlfun_chrysalis.entities
    WHERE id = :entity_id
    LIMIT 1
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchColumn() !== false;
}