<?php

declare(strict_types=1);

function suggest_entity_event_theme_link(PDO $pdo, ?string $contextId = null): array
{
    $contextId = is_string($contextId) && trim($contextId) !== ''
        ? trim($contextId)
        : null;

    return [
        'proposal_type' => 'entity_event_theme_link',
        'context_id' => $contextId,

        'subject_entity_id' => 'entity_event_123',
        'fact_type_id' => 'fact_type_event_theme',
        'object_entity_id' => 'entity_theme_sacrifice',

        'match_status' => 'placeholder',

        'proposed_action' => [
            'table' => 'entity_linked_facts',
            'operation' => 'insert_if_missing',
            'conflict_check' => [
                'subject_entity_id',
                'fact_type_id',
                'object_entity_id',
            ],
        ],

        'sql_text' => null,
    ];
}