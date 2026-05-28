<?php

declare(strict_types=1);

function prose_place_surface_inventory_provider_fetch_surfaces(
    PDO $pdo
): array {

    $placeColumns = prose_place_surface_inventory_provider_columns(
        $pdo
    );

    if (!in_array('place_id', $placeColumns, true)) {
        return [];
    }

    $surfaceColumns = array_values(array_intersect(
        [
            'place_name',
            'place_label',
            'canonical_name',
            'search_name',
            'name',
            'label',
        ],
        $placeColumns
    ));

    if ($surfaceColumns === []) {
        return [];
    }

    $selectParts = ['place_id'];

    foreach ($surfaceColumns as $column) {
        $selectParts[] = $column;
    }

    $results = [];

    try {
        $stmt = $pdo->prepare(
            "SELECT " . implode(', ', $selectParts) . "\n"
            . "FROM sxnzlfun_chrysalis.places\n"
            . "WHERE place_id IS NOT NULL\n"
            . "  AND TRIM(place_id) <> ''"
        );

        $stmt->execute();

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {

            if (!is_array($row)) {
                continue;
            }

            $placeId = trim((string)(
                $row['place_id'] ?? ''
            ));

            if ($placeId === '') {
                continue;
            }

            foreach ($surfaceColumns as $column) {
                prose_place_surface_inventory_provider_append_surface(
                    $results,
                    $placeId,
                    $row[$column] ?? '',
                    'PLACE_' . strtoupper($column),
                    prose_place_surface_inventory_provider_surface_confidence($column)
                );
            }
        }
    } catch (Throwable $e) {
        /*
         * Place suggestion must fail soft.
         * Missing or transitional place schema should not break prose ingestion.
         */
    }

    $deduplicated = [];

    foreach ($results as $result) {
        $key = mb_strtolower(trim((string)(
            $result['place_id'] ?? ''
        ))) . '|' . mb_strtolower(trim((string)(
            $result['surface'] ?? ''
        )));

        if ($key === '|') {
            continue;
        }

        if (!isset($deduplicated[$key])) {
            $deduplicated[$key] = $result;
            continue;
        }

        $existingConfidence = (float)(
            $deduplicated[$key]['surface_confidence'] ?? 0.0
        );

        $incomingConfidence = (float)(
            $result['surface_confidence'] ?? 0.0
        );

        if ($incomingConfidence > $existingConfidence) {
            $deduplicated[$key] = $result;
        }
    }

    return array_values($deduplicated);
}

function prose_place_surface_inventory_provider_columns(PDO $pdo): array
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

function prose_place_surface_inventory_provider_append_surface(
    array &$results,
    string $placeId,
    mixed $surface,
    string $surfaceType,
    float $surfaceConfidence
): void {

    $placeId = trim($placeId);
    $surface = trim((string)$surface);

    if (
        $placeId === ''
        || $surface === ''
    ) {
        return;
    }

    $results[] = [
        'place_id' => $placeId,
        'surface' => $surface,
        'surface_type' => $surfaceType,
        'surface_confidence' => $surfaceConfidence,
    ];
}

function prose_place_surface_inventory_provider_surface_confidence(
    string $column
): float {

    return match ($column) {
        'place_name',
        'place_label',
        'canonical_name' => 1.0,

        'search_name' => 0.98,

        'name',
        'label' => 0.95,

        default => 0.9,
    };
}
