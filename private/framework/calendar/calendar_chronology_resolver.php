<?php

declare(strict_types=1);

function resolve_calendar_chronology_path(PDO $pdo, string $address): ?string
{
    $address = trim($address);

    if (!preg_match('/^[1-9][0-9]*(?:\.[1-9][0-9]*)*$/', $address)) {
        throw new InvalidArgumentException(
            'chronology_address must be dot-separated positive integers'
        );
    }

    $parts = array_map('intval', explode('.', $address));

    $selects = [];
    $params = [':path_depth' => count($parts)];

    foreach ($parts as $i => $part) {
        $depth = $i + 1;
        $selects[] = "SELECT {$depth} AS depth, :part_{$depth} AS sequence_index";
        $params[":part_{$depth}"] = $part;
    }

    $pathPartsSql = implode("\nUNION ALL\n", $selects);

    $stmt = $pdo->prepare("
        WITH RECURSIVE
        path_parts AS (
            {$pathPartsSql}
        ),
        walk AS (
            SELECT ce.id, ce.entity_id, 1 AS depth
            FROM sxnzlfun_chrysalis.calendar_events ce
            JOIN path_parts p
              ON p.depth = 1
             AND ce.sequence_index = p.sequence_index
            WHERE ce.parent_event_id IS NULL

            UNION ALL

            SELECT child.id, child.entity_id, walk.depth + 1
            FROM walk
            JOIN path_parts p
              ON p.depth = walk.depth + 1
            JOIN sxnzlfun_chrysalis.calendar_events child
              ON child.parent_event_id = walk.id
             AND child.sequence_index = p.sequence_index
        )
        SELECT entity_id
        FROM walk
        WHERE depth = :path_depth
    ");

    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        return null;
    }

    if (count($rows) > 1) {
        throw new RuntimeException('Chronology path resolved ambiguously');
    }

    return (string)$rows[0]['entity_id'];
}