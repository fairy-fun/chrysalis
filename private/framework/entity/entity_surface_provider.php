<?php

declare(strict_types=1);

function entity_surface_provider_normalize_surface(
    string $surface
): string {

    return mb_strtolower(trim($surface));
}

function entity_surface_provider_fetch_exact_surface_candidates(
    PDO $pdo,
    string $surface,
    ?string $entityTypeId = null
): array {

    $surface = trim($surface);

    if ($surface === '') {
        return [];
    }

    $normalizedSurface = entity_surface_provider_normalize_surface(
        $surface
    );

    $candidates = [];

    /*
     * Canonical ontology surface:
     * entity_labels.label
     */
    try {
        $sql = "
            SELECT
                e.id AS entity_id,
                e.entity_type_id,
                el.label AS matched_surface
            FROM entity_labels el
            INNER JOIN entities e
                ON e.id = el.entity_id
            WHERE LOWER(el.label) = LOWER(:surface)
        ";

        if ($entityTypeId !== null && $entityTypeId !== '') {
            $sql .= "
                AND e.entity_type_id = :entity_type_id
            ";
        }

        $sql .= "
            LIMIT 100
        ";

        $stmt = $pdo->prepare($sql);

        $params = [
            ':surface' => $surface,
        ];

        if ($entityTypeId !== null && $entityTypeId !== '') {
            $params[':entity_type_id'] = $entityTypeId;
        }

        $stmt->execute($params);

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {

            if (!is_array($row)) {
                continue;
            }

            $entityId = trim((string)(
                $row['entity_id'] ?? ''
            ));

            if ($entityId === '') {
                continue;
            }

            $candidates[] = [
                'entity_id' => $entityId,
                'entity_type_id' => $row['entity_type_id'] ?? null,
                'matched_surface' => $row['matched_surface'] ?? $surface,
                'matched_surface_type' => 'CANONICAL_LABEL',
                'matched_lookup_surface' => $normalizedSurface,
                'candidate_label' => $row['matched_surface'] ?? $surface,
                'surface_confidence' => 1.0,
            ];
        }
    } catch (Throwable $e) {
        /*
         * Surface provider must fail soft.
         * Arbitration/resolvers decide downstream behavior.
         */
    }

    /*
     * Advisory ontology surfaces:
     * semantic_aliases.alias
     */
    try {
        $sql = "
            SELECT
                e.id AS entity_id,
                e.entity_type_id,
                sa.alias
            FROM semantic_aliases sa
            INNER JOIN entities e
                ON e.id = sa.entity_id
            WHERE LOWER(sa.alias) = LOWER(:surface)
        ";

        if ($entityTypeId !== null && $entityTypeId !== '') {
            $sql .= "
                AND e.entity_type_id = :entity_type_id
            ";
        }

        $sql .= "
            LIMIT 100
        ";

        $stmt = $pdo->prepare($sql);

        $params = [
            ':surface' => $surface,
        ];

        if ($entityTypeId !== null && $entityTypeId !== '') {
            $params[':entity_type_id'] = $entityTypeId;
        }

        $stmt->execute($params);

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {

            if (!is_array($row)) {
                continue;
            }

            $entityId = trim((string)(
                $row['entity_id'] ?? ''
            ));

            if ($entityId === '') {
                continue;
            }

            $alreadyPresent = false;

            foreach ($candidates as $candidate) {
                if (
                    ($candidate['entity_id'] ?? null) === $entityId
                    && ($candidate['matched_surface_type'] ?? null) === 'CANONICAL_LABEL'
                ) {
                    $alreadyPresent = true;
                    break;
                }
            }

            if ($alreadyPresent) {
                continue;
            }

            $candidates[] = [
                'entity_id' => $entityId,
                'entity_type_id' => $row['entity_type_id'] ?? null,
                'matched_surface' => $row['alias'] ?? $surface,
                'matched_surface_type' => 'SEMANTIC_ALIAS',
                'matched_lookup_surface' => $normalizedSurface,
                'candidate_label' => $row['alias'] ?? $surface,
                'surface_confidence' => 0.95,
            ];
        }
    } catch (Throwable $e) {
        /*
         * Fail soft intentionally.
         */
    }

    /*
     * Exact entity-id fallback.
     */
    try {
        $sql = "
            SELECT
                id,
                entity_type_id
            FROM entities
            WHERE id = :surface
        ";

        if ($entityTypeId !== null && $entityTypeId !== '') {
            $sql .= "
                AND entity_type_id = :entity_type_id
            ";
        }

        $sql .= "
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);

        $params = [
            ':surface' => $surface,
        ];

        if ($entityTypeId !== null && $entityTypeId !== '') {
            $params[':entity_type_id'] = $entityTypeId;
        }

        $stmt->execute($params);

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {

            if (!is_array($row)) {
                continue;
            }

            $entityId = trim((string)(
                $row['id'] ?? ''
            ));

            if ($entityId === '') {
                continue;
            }

            $alreadyPresent = false;

            foreach ($candidates as $candidate) {
                if (($candidate['entity_id'] ?? null) === $entityId) {
                    $alreadyPresent = true;
                    break;
                }
            }

            if ($alreadyPresent) {
                continue;
            }

            $candidates[] = [
                'entity_id' => $entityId,
                'entity_type_id' => $row['entity_type_id'] ?? null,
                'matched_surface' => $surface,
                'matched_surface_type' => 'ENTITY_ID',
                'matched_lookup_surface' => $normalizedSurface,
                'candidate_label' => $surface,
                'surface_confidence' => 0.9,
            ];
        }
    } catch (Throwable $e) {
        /*
         * Fail soft intentionally.
         */
    }

    return $candidates;
}