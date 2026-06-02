<?php

declare(strict_types=1);

function resolve_character_knowledge_packet(PDO $pdo, string $characterId): array
{
    $characterId = trim($characterId);

    if ($characterId === '') {
        throw new InvalidArgumentException('character_id must be a non-empty string');
    }

    $character = fetch_resolved_character_row($pdo, $characterId);
    $bridge = fetch_character_entity_bridge($pdo, $characterId);
    $entityId = trim((string) ($bridge['entity_id'] ?? ''));

    if ($entityId === '') {
        throw new RuntimeException('Character entity bridge is missing entity_id');
    }

    return [
        'character_id' => $characterId,
        'entity_id' => $entityId,
        'character' => $character,
        'relationships' => fetch_character_relationship_packets($pdo, $entityId),
        'relationship_facts' => fetch_character_relationship_facts($pdo, $entityId),
        'character_facts' => fetch_character_facts($pdo, $entityId),
    ];
}

function fetch_resolved_character_row(PDO $pdo, string $characterId): array
{
    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT *
FROM v_character_resolved
WHERE character_id = :character_id
LIMIT 1
SQL
    );

    $stmt->execute([
        ':character_id' => $characterId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException('Character was not found in v_character_resolved');
    }

    return $row;
}

function fetch_character_entity_bridge(PDO $pdo, string $characterId): array
{
    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT
    character_id,
    entity_id
FROM characters
WHERE character_id = :character_id
LIMIT 1
SQL
    );

    $stmt->execute([
        ':character_id' => $characterId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException('Character entity bridge was not found in characters');
    }

    return $row;
}

function fetch_character_relationship_packets(PDO $pdo, string $entityId): array
{
    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT *
FROM v_character_relationship_packet
WHERE character_entity_id = :entity_id
SQL
    );

    $stmt->execute([
        ':entity_id' => $entityId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}

function fetch_character_relationship_facts(PDO $pdo, string $entityId): array
{
    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT
    rf.*
FROM v_relationship_fact_resolved rf
INNER JOIN relationships r
    ON r.entity_id = rf.relationship_entity_id
WHERE r.entity_a_id = :entity_id_a
   OR r.entity_b_id = :entity_id_b
SQL
    );

    $stmt->execute([
        ':entity_id_a' => $entityId,
        ':entity_id_b' => $entityId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}

function fetch_character_facts(PDO $pdo, string $entityId): array
{
    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT *
FROM canonical_entity_linked_facts_global
WHERE subject_entity_id = :entity_id
SQL
    );

    $stmt->execute([
        ':entity_id' => $entityId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}
