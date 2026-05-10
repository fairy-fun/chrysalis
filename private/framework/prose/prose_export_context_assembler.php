<?php

declare(strict_types=1);

require_once __DIR__ . '/prose_projection_writer.php';
require_once __DIR__ . '/prose_export_context_writer.php';

/**
 * Export-context orchestration layer.
 *
 * projection existence
 *     !=
 * export participation
 *
 * prose_projections
 *     = projection topology + publication pointer
 *
 * prose_export_contexts
 *     = export assembly identity
 *
 * prose_export_context_projections
 *     = projection membership + ordering
 */

/**
 * Create an export context, create projections, and persist membership.
 *
 * $projectionDefinitions format:
 *
 * [
 *     [
 *         'prose_family_id' => 123,
 *         'published_prose_draft_id' => 456,
 *         'projection_classval_id' => '...',
 *         'projection_type_id' => '...',
 *         'target_entity_id' => '...',
 *         'role_id' => '...',
 *         'projection_order' => 1,
 *         'export_order' => 1,
 *     ],
 * ]
 */
function assemble_prose_export_context(
    PDO $pdo,
    string $exportContextKey,
    string $exportTypeId,
    ?string $label,
    array $projectionDefinitions,
): int {

    if ($projectionDefinitions === []) {
        throw new InvalidArgumentException(
            'projectionDefinitions must be non-empty'
        );
    }

    $pdo->beginTransaction();

    try {

        $exportContextId = write_prose_export_context(
            $pdo,
            $exportContextKey,
            $exportTypeId,
            $label,
        );

        foreach ($projectionDefinitions as $definition) {

            if (!is_array($definition)) {
                throw new InvalidArgumentException(
                    'Each projection definition must be an array'
                );
            }

            foreach ([
                         'prose_family_id',
                         'published_prose_draft_id',
                         'projection_classval_id',
                         'projection_type_id',
                         'target_entity_id',
                         'role_id',
                     ] as $requiredKey) {
                if (!array_key_exists($requiredKey, $definition)) {
                    throw new InvalidArgumentException(
                        'Missing ' . $requiredKey
                    );
                }
            }

            $projectionOrder = null;

            if (array_key_exists('projection_order', $definition)) {
                $projectionOrder = $definition['projection_order'];

                if ($projectionOrder !== null) {
                    $projectionOrder = (int) $projectionOrder;
                }
            }

            $exportOrder = null;

            if (array_key_exists('export_order', $definition)) {
                $exportOrder = $definition['export_order'];

                if ($exportOrder !== null) {
                    $exportOrder = (int) $exportOrder;
                }
            }

            $projectionId = insert_prose_projection(
                $pdo,
                (int) $definition['prose_family_id'],
                (int) $definition['published_prose_draft_id'],
                (string) $definition['projection_classval_id'],
                (string) $definition['projection_type_id'],
                (string) $definition['target_entity_id'],
                (string) $definition['role_id'],
                $projectionOrder,
            );

            write_prose_export_context_projection_membership(
                $pdo,
                $exportContextId,
                $projectionId,
                $exportOrder,
            );
        }

        $pdo->commit();

        return $exportContextId;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}