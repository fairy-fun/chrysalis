<?php

declare(strict_types=1);

function entity_surface_inventory_provider_fetch_character_surfaces(
    PDO $pdo
): array {

    $results = [];

    /*
     * Canonical ontology surfaces.
     */
    try {

        $stmt = $pdo->prepare("
            SELECT
                e.id AS entity_id,
                el.label AS surface
            FROM entity_labels el
            INNER JOIN entities e
                ON e.id = el.entity_id
            WHERE e.entity_type_id = 'entity_type_character'
              AND el.label IS NOT NULL
              AND TRIM(el.label) <> ''
            ORDER BY el.label
        ");

        $stmt->execute();

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {

            $entityId = trim((string)(
                $row['entity_id'] ?? ''
            ));

            $surface = trim((string)(
                $row['surface'] ?? ''
            ));

            if (
                $entityId === ''
                || $surface === ''
            ) {
                continue;
            }

            $results[] = [
                'entity_id' => $entityId,
                'surface' => $surface,
                'surface_type' => 'CANONICAL_LABEL',
                'surface_confidence' => 1.0,
            ];
        }

    } catch (Throwable $e) {
        /*
         * Fail soft intentionally.
         */
    }

    /*
     * Advisory ontology surfaces.
     */
    try {

        $stmt = $pdo->prepare("
            SELECT
                e.id AS entity_id,
                sa.alias AS surface
            FROM semantic_aliases sa
            INNER JOIN entities e
                ON e.id = sa.entity_id
            WHERE e.entity_type_id = 'entity_type_character'
              AND sa.alias IS NOT NULL
              AND TRIM(sa.alias) <> ''
            ORDER BY sa.alias
        ");

        $stmt->execute();

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {

            $entityId = trim((string)(
                $row['entity_id'] ?? ''
            ));

            $surface = trim((string)(
                $row['surface'] ?? ''
            ));

            if (
                $entityId === ''
                || $surface === ''
            ) {
                continue;
            }

            $results[] = [
                'entity_id' => $entityId,
                'surface' => $surface,
                'surface_type' => 'SEMANTIC_ALIAS',
                'surface_confidence' => 0.95,
            ];
        }

    } catch (Throwable $e) {
        /*
         * Fail soft intentionally.
         */
    }

    /*
     * Deduplicate identical surfaces while preserving
     * earliest canonical occurrence.
     */
    $deduplicated = [];

    foreach ($results as $result) {

        $key = mb_strtolower(trim((string)(
            $result['surface'] ?? ''
        )));

        if ($key === '') {
            continue;
        }

        if (!isset($deduplicated[$key])) {
            $deduplicated[$key] = $result;
            continue;
        }

        $existingType = (string)(
            $deduplicated[$key]['surface_type'] ?? ''
        );

        $incomingType = (string)(
            $result['surface_type'] ?? ''
        );

        /*
         * Canonical ontology surfaces outrank aliases.
         */
        if (
            $existingType !== 'CANONICAL_LABEL'
            && $incomingType === 'CANONICAL_LABEL'
        ) {
            $deduplicated[$key] = $result;
        }
    }

    return array_values($deduplicated);
}