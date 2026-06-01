<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../private/framework/api/api_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = getJsonBody();

$operation = $body['operation'] ?? $body['endpoint'] ?? null;

/**
 * GPT Actions do not automatically copy the OpenAPI operationId into the JSON body.
 * For the natural-language workflow chat entrypoint, infer the router operation from
 * the presence of a chat message when no explicit operation/endpoint is supplied.
 */
if (($operation === null || $operation === '') && isset($body['message'])) {
    $operation = 'startWorkflowChat';
}

if (!is_string($operation) || trim($operation) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'Missing operation',
    ]);
}

$operation = trim($operation);

$GLOBALS['_API_BODY'] = $body;
$GLOBALS['_QUERY_BODY'] = $body;

$visibility = require __DIR__ . '/../../../private/framework/contracts/repo_visibility.php';

$requiredOperations = $visibility['required_operations'] ?? [];
$handlerPath = $requiredOperations[$operation] ?? null;

if (
    is_string($handlerPath)
    && str_starts_with($handlerPath, 'public_html/pecherie/chill-api/reference/')
) {
    require dirname(__DIR__, 3) . '/' . $handlerPath;
    exit;
}

switch ($operation) {
    case 'listRepo':
        require __DIR__ . '/repo/list_repo.php';
        break;

    case 'getRepoFile':
        require __DIR__ . '/repo/get_repo_file.php';
        break;

    case 'executeSqlRead':
        require __DIR__ . '/query.php';
        break;

    case 'tables':
        require __DIR__ . '/tables.php';
        break;

    case 'columns':
        require __DIR__ . '/columns.php';
        break;

    case 'query':
        require __DIR__ . '/query.php';
        break;

    case 'resolveMedleyCore':
        require __DIR__ . '/choreography/resolve_medley_core.php';
        break;

    case 'runChoreography':
        require __DIR__ . '/choreography/run_choreography.php';
        break;

    case 'suggestLinkEntity':
        require __DIR__ . '/entity/suggest_link_entity.php';
        break;

    case 'auditTraversalTriggerIntegrity':
        require __DIR__ . '/audit/traversal_trigger_integrity.php';
        break;

    case 'auditEventGraphIdentity':
        require __DIR__ . '/audit/event_graph_identity.php';
        break;

    case 'resolveEntityTraversal':
        require __DIR__ . '/entity/resolve_entity_traversal.php';
        break;

    case 'executeEntityTraversal':
        require __DIR__ . '/entity/execute_entity_traversal.php';
        break;

    case 'resolveEntityMeasurements':
        require __DIR__ . '/entity/resolve_entity_measurements.php';
        break;

    case 'resolveCharacterExpressionOutput':
        require __DIR__ . '/expression/resolve_character_expression_output.php';
        break;

    case 'createCalendarWeek':
        require __DIR__ . '/calendar/create_calendar_week.php';
        break;

    case 'createCalendarDay':
        require __DIR__ . '/calendar/create_calendar_day.php';
        break;

    case 'createCalendarTime':
        require __DIR__ . '/calendar/create_calendar_time.php';
        break;

    case 'createCalendarEvent':
        require __DIR__ . '/calendar/create_calendar_event.php';
        break;

    case 'createCalendarSubevent':
        require __DIR__ . '/calendar/create_calendar_subevent.php';
        break;

    case 'getCalendarNodeByChronologyAddress':
        require __DIR__ . '/calendar/get_calendar_node_by_chronology_address.php';
        break;

    case 'getCalendarTreeForEntity':
        require __DIR__ . '/calendar/get_calendar_tree_for_entity.php';
        break;

    case 'getCalendarEventsForProjectionSpan':
        require __DIR__ . '/calendar/get_calendar_events_for_projection_span.php';
        break;

    case 'createProseDraft':
        require __DIR__ . '/prose/create_prose_draft.php';
        break;

    case 'resolveProseCalendarTarget':
        require __DIR__ . '/prose/resolve_prose_calendar_target.php';
        break;

    case 'getProseAnnotations':
        require __DIR__ . '/prose/get_prose_annotations.php';
        break;

    case 'getProseTrainingView':
        require __DIR__ . '/prose/get_prose_training_view.php';
        break;

    case 'addProseAnnotations':
        require __DIR__ . '/prose/add_prose_annotations.php';
        break;

    case 'generateCalendarBatchFromProse':
        require __DIR__ . '/calendar/generate_calendar_batch_from_prose.php';
        break;

    case 'executeCalendarBatchFromProse':
        require __DIR__ . '/calendar/execute_calendar_batch_from_prose.php';
        break;

    case 'auditTraversalStepChainIntegrity':
        require __DIR__ . '/audit/traversal_step_chain_integrity.php';
        break;

    case 'auditTraversalProjectionIntegrity':
        require __DIR__ . '/audit/traversal_projection_integrity.php';
        break;

    case 'auditTraversalJoinSemantics':
        require __DIR__ . '/audit/traversal_join_semantics.php';
        break;

    case 'auditTraversalOptionalityIntegrity':
        require __DIR__ . '/audit/traversal_optionality_integrity.php';
        break;

    case 'applyGovernedGlobalFact':
        require __DIR__ . '/facts/apply_governed_global_fact.php';
        exit;

    case 'applyGovernedEventFact':
        require __DIR__ . '/facts/apply_governed_event_fact.php';
        exit;

    case 'resolveGlobalFact':
        require __DIR__ . '/facts/resolve_global_fact.php';
        exit;

    case 'resolveSongArtistPair':
        require __DIR__ . '/music/resolve_song_artist_pair.php';
        break;

    case 'runExpressionPipeline':
        require __DIR__ . '/expression/run_expression_pipeline.php';
        break;

    case 'suggestEntityEventThemeLink':
        require __DIR__ . '/expression/suggest_entity_event_theme_link.php';
        break;

    case 'applyEntityEventThemeLink':
        require __DIR__ . '/expression/apply_entity_event_theme_link.php';
        break;

    case 'applyLimbicFact':
        require __DIR__ . '/limbic/apply_limbic_fact.php';
        break;

    case 'createDreamJournalEntry':
        require __DIR__ . '/dreams/create_dream_journal_entry.php';
        break;

    case 'getDreamJournalEntries':
        require __DIR__ . '/dreams/get_dream_journal_entries.php';
        break;

    case 'getDreamAnnotations':
        require __DIR__ . '/dreams/get_dream_annotations.php';
        break;

    case 'startWorkflowChat':
        require __DIR__ . '/prose/start_workflow_chat.php';
        break;

    case 'resolveProseExportText':
        require __DIR__ . '/prose/resolve_prose_export_text.php';
        break;

    case 'getLatestEventProse':
        require __DIR__ . '/prose/get_latest_event_prose.php';
        break;

    case 'getEventProse':
        require __DIR__ . '/prose/get_event_prose.php';
        break;

    default:
        respond(400, [
            'status' => 'error',
            'error' => 'Unknown operation: ' . $operation,
        ]);
}
