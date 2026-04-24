<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function api_error(int $statusCode, string $message): never
{
    http_response_code($statusCode);
    echo json_encode([
        'status' => 'error',
        'error' => $message,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function read_request_body(): array
{
    if (array_key_exists('_API_BODY', $GLOBALS) && is_array($GLOBALS['_API_BODY'])) {
        return $GLOBALS['_API_BODY'];
    }

    $raw = function_exists('ci_get_php_input')
        ? ci_get_php_input()
        : file_get_contents('php://input');

    if ($raw === false) {
        api_error(400, 'Unable to read request body');
    }

    $body = json_decode($raw, true);
    if (!is_array($body)) {
        api_error(400, 'Invalid JSON body');
    }

    $GLOBALS['_API_BODY'] = $body;
    $GLOBALS['_QUERY_BODY'] = $body;

    return $body;
}

$body = read_request_body();

$operation = $body['operation'] ?? $body['endpoint'] ?? '';
if (!is_string($operation) || $operation === '') {
    api_error(400, 'Missing operation');
}

$GLOBALS['_API_BODY'] = $body;
$GLOBALS['_QUERY_BODY'] = $body;

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

    case 'resolveEntityMeasurements':
        require __DIR__ . '/entity/resolve_entity_measurements.php';
        break;

    case 'resolveCharacterExpressionOutput':
        require __DIR__ . '/expression/resolve_character_expression_output.php';
        break;

    case 'createCalendarWeek':
        require __DIR__ . '/calendar/create_calendar_week.php';
        break;

    default:
        api_error(400, 'Unknown operation: ' . $operation);
}


