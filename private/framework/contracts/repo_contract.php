<?php
declare(strict_types=1);

const FW_AUDIT_ENTRYPOINT = 'private/framework/contracts/chrysalis_hydration_prompt.md';

const FW_REPO_CONTRACT = [
    'audit_entrypoint' => FW_AUDIT_ENTRYPOINT,
    'doctrine_owner' => 'php',
    'db_role' => 'transport_only',
    'ci_role' => 'enforcement_authority',

    'api_operations' => [
        'listRepo' => [
            'handler' => 'public_html/pecherie/chill-api/repo/list_repo.php',
            'behaviour_tested' => true,
            'audit_visibility_required' => true,
        ],
        'getRepoFile' => [
            'handler' => 'public_html/pecherie/chill-api/repo/get_repo_file.php',
            'behaviour_tested' => true,
            'audit_visibility_required' => true,
        ],
        'query' => [
            'handler' => 'public_html/pecherie/chill-api/query.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'executeSqlRead' => [
            'handler' => 'public_html/pecherie/chill-api/query.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'tables' => [
            'handler' => 'public_html/pecherie/chill-api/tables.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'columns' => [
            'handler' => 'public_html/pecherie/chill-api/columns.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'resolveMedleyCore' => [
            'handler' => 'public_html/pecherie/chill-api/choreography/resolve_medley_core.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'runChoreography' => [
            'handler' => 'public_html/pecherie/chill-api/choreography/run_choreography.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'suggestLinkEntity' => [
            'handler' => 'public_html/pecherie/chill-api/entity/suggest_link_entity.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'auditTraversalTriggerIntegrity' => [
            'handler' => 'public_html/pecherie/chill-api/audit/traversal_trigger_integrity.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'auditEventGraphIdentity' => [
            'handler' => 'public_html/pecherie/chill-api/audit/event_graph_identity.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'resolveEntityTraversal' => [
            'handler' => 'public_html/pecherie/chill-api/entity/resolve_entity_traversal.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'resolveEntityMeasurements' => [
            'handler' => 'public_html/pecherie/chill-api/entity/resolve_entity_measurements.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'resolveCharacterExpressionOutput' => [
            'handler' => 'public_html/pecherie/chill-api/expression/resolve_character_expression_output.php',
            'behaviour_tested' => true,
            'audit_visibility_required' => true,
        ],
        'createCalendarWeek' => [
            'handler' => 'public_html/pecherie/chill-api/calendar/create_calendar_week.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'listYears' => [
            'handler' => 'public_html/pecherie/chill-api/reference/list_years.php',
            'method' => 'GET',
            'path' => '/reference/list_years.php',
            'description' => 'List available years',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'listChoreographyTypes' => [
            'handler' => 'public_html/pecherie/chill-api/reference/list_choreography_types.php',
            'method' => 'GET',
            'path' => '/reference/list_choreography_types.php',
            'description' => 'List choreography types',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],

        'listRoutineStatuses' => [
            'handler' => 'public_html/pecherie/chill-api/reference/list_routine_statuses.php',
            'method' => 'GET',
            'path' => '/reference/list_routine_statuses.php',
            'description' => 'List routine statuses',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],

        'executeEntityTraversal' => [
            'handler' => 'public_html/pecherie/chill-api/entity/execute_entity_traversal.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],

        'auditTraversalStepChainIntegrity' => [
            'handler' => 'public_html/pecherie/chill-api/audit/traversal_step_chain_integrity.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
    ],
];
