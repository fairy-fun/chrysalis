<?php

declare(strict_types=1);

/**
 * Canonical export-context persistence layer.
 *
 * Responsibilities:
 *
 * - prose_export_contexts persistence
 * - prose_export_context_projections persistence
 *
 * Explicitly NOT responsible for:
 *
 * - projection creation
 * - prose draft creation
 * - projection topology validation
 * - export resolution
 *
 * Architectural boundary:
 *
 * projection existence
 *     !=
 * export participation
 */

/**
 * Insert export context row.
 */
function insert_prose_export_context(
    PDO $pdo,
    string $exportContextKey,
    string $exportTypeId,
    ?string $label = null,
    ?int $exportOrder = null,
): int {

    $exportContextKey = trim($exportContextKey);
    $exportTypeId = trim($exportTypeId);
    $label = $label !== null
        ? trim($label)
        : null;

    if ($exportContextKey === '') {
        throw new InvalidArgumentException(
            'exportContextKey must be non-empty'
        );
    }

    if ($exportTypeId === '') {
        throw new InvalidArgumentException(
            'exportTypeId must be non-empty'
        );
    }

    if ($exportOrder !== null && $exportOrder < 1) {
        throw new InvalidArgumentException(
            'exportOrder must be null or positive'
        );
    }

    $stmt = $pdo->prepare("
        INSERT INTO sxnzlfun_chrysalis.prose_export_contexts (
            export_context_key,
            export_type_id,
            label,
            created_at
        ) VALUES (
            :export_context_key,
            :export_type_id,
            :label,
            NOW()
        )
    ");

    try {

        $stmt->execute([
            ':export_context_key' => $exportContextKey,
            ':export_type_id' => $exportTypeId,
            ':label' => $label,
            ':export_order' => $exportOrder,
        ]);

    } catch (PDOException $e) {

        if (
            isset($e->errorInfo[1])
            && (int) $e->errorInfo[1] === 1062
        ) {
            throw new RuntimeException(
                'Duplicate prose export context: '
                . $exportContextKey
            );
        }

        throw $e;
    }

    $id = (int) $pdo->lastInsertId();

    if ($id < 1) {
        throw new RuntimeException(
            'Failed to resolve prose export context ID'
        );
    }

    return $id;
}

/**
 * Attach projection to export context.
 *
 * This models export membership only.
 *
 * It intentionally does not mutate projection topology.
 */
function attach_projection_to_export_context(
    PDO $pdo,
    int $proseExportContextId,
    int $proseProjectionId,
): void {

    if ($proseExportContextId < 1) {
        throw new InvalidArgumentException(
            'proseExportContextId must be positive'
        );
    }

    if ($proseProjectionId < 1) {
        throw new InvalidArgumentException(
            'proseProjectionId must be positive'
        );
    }


    $stmt = $pdo->prepare("
        INSERT INTO sxnzlfun_chrysalis.prose_export_context_projections (
            export_context_id,
            prose_projection_id,
            export_order,
            created_at
        ) VALUES (
            :export_context_id,
            :prose_projection_id,
            :export_order,
            NOW()
        )
    ");

    try {

        $stmt->execute([
            ':export_context_id' => $proseExportContextId,
            ':prose_projection_id' => $proseProjectionId,
        ]);

    } catch (PDOException $e) {

        if (
            isset($e->errorInfo[1])
            && (int) $e->errorInfo[1] === 1062
        ) {
            throw new RuntimeException(
                'Projection already attached to export context'
            );
        }

        throw $e;
    }
}