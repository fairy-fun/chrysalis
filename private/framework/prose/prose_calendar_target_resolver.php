<?php

declare(strict_types=1);

function resolve_prose_target_calendar_node(
    PDO $pdo,
    string $proseEntityId,
    ?string $projectionTypeId = null,
    ?string $roleId = null,
    bool $requireExportTarget = true
): ?array {
    // --- Validate input ---
    if (strpos($proseEntityId, 'prose_draft:') !== 0) {
        throw new InvalidArgumentException('Invalid prose_entity_id format');
    }

    // --- Base query ---
    $sql = "
        SELECT
            pd.id AS prose_draft_id,
            pd.entity_id AS prose_entity_id,

            pp.id AS projection_id,
            pp.projection_type_id,
            pp.role_id,
            pp.is_export_target,
            pp.target_entity_id,

            ce.id AS calendar_event_row_id,
            ce.event_id,
            ce.entity_id AS calendar_entity_id,
            ce.parent_event_id,
            ce.layer_id,
            ce.sequence_index,
            ce.summary,
            ce.created_at,
            ce.updated_at,

            e.entity_type_id

        FROM prose_drafts pd
        JOIN prose_projections pp
            ON pp.prose_family_id = pd.prose_family_id
               AND pp.published_prose_draft_id = pd.id

        JOIN calendar_events ce
            ON ce.entity_id = pp.target_entity_id

        JOIN entities e
            ON e.id = ce.entity_id

        WHERE pd.entity_id = :prose_entity_id
    ";

    $params = [
        ':prose_entity_id' => $proseEntityId
    ];

    // --- Optional filters ---
    if ($projectionTypeId !== null) {
        $sql .= " AND pp.projection_type_id = :projection_type_id";
        $params[':projection_type_id'] = $projectionTypeId;
    }

    if ($roleId !== null) {
        $sql .= " AND pp.role_id = :role_id";
        $params[':role_id'] = $roleId;
    }

    if ($requireExportTarget) {
        $sql .= " AND pp.is_export_target = 1";
    }

    $sql .= " ORDER BY pp.projection_order ASC LIMIT 2";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) === 0) {
        return null;
    }

    if (count($rows) > 1) {
        throw new RuntimeException(
            'Ambiguous projection: multiple calendar targets match prose_entity_id=' . $proseEntityId
        );
    }

    $row = $rows[0];

    // ✅ Phase 14: semantic assertion
    if (!isset($row['entity_type_id']) || !is_string($row['entity_type_id'])) {
        throw new RuntimeException('Missing entity_type_id for resolved calendar node');
    }

    /*
     * Phase 14:
     * entity_type_id is authoritative.
     * layer_id must agree with the expected structural mapping.
     */
        $expectedLayerMap = [
            'entity_type_calendar_week'  => 'calendar_layer_week',
            'entity_type_calendar_day'   => 'calendar_layer_day',
            'entity_type_calendar_time'  => 'calendar_layer_time',
            'entity_type_calendar_event' => 'calendar_layer_event',
        ];

        $entityTypeId = $row['entity_type_id'];

        if (!isset($expectedLayerMap[$entityTypeId])) {
            throw new RuntimeException(
                'Invalid projection target: unsupported calendar entity_type_id ' . $entityTypeId
            );
        }

        $expectedLayerId = $expectedLayerMap[$entityTypeId];

        if (($row['layer_id'] ?? null) !== $expectedLayerId) {
            throw new RuntimeException(
                'Calendar node mismatch: entity_type_id ' . $entityTypeId .
                ' must map to ' . $expectedLayerId .
                ', got ' . ($row['layer_id'] ?? 'NULL')
            );
        }

    return $row;
}