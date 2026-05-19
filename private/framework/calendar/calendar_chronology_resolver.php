<?php

declare(strict_types=1);

function resolve_calendar_chronology_path(
    PDO $pdo,
    int $projectionId,
    string $address
): ?string {
    if ($projectionId <= 0) {
        throw new InvalidArgumentException(
            'projection_id must be positive'
        );
    }

    $address = trim($address);

    if (!preg_match('/^[1-9][0-9]*(?:\\.[1-9][0-9]*)*$/', $address)) {
        throw new InvalidArgumentException(
            'chronology_address must be dot-separated positive integers'
        );
    }

    $parts = array_map('intval', explode('.', $address));

    $selects = [];
    $params = [
        ':path_depth' => count($parts),
        ':projection_id' => $projectionId,
    ];

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
            SELECT
                ce.id AS _internal_id,
                ce.entity_id,
                1 AS depth
            FROM sxnzlfun_chrysalis.calendar_events ce
            JOIN path_parts p
              ON p.depth = 1
             AND ce.sequence_index = p.sequence_index
            WHERE ce.parent_event_id IS NULL
              AND ce.projection_id = :projection_id

            UNION ALL

            SELECT
                child.id AS _internal_id,
                child.entity_id,
                walk.depth + 1
            FROM walk
            JOIN path_parts p
              ON p.depth = walk.depth + 1
            JOIN sxnzlfun_chrysalis.calendar_events child
              ON child.parent_event_id = walk._internal_id
             AND child.sequence_index = p.sequence_index
             AND child.projection_id = :projection_id
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
        throw new RuntimeException(
            'Chronology path resolved ambiguously within projection'
        );
    }

    return (string)$rows[0]['entity_id'];
}

function inherit_calendar_subevent_chronology(
    array $parent,
    ?int $subeventIndex
): array {

    return [

        'real_date_start_id'
        => $parent['real_date_start_id'] ?? null,

        'real_date_end_id'
        => $parent['real_date_end_id'] ?? null,

        'week_index'
        => isset($parent['week_index'])
            ? (int)$parent['week_index']
            : null,

        'day_index'
        => isset($parent['day_index'])
            ? (int)$parent['day_index']
            : null,

        'time_index'
        => isset($parent['time_index'])
            ? (int)$parent['time_index']
            : null,

        'event_index'
        => isset($parent['event_index'])
            ? (int)$parent['event_index']
            : null,

        'chronology_address'
        => build_subevent_chronology_address(
            $parent['chronology_address'] ?? null,
            $subeventIndex
        ),
    ];
}

function build_subevent_chronology_address(
    ?string $parentAddress,
    ?int $subeventIndex
): ?string {

    if (
        $parentAddress === null ||
        trim($parentAddress) === ''
    ) {
        return null;
    }

    if (
        $subeventIndex === null ||
        $subeventIndex < 1
    ) {
        return $parentAddress;
    }

    return $parentAddress . '.' . $subeventIndex;
}