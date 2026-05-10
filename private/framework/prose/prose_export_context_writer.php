<?php

declare(strict_types=1);

/**
 * Canonical export-context persistence layer.
 *
 * prose_export_contexts
 *     = export assembly identity
 *
 * prose_export_context_projections
 *     = projection membership + ordering
 *
 * Explicitly NOT responsible for:
 *
 * - projection creation
 * - prose draft creation
 * - projection topology validation
 * - export resolution
 */

/**
 * Write export context row.
 */
function write_prose_export_context(
    PDO $pdo,
    string $exportContextKey,
    string $exportTypeId,
    ?string $label = null,
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
 * Write projection membership row for an export context.
 */
function write_prose_export_context_projection_membership(
    PDO $pdo,
    int $proseExportContextId,
    int $proseProjectionId,
    ?int $exportOrder = null,
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

    if ($exportOrder !== null && $exportOrder < 1) {
        throw new InvalidArgumentException(
            'exportOrder must be null or positive'
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
            ':export_order' => $exportOrder,
        ]);

    } catch (PDOException $e) {

        if (
            isset($e->errorInfo[1])
            && (int) $e->errorInfo[1] === 1062
        ) {
            throw new RuntimeException(
                'Projection already belongs to export context'
            );
        }

        throw $e;
    }
}