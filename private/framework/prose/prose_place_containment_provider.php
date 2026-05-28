<?php

declare(strict_types=1);

function prose_place_containment_provider_fetch_context(
    PDO $pdo,
    array $directPlaceIds
): array {

    $directPlaceIds = array_values(array_unique(array_filter(array_map(
        static fn (mixed $value): string => trim((string)$value),
        $directPlaceIds
    ))));

    if ($directPlaceIds === []) {
        return [];
    }

    $columns = prose_place_containment_provider_columns($pdo);

    if (!in_array('place_id', $columns, true)) {
        return [];
    }

    $parentColumn = prose_place_containment_provider_parent_column($columns);

    if ($parentColumn === null) {
        return prose_place_containment_provider_flat_context(
            $directPlaceIds
        );
    }

    $context = [];

    foreach ($directPlaceIds as $placeId) {
        $context[$placeId] = [
            'place_id' => $placeId,
            'containment_depth' => 0,
            'ancestors' => [],
        ];
    }

    try {
        foreach ($directPlaceIds as $placeId) {
            $lineage = prose_place_containment_provider_fetch_lineage(
                $pdo,
                $placeId,
                $parentColumn
            );

            if (!isset($context[$placeId])) {
                continue;
            }

            $context[$placeId]['ancestors'] = $lineage;
            $context[$placeId]['containment_depth'] = count($lineage);
        }
    } catch (Throwable $e) {
        return prose_place_containment_provider_flat_context(
            $directPlaceIds
        );
    }

    return $context;
}

function prose_place_containment_provider_fetch_lineage(
    PDO $pdo,
    string $placeId,
    string $parentColumn
): array {

    $ancestors = [];
    $seen = [$placeId => true];
    $currentPlaceId = $placeId;

    for ($depth = 0; $depth < 25; $depth++) {
        $stmt = $pdo->prepare(
            "SELECT
                child.{$parentColumn} AS parent_place_id,
                parent.place_name AS parent_place_name
             FROM sxnzlfun_chrysalis.places child
             LEFT JOIN sxnzlfun_chrysalis.places parent
                 ON parent.place_id = child.{$parentColumn}
             WHERE child.place_id = :place_id
             LIMIT 1"
        );

        $stmt->execute([
            ':place_id' => $currentPlaceId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            break;
        }

        $parentId = trim((string)(
            $row['parent_place_id'] ?? ''
        ));

        $parentPlaceName = trim((string)(
            $row['parent_place_name'] ?? ''
        ));

        if ($parentId === '' || isset($seen[$parentId])) {
            break;
        }

        $ancestors[] = [
            'place_id' => $parentId,

            'place_label' => (
                $parentPlaceName !== ''
                    ? $parentPlaceName
                    : $parentId
            ),

            'candidate_relationship_type'
                => 'ancestor_of_candidate',

            'distance'
                => $depth + 1,
        ];

        $seen[$parentId] = true;
        $currentPlaceId = $parentId;
    }

    return $ancestors;
}

function prose_place_containment_provider_columns(PDO $pdo): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'places'
        ");

        $stmt->execute();

        $columns = [];

        while (($column = $stmt->fetchColumn()) !== false) {
            $column = trim((string)$column);

            if ($column !== '') {
                $columns[] = $column;
            }
        }

        return $columns;
    } catch (Throwable $e) {
        return [];
    }
}

function prose_place_containment_provider_parent_column(
    array $columns
): ?string {

    foreach ([
        'parent_place_id',
        'containing_place_id',
        'container_place_id',
        'place_parent_id',
        'parent_id',
    ] as $candidateColumn) {
        if (in_array($candidateColumn, $columns, true)) {
            return $candidateColumn;
        }
    }

    return null;
}

function prose_place_containment_provider_flat_context(
    array $directPlaceIds
): array {

    $context = [];

    foreach ($directPlaceIds as $placeId) {
        $context[$placeId] = [
            'place_id' => $placeId,
            'containment_depth' => 0,
            'ancestors' => [],
        ];
    }

    return $context;
}
