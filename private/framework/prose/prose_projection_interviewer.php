<?php
declare(strict_types=1);

/**
 * Projection is resolved through interview.
 *
 * Projection is not required authorial payload.
 * Projection is not guessed silently.
 *
 * This layer converts conversational placement intent
 * into a concrete projection payload suitable for
 * create_prose_draft().
 */

function begin_prose_projection_interview(): array
{
    return [
        'needs_interview' => true,

        'questions' => [
            [
                'field' => 'projection_kind',

                'question' => 'Where does this prose belong?',

                'options' => [
                    [
                        'id' => 'book',
                        'label' => 'Book',
                    ],
                    [
                        'id' => 'dream_journal',
                        'label' => 'Dream journal',
                    ],
                    [
                        'id' => 'standalone_note',
                        'label' => 'Standalone note',
                    ],
                ],
            ],
        ],
    ];
}

/**
 * Resolve interview answers into a concrete
 * projection payload.
 *
 * Current implementation is intentionally explicit.
 * No silent inference.
 */

function resolve_prose_projection_payload(
    array $answers
): array {

    $projectionKind = $answers['projection_kind'] ?? null;

    if (!is_string($projectionKind) || trim($projectionKind) === '') {
        throw new InvalidArgumentException(
            'projection_kind must be supplied'
        );
    }

    switch ($projectionKind) {

        case 'book':

            $targetEntityId = $answers['target_entity_id'] ?? null;

            if (
                !is_string($targetEntityId)
                || trim($targetEntityId) === ''
            ) {
                throw new InvalidArgumentException(
                    'target_entity_id is required for book projection'
                );
            }

            return [
                'projection_classval_id'
                => 'projection_type_book',

                'projection_type_id'
                => 'book1',

                'target_entity_id'
                => trim($targetEntityId),

                'role_id'
                => 'projection_role_primary',

                'projection_order'
                => 1,
            ];

        case 'dream_journal':

            $targetEntityId = $answers['target_entity_id'] ?? null;

            if (
                !is_string($targetEntityId)
                || trim($targetEntityId) === ''
            ) {
                throw new InvalidArgumentException(
                    'target_entity_id is required for dream_journal projection'
                );
            }

            return [
                'projection_classval_id'
                => 'projection_type_dream_journal',

                'projection_type_id'
                => 'dream_journal1',

                'target_entity_id'
                => trim($targetEntityId),

                'role_id'
                => 'projection_role_primary',

                'projection_order'
                => 1,
            ];

        case 'standalone_note':

            $targetEntityId = $answers['target_entity_id'] ?? null;

            if (
                !is_string($targetEntityId)
                || trim($targetEntityId) === ''
            ) {
                throw new InvalidArgumentException(
                    'target_entity_id is required for standalone_note projection'
                );
            }

            return [
                'projection_classval_id'
                => 'projection_type_note',

                'projection_type_id'
                => 'note1',

                'target_entity_id'
                => trim($targetEntityId),

                'role_id'
                => 'projection_role_primary',

                'projection_order'
                => 1,
            ];
    }

    throw new InvalidArgumentException(
        'Unsupported projection_kind: ' . $projectionKind
    );
}