<?php

declare(strict_types=1);

function hydrate_calendar_week_prose_item(
    array $row,
    string $layerId,
    string $summary,
    string $proseBody,
    string $notes
): array {

    return [

        'id'
            => (int)$row['id'],

        'entity_id'
            => (string)$row['entity_id'],

        'layer_id'
            => $layerId,

        'is_subevent'
            => ($layerId === 'calendar_layer_subevent'),

        'calendar_hierarchy_parent_id'
            => $row['parent_event_id'],

        'parent_entity_id'
            => $row['parent_entity_id'],

        'parent_summary'
            => $row['parent_summary'],

        'summary'
            => $summary,

        'ontology' => [
            'beat_type' => [
                'id' => $row['beat_type_id'],
                'code' => $row['beat_type_code'],
                'label' => $row['beat_type_label'],
                'description' => $row['beat_type_description'],
                'classset' => [
                    'id' => $row['beat_classset_id'],
                    'code' => $row['beat_classset_code'],
                    'label' => $row['beat_classset_label'],
                ],
            ],
            'class_type' => [
                'id' => $row['class_type_id'],
                'code' => $row['class_type_code'],
                'label' => $row['class_type_label'],
                'requires_member'
                    => isset($row['class_type_requires_member'])
                        ? (int)$row['class_type_requires_member']
                        : null,
                'requires_character'
                    => isset($row['class_type_requires_character'])
                        ? (int)$row['class_type_requires_character']
                        : null,
            ],
        ],

        'beat_type_id'
            => $row['beat_type_id'],

        'beat_type_label'
            => $row['beat_type_label'],

        'beat_classset_id'
            => $row['beat_classset_id'],

        'beat_classset_label'
            => $row['beat_classset_label'],

        'class_type_id'
            => $row['class_type_id'],

        'class_type_label'
            => $row['class_type_label'],

        'chronology_address'
            => $row['chronology_address'],

        'real_date_start_id'
            => $row['real_date_start_id'],

        'effective_day_id'
            => (string)$row['effective_day_id'],

        'book_time_id'
            => $row['book_time_id'],

        'effective_book_time_id'
            => $row['effective_book_time_id'],

        'event_index'
            => $row['event_index'],

        'effective_event_index'
            => $row['effective_event_index'],

        'subevent_index'
            => $row['subevent_index'],

        'sequence_index'
            => $row['sequence_index'],

        'reference_label'
            => (string)($row['reference_label'] ?? ''),

        'prose_body'
            => $proseBody,

        'notes'
            => $notes,
    ];
}