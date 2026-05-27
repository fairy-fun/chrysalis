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
     * Canonical character-registry surfaces:
     * characters.char_name_full / char_name_first / char_name_last / search_name
     */
    if ($entityTypeId === null || $entityTypeId === '' || $entityTypeId === 'entity_type_character') {
        try {

            $allowSearchTokenMatch = mb_strlen($normalizedSurface) >= 3;

            $sql = "
                SELECT
                    e.id AS entity_id,
                    e.entity_type_id,
                    c.char_name_full,
                    c.char_name_first,
                    c.char_name_last,
                    c.search_name,
                    CASE
                        WHEN LOWER(c.char_name_full) = LOWER(:surface)
                            THEN 'CHARACTER_FULL_NAME'

                        WHEN LOWER(c.search_name) = LOWER(:surface)
                            THEN 'CHARACTER_SEARCH_NAME'

                        WHEN :allow_search_token_match = 1
                            AND CONCAT(' ', LOWER(c.search_name), ' ')
                                LIKE CONCAT('% ', LOWER(:surface), ' %')
                            THEN 'CHARACTER_SEARCH_NAME'

                        WHEN LOWER(c.char_name_first) = LOWER(:surface)
                            THEN 'CHARACTER_FIRST_NAME'

                        WHEN LOWER(c.char_name_last) = LOWER(:surface)
                            THEN 'CHARACTER_LAST_NAME'

                        ELSE 'CHARACTER_REGISTRY_SURFACE'
                    END AS matched_surface_type,

                    CASE
                        WHEN LOWER(c.char_name_full) = LOWER(:surface)
                            THEN c.char_name_full

                        WHEN LOWER(c.search_name) = LOWER(:surface)
                            THEN c.search_name

                        WHEN :allow_search_token_match = 1
                            AND CONCAT(' ', LOWER(c.search_name), ' ')
                                LIKE CONCAT('% ', LOWER(:surface), ' %')
                            THEN c.search_name

                        WHEN LOWER(c.char_name_first) = LOWER(:surface)
                            THEN c.char_name_first

                        WHEN LOWER(c.char_name_last) = LOWER(:surface)
                            THEN c.char_name_last

                        ELSE :surface
                    END AS matched_surface

                FROM characters c
                INNER JOIN entities e
                    ON e.id = c.entity_id

                WHERE e.entity_type_id = 'entity_type_character'
                  AND c.entity_id IS NOT NULL
                  AND TRIM(c.entity_id) <> ''
                  AND (
                      LOWER(c.char_name_full) = LOWER(:surface)

                      OR LOWER(c.search_name) = LOWER(:surface)

                      OR (
                          :allow_search_token_match = 1
                          AND CONCAT(' ', LOWER(c.search_name), ' ')
                              LIKE CONCAT('% ', LOWER(:surface), ' %')
                      )

                      OR LOWER(c.char_name_first) = LOWER(:surface)

                      OR LOWER(c.char_name_last) = LOWER(:surface)
                  )

                LIMIT 100
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':surface' => $surface,
                ':allow_search_token_match'
                    => $allowSearchTokenMatch ? 1 : 0,
            ]);

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

                $matchedSurfaceType = trim((string)(
                    $row['matched_surface_type'] ?? 'CHARACTER_REGISTRY_SURFACE'
                ));

                $surfaceConfidence = 0.9;

                if (in_array($matchedSurfaceType, [
                    'CHARACTER_FULL_NAME',
                    'CHARACTER_SEARCH_NAME',
                ], true)) {
                    $surfaceConfidence = 1.0;
                } elseif ($matchedSurfaceType === 'CHARACTER_FIRST_NAME') {
                    $surfaceConfidence = 0.9;
                } elseif ($matchedSurfaceType === 'CHARACTER_LAST_NAME') {
                    $surfaceConfidence = 0.88;
                }

                $candidateLabel = trim((string)(
                    $row['char_name_full']
                    ?? $row['matched_surface']
                    ?? $surface
                ));

                $candidates[] = [
                    'entity_id' => $entityId,
                    'entity_type_id' => $row['entity_type_id'] ?? null,
                    'matched_surface' => $row['matched_surface'] ?? $surface,
                    'matched_surface_type' => $matchedSurfaceType,
                    'matched_lookup_surface' => $normalizedSurface,
                    'candidate_label' => $candidateLabel !== '' ? $candidateLabel : $surface,
                    'surface_confidence' => $surfaceConfidence,
                ];
            }
        } catch (Throwable $e) {
            /*
             * Surface provider must fail soft.
             * Arbitration/resolvers decide downstream behavior.
             */
        }
    }

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

            $alreadyPresent = false;

            foreach ($candidates as $candidate) {
                if (
                    ($candidate['entity_id'] ?? null) === $entityId
                    && in_array((string)($candidate['matched_surface_type'] ?? ''), [
                        'CHARACTER_FULL_NAME',
                        'CHARACTER_SEARCH_NAME',
                        'CANONICAL_LABEL',
                    ], true)
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
