<?php

return [
    'workflow_id' => 'calendar_event_title_narrative_ontology',
    'delegates_to' => 'calendar_event_derive_beat_title',

    'initial_context' => [
        'workflow_doctrine' => [
            'semantic_summary_is_not_ontology' => true,
            'beat_type_id_is_canonical_linkage' => true,
            'classsets_are_taxonomy_only' => true,
        ],
    ],
];
