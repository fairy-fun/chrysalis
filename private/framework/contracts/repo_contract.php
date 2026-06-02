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
        'startWorkflowChat' => [
            'handler'
            => 'public_html/pecherie/chill-api/prose/start_workflow_chat.php',
            'visibility' => 'public',
            'writes' => false,
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
        'createCalendarDay' => [
            'handler' => 'public_html/pecherie/chill-api/calendar/create_calendar_day.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'createCalendarTime' => [
            'handler' => 'public_html/pecherie/chill-api/calendar/create_calendar_time.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'createCalendarEvent' => [
            'handler' => 'public_html/pecherie/chill-api/calendar/create_calendar_event.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'createCalendarSubevent' => [
            'handler' => 'public_html/pecherie/chill-api/calendar/create_calendar_subevent.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'getCalendarNodeByChronologyAddress' => [
            'handler' => 'public_html/pecherie/chill-api/calendar/get_calendar_node_by_chronology_address.php',
            'method' => 'GET',
            'path' => '/calendar/get_calendar_node_by_chronology_address.php',
            'description' => 'Look up a calendar node by human-readable chronology address',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'getCalendarTreeForEntity' => [
            'handler' => 'public_html/pecherie/chill-api/calendar/get_calendar_tree_for_entity.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'getCalendarEventsForProjectionSpan' => [
            'handler' => 'public_html/pecherie/chill-api/calendar/get_calendar_events_for_projection_span.php',
            'visibility' => 'public',
            'writes' => false,
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'rebuildCalendarProjections' => [
            'handler' => 'public_html/pecherie/chill-api/calendar/rebuild_calendar_projections.php',
            'visibility' => 'public',
            'writes' => true,
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'createProseDraft' => [
            'handler' => 'public_html/pecherie/chill-api/prose/create_prose_draft.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'addProseAnnotations' => [
            'handler' => 'public_html/pecherie/chill-api/prose/add_prose_annotations.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'getProseAnnotations' => [
            'handler' => 'public_html/pecherie/chill-api/prose/get_prose_annotations.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'getProseTrainingView' => [
            'handler' => 'public_html/pecherie/chill-api/prose/get_prose_training_view.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'resolveProseCalendarTarget' => [
            'handler' => 'public_html/pecherie/chill-api/prose/resolve_prose_calendar_target.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'getEventProse' => [
            'handler' => 'public_html/pecherie/chill-api/prose/get_event_prose.php',
            'visibility' => 'public',
            'writes' => false,
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'createDreamJournalEntry' => [
            'handler' => 'public_html/pecherie/chill-api/dreams/create_dream_journal_entry.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'getDreamJournalEntries' => [
            'handler' => 'public_html/pecherie/chill-api/dreams/get_dream_journal_entries.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'getDreamAnnotations' => [
            'handler' => 'public_html/pecherie/chill-api/dreams/get_dream_annotations.php',
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
        'auditTraversalProjectionIntegrity' => [
            'handler' => 'public_html/pecherie/chill-api/audit/traversal_projection_integrity.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'auditTraversalJoinSemantics' => [
            'handler' => 'public_html/pecherie/chill-api/audit/traversal_join_semantics.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'auditTraversalOptionalityIntegrity' => [
            'handler' => 'public_html/pecherie/chill-api/audit/traversal_optionality_integrity.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],

        'listTeamRoles' => [
            'handler' => 'public_html/pecherie/chill-api/reference/list_team_roles.php',
            'method' => 'GET',
            'path' => '/reference/list_team_roles.php',
            'description' => 'List team roles',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],

        'resolveSongArtistPair' => [
            'handler' => 'public_html/pecherie/chill-api/music/resolve_song_artist_pair.php',
            'behaviour_tested' => false,
        ],

        'runExpressionPipeline' => [
            'handler' => 'public_html/pecherie/chill-api/expression/run_expression_pipeline.php',
            'behaviour_tested' => false,
        ],

        'applyEntityEventThemeLink' => [
            'handler' => 'public_html/pecherie/chill-api/expression/apply_entity_event_theme_link.php',
            'behaviour_tested' => false,
        ],

        'applyLimbicFact' => [
            'handler' => 'public_html/pecherie/chill-api/limbic/apply_limbic_fact.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],

        'applyGovernedGlobalFact' => [
            'handler'
            => 'public_html/pecherie/chill-api/facts/apply_governed_global_fact.php',
            'visibility' => 'public',
            'writes' => true,
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],

        'applyGovernedEventFact' => [
            'handler'
            => 'public_html/pecherie/chill-api/facts/apply_governed_event_fact.php',
            'visibility' => 'public',
            'writes' => true,
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],

        'suggestEntityEventThemeLink' => [
            'handler' => 'public_html/pecherie/chill-api/expression/suggest_entity_event_theme_link.php',
            'behaviour_tested' => false,
        ],

        'generateCalendarBatchFromProse' => [
            'handler' => 'public_html/pecherie/chill-api/calendar/generate_calendar_batch_from_prose.php',
            'visibility' => 'public',
            'writes' => false,
            'behaviour_tested' => false,
        ],

        'executeCalendarBatchFromProse' => [
            'handler' => 'public_html/pecherie/chill-api/calendar/execute_calendar_batch_from_prose.php',
            'visibility' => 'public',
            'writes' => true,
            'behaviour_tested' => false,
        ],

        'resolveProseExportText' => [
            'handler' => 'public_html/pecherie/chill-api/prose/resolve_prose_export_text.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],

        'resolveGlobalFact' => [
            'handler'
            => 'public_html/pecherie/chill-api/facts/resolve_global_fact.php',
            'visibility' => 'public',
            'writes' => false,
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],

        'getLatestEventProse' => [
            'handler' => 'public_html/pecherie/chill-api/prose/get_latest_event_prose.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
    ],
];
