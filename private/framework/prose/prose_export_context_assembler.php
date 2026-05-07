<?php

declare(strict_types=1);

require_once __DIR__ . '/prose_export_context_writer.php';

/**
 * Export-context orchestration layer.
 *
 * projection existence
 *     !=
 * export participation
 */

/**
 * Create export context and attach existing projections.
 *
 * $projectionAttachments format:
 *
 * [
 *     [
 *         'prose_projection_id' => 123,
 *         'export_order' => 1,
 *     ],
 * ]
 */
function assemble_prose_export_context(
    PDO $pdo,
    string $exportContextKey,
    string $exportTypeId,
    ?string $label,
    array $projectionAttachments,
): int {

    if ($projectionAttachments === []) {
        throw new InvalidArgumentException(
            'projectionAttachments must be non-empty'
        );
    }

    $pdo->beginTransaction();

    try {

        $exportContextId = insert_prose_export_context(
            $pdo,
            $exportContextKey,
            $exportTypeId,
            $label,
        );

        foreach ($projectionAttachments as $attachment) {

            if (!is_array($attachment)) {
                throw new InvalidArgumentException(
                    'Each projection attachment must be an array'
                );
            }

            if (!array_key_exists('prose_projection_id', $attachment)) {
                throw new InvalidArgumentException(
                    'Missing prose_projection_id'
                );
            }

            $proseProjectionId = (int) $attachment['prose_projection_id'];

            $exportOrder = null;

            if (array_key_exists('export_order', $attachment)) {
                $exportOrder = $attachment['export_order'];

                if ($exportOrder !== null) {
                    $exportOrder = (int) $exportOrder;
                }
            }

            attach_projection_to_export_context(
                $pdo,
                $exportContextId,
                $proseProjectionId,
                $exportOrder,
            );
        }

        $pdo->commit();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    return $exportContextId;
}