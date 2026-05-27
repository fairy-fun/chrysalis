<?php

declare(strict_types=1);

function entity_surface_inventory_provider_fetch_character_surfaces(
    PDO $pdo
): array {

    $results = [];

    /*
     * Canonical character-registry surfaces.
     *
     * Character identity authority lives in entities.id, but canonical prose
     * surfaces may originate in the characters table before they are mirrored
     * into entity_labels or semantic_aliases. This provider must therefore
     * consult the canonical registry directly so known characters do not become
     * invisible to suggestion workflows simply because alias expansion has not
     * been populated yet.
     */
    try {

        $stmt = $pdo->prepare("
            SELECT
                c.entity_id,
                c.char_name_full,
                c.char_name_first,
                c.char_name_last,
                c.search_name
            FROM characters c
            INNER JOIN entities e
                ON e.id = c.entity_id
            WHERE e.entity_type_id = 'entity_type_character'
              AND c.entity_id IS NOT NULL
              AND TRIM(c.entity_id) <> ''
        ");

        $stmt->execute();

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {

            $entityId = trim((string)(
                $row['entity_id'] ?? ''
            ));

            if ($entityId === '') {
                continue;
            }

            entity_surface_inventory_provider_append_surface(
                $results,
                $entityId,
                $row['char_name_full'] ?? '',
                'CHARACTER_FULL_NAME',
                1.0
            );

            entity_surface_inventory_provider_append_surface(
                $results,
                $entityId,
                $row['search_name'] ?? '',
                'CHARACTER_SEARCH_NAME',
                1.0
            );

            entity_surface_inventory_provider_append_surface(
                $results,
                $entityId,
                $row['char_name_first'] ?? '',
                'CHARACTER_FIRST_NAME',
                0.9
            );

            entity_surface_inventory_provider_append_surface(
                $results,
                $entityId,
                $row['char_name_last'] ?? '',
                'CHARACTER_LAST_NAME',
                0.88
            );
        }

    } catch (Throwable $e) {
        /*
         * Fail soft intentionally.
         */
    }

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
     * Deduplicate identical surfaces while preserving the strongest surface
     * authority for that literal surface.
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

function entity_surface_inventory_provider_append_surface(
    array &$results,
    string $entityId,
    mixed $surface,
    string $surfaceType,
    float $surfaceConfidence
): void {

    $surface = trim((string)$surface);

    if (
        $entityId === ''
        || $surface === ''
    ) {
        return;
    }

    $results[] = [
        'entity_id' => $entityId,
        'surface' => $surface,
        'surface_type' => $surfaceType,
        'surface_confidence' => $surfaceConfidence,
    ];
}
