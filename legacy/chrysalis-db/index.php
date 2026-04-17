<?php
set_time_limit(180);
define('STORAGE_DIR', dirname(__DIR__, 2) . '/storage/chrysalis-db');
define('PROMPT_FILE', STORAGE_DIR . '/prompts/system_prompt.txt');
define('SCHEMA_FILE', STORAGE_DIR . '/cache/schema_cache.txt');

if (!is_dir(dirname(PROMPT_FILE))) {
    mkdir(dirname(PROMPT_FILE), 0777, true);
}

if (!is_dir(dirname(SCHEMA_FILE))) {
    mkdir(dirname(SCHEMA_FILE), 0777, true);
}

function getConfig(): array {
	$config = require dirname(__DIR__, 2) . '/config/bootstrap.php';
    if (!is_array($config)) { http_response_code(500); echo json_encode(['error' => 'Invalid config']); exit; }
    return $config;
}


require_once dirname(__DIR__, 2) . '/api/HttpClient.php';


function runSql(string $sql, string $apiKey): array {
    return http_post_json(
        'https://makeyourfairytale.com/pecherie/chill-api/query.php',
        ['sql' => $sql, 'limit' => 200],
        ['x-api-key: ' . $apiKey]
    );
}

function getTables(string $apiKey): array {
    $result = http_get(
        'https://makeyourfairytale.com/pecherie/chill-api/tables.php',
        ['x-api-key: ' . $apiKey]
    );

    if ($result['curl_error']) {
        return [];
    }

    $data = json_decode($result['body'], true);
    if (!is_array($data)) return [];

    $tables = $data['tables'] ?? $data;
    return is_array($tables) ? $tables : [];
}

function getColumns(string $table, string $apiKey): array {
    $result = http_get(
        'https://makeyourfairytale.com/pecherie/chill-api/columns.php?table=' . urlencode($table),
        ['x-api-key: ' . $apiKey]
    );

    if ($result['curl_error']) {
        return [];
    }

    $data = json_decode($result['body'], true);
    return $data['columns'] ?? [];
}

function buildSchemaContext(string $apiKey): string {
    $tables = getTables($apiKey);
    if (empty($tables)) return 'Database: sxnzlfun_chrysalis (schema unavailable).';
    $lines = ['Database: sxnzlfun_chrysalis', 'Schema:'];
    foreach ($tables as $table) {
        $cols = getColumns((string)$table, $apiKey);
        if (empty($cols)) { $lines[] = "  {$table}"; continue; }
        $colParts = [];
        foreach ($cols as $col) {
            $flags = [];
            if ($col['key'] === 'PRI') $flags[] = 'PK';
            if ($col['key'] === 'MUL') $flags[] = 'FK';
            if (!$col['nullable']) $flags[] = 'NOT NULL';
            $colParts[] = $col['name'] . ' ' . $col['type'] . (empty($flags) ? '' : ' [' . implode(', ', $flags) . ']');
        }
        $lines[] = "  {$table}(" . implode(', ', $colParts) . ")";
    }
    return implode("\n", $lines);
}

function getSchemaContext(string $apiKey, bool $force = false): string {
    if (!$force && file_exists(SCHEMA_FILE)) return file_get_contents(SCHEMA_FILE);
    $schema = buildSchemaContext($apiKey);
    file_put_contents(SCHEMA_FILE, $schema);
    return $schema;
}

function callAnthropic(string $system, string $user, string $anthropicKey, int $maxTokens = 1000): array {
    $payload = json_encode([
        'model'      => 'claude-sonnet-4-20250514',
        'max_tokens' => $maxTokens,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $user]],
    ]);
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $anthropicKey,
            'anthropic-version: 2023-06-01',
        ],
    ]);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($curlErr) return ['text' => '', 'error' => 'Anthropic curl error: ' . $curlErr];
    if ($status !== 200) return ['text' => '', 'error' => 'Anthropic API error ' . $status . ': ' . substr($response, 0, 200)];
    $data = json_decode($response, true);
    return ['text' => $data['content'][0]['text'] ?? '', 'error' => ''];
}

function nlToSql(string $nl, string $systemPrompt, string $anthropicKey): array {
    $result = callAnthropic($systemPrompt, $nl, $anthropicKey);
    if ($result['error']) return ['sql' => '', 'error' => $result['error']];
    $text = $result['text'];

    // Try direct JSON decode first
    $decoded = json_decode($text, true);
    if (is_array($decoded) && isset($decoded['sql'])) {
        return ['sql' => trim($decoded['sql']), 'error' => ''];
    }

    // Extract from code fence anywhere in the response
    if (preg_match('/```(?:json|sql)?\s*([\s\S]*?)\s*```/i', $text, $m)) {
        $inner = trim($m[1]);
        $decoded = json_decode($inner, true);
        if (is_array($decoded) && isset($decoded['sql'])) {
            return ['sql' => trim($decoded['sql']), 'error' => ''];
        }
        return ['sql' => $inner, 'error' => ''];
    }

    // Last resort: extract sql value directly from anywhere in the text
    if (preg_match('/\{[\s\S]*"sql"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/i', $text, $m)) {
        return ['sql' => trim(stripslashes($m[1])), 'error' => ''];
    }

    return ['sql' => trim($text), 'error' => ''];
}

function describeSql(string $question, array $rows, string $anthropicKey): array {
    $system = "You are a helpful assistant. The user asked a question about a database. You have been given the query results as JSON. Write a clear, concise natural language description of what the results show, in the context of the user's original question. Be specific and use the actual data. Do not show SQL. Do not use markdown headers. Write in flowing prose.";
    $rowsToDescribe = !empty($rows) ? array_slice($rows, 0, 20) : [];
    $user = "Question: " . $question . "\n\nResults (JSON):\n" . json_encode($rowsToDescribe, JSON_PRETTY_PRINT) . (empty($rowsToDescribe) ? "\n\n(The query returned no rows.)" : "");
    return callAnthropic($system, $user, $anthropicKey, 600);
}

function planQueries(string $scenePrompt, string $anthropicKey): array {
    $system = 'You are a story database planner for CHRYSALIS — a novel about a ballroom dancer named Shay Aurelia Vertue Young (CHAR-MAIN-001) who joins an elite formation team in London to compete at Blackpool.

Your job: given a scene prompt, identify up to 3 natural language database queries that would retrieve the most essential grounding data — character details, appearance, relationships, calendar events, places, choreography.

Return ONLY a JSON array of query strings. No prose, no explanation, no markdown fences. Example:
["Show me Shay\'s appearance profile", "Show me the relationship between Shay and Kai", "Show me the calendar event for Tuesday of Week 1"]

Rules:
- Maximum 3 queries — prioritise ruthlessly
- Only include queries that would return genuinely useful grounding data for the scene
- Prefer specific queries over broad ones
- If the scene is abstract or does not reference specific characters or events, return fewer queries
- Return an empty array [] if no useful queries can be identified';

    $result = callAnthropic($system, $scenePrompt, $anthropicKey, 300);
    if ($result['error']) return [];
    $text = trim($result['text']);

    if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $text, $m)) {
        $text = trim($m[1]);
    }

    $queries = json_decode($text, true);
    if (!is_array($queries)) return [];
    return array_slice($queries, 0, 3);
}

function writeScene(string $scenePrompt, array $dbContext, string $anthropicKey): array {
    $system = 'You are a literary prose writer for CHRYSALIS — a three-book novel series in the Plum Sykes meets Len Deighton tradition: socially sharp, glamorous, and laced with intrigue.

The story follows Shay Aurelia Vertue Young (CHAR-MAIN-001), a ballroom formation Follow who has travelled from Los Angeles to London to escape her past and help her new team win Blackpool. The world is the Royal Ballroom Dance Society (COMP-001). The tone is sharp, observational, and glamorous — think Tatler meets espionage.

You will be given a scene prompt and database results containing verified facts about the story world. Use those facts to ground the prose. Do not invent details that contradict the database. You may invent atmosphere, interiority, and dialogue.

Writing rules:
- Third person limited, Shay\'s POV unless the prompt specifies otherwise
- Present tense unless the prompt specifies otherwise
- No markdown headers or formatting — flowing prose only
- Use physical and sensory detail
- Do not summarise — write the scene
- Length: 200-400 words unless the prompt specifies otherwise';

    $contextBlock = '';
    foreach ($dbContext as $item) {
        if (!empty($item['rows'])) {
            $contextBlock .= "\n\n--- " . $item['nl'] . " ---\n";
            $contextBlock .= json_encode(array_slice($item['rows'], 0, 15), JSON_PRETTY_PRINT);
        }
    }

    $user = "Scene prompt: " . $scenePrompt . ($contextBlock ? "\n\nVerified story data from the database:" . $contextBlock : "");
    return callAnthropic($system, $user, $anthropicKey, 1500);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, x-api-key');
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json; charset=utf-8');

    $config  = getConfig();
    $apiKey  = $_SERVER['HTTP_X_API_KEY'] ?? '';
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $mode    = $body['mode'] ?? 'sql';

    if ($mode === 'save_prompt') {
        file_put_contents(PROMPT_FILE, $body['prompt'] ?? '');
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($mode === 'refresh_schema') {
        $schema = getSchemaContext($apiKey, true);
        echo json_encode(['status' => 'ok', 'cached_at' => date('Y-m-d H:i:s'), 'table_count' => substr_count($schema, "\n  ")]);
        exit;
    }

    if ($mode === 'write') {
        $scenePrompt  = trim($body['prompt'] ?? '');
        $anthropicKey = trim((string)($config['anthropic_api_key'] ?? ''));

        if ($scenePrompt === '')  { echo json_encode(['error' => 'Missing prompt']); exit; }
        if ($anthropicKey === '') { echo json_encode(['error' => 'Anthropic key not configured on server']); exit; }

        $userPrompt      = trim($body['system_prompt'] ?? '');
        $sqlSystemPrompt = $userPrompt ?: "You are a SQL expert for the sxnzlfun_chrysalis database. Return only a single valid SELECT query using fully qualified table names (sxnzlfun_chrysalis.table_name). No explanation, no markdown, just the raw SQL.";

        // Step 1: plan queries
        $plannedQueries = planQueries($scenePrompt, $anthropicKey);

        // Step 2: run each query through NL->SQL pipeline
        $dbContext = [];
        foreach ($plannedQueries as $nlQuery) {
            sleep(3);
            $nlResult = nlToSql($nlQuery, $sqlSystemPrompt, $anthropicKey);
            if ($nlResult['error'] || $nlResult['sql'] === '') continue;
            $sql = $nlResult['sql'];
            if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|SET|CREATE|ALTER|DROP|TRUNCATE|CALL)/i', $sql)) continue;
            $dbResult = runSql($sql, $apiKey);
            if ($dbResult['curl_error']) continue;
            $data = json_decode($dbResult['body'], true);
            if (!is_array($data) || !isset($data['rows'])) continue;
            $dbContext[] = [
                'nl'        => $nlQuery,
                'sql'       => $sql,
                'rows'      => $data['rows'],
                'row_count' => $data['row_count'] ?? count($data['rows']),
            ];
        }

        // Step 3: pause then write
        sleep(3);
        $writeResult = writeScene($scenePrompt, $dbContext, $anthropicKey);
        if ($writeResult['error']) {
            echo json_encode(['error' => $writeResult['error']]);
            exit;
        }

        echo json_encode([
            'prose'   => $writeResult['text'],
            'sources' => array_map(fn($s) => [
                'nl'        => $s['nl'],
                'sql'       => $s['sql'],
                'row_count' => $s['row_count'],
            ], $dbContext),
        ]);
        exit;
    }

    if ($mode === 'nl') {
        $nl           = trim($body['nl'] ?? '');
        $userPrompt   = trim($body['system_prompt'] ?? '');
        $describe     = !empty($body['describe']);
        $anthropicKey = trim((string)($config['anthropic_api_key'] ?? ''));

        if ($nl === '')           { echo json_encode(['error' => 'Missing nl field']); exit; }
        if ($anthropicKey === '') { echo json_encode(['error' => 'Anthropic key not configured on server']); exit; }

        $schemaContext = getSchemaContext($apiKey);
        $basePrompt    = "You are a SQL expert. Return only a single valid SELECT query. No explanation, no markdown, just the raw SQL.\n\n" . $schemaContext;
        $systemPrompt  = $userPrompt ? $userPrompt . "\n\n" . $schemaContext : $basePrompt;

        $nlResult = nlToSql($nl, $systemPrompt, $anthropicKey);
        if ($nlResult['error'] !== '') {
            echo json_encode(['error' => $nlResult['error'], 'generated_sql' => '']);
            exit;
        }
        $sql = $nlResult['sql'];
        if ($sql === '') {
            echo json_encode(['error' => 'AI returned empty SQL', 'generated_sql' => '']);
            exit;
        }

        $isWriteQuery = preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|SET|CREATE|ALTER|DROP|TRUNCATE|CALL)/i', $sql);
        if ($isWriteQuery) {
            echo json_encode([
                'status'        => 'write_only',
                'generated_sql' => $sql,
                'message'       => 'This query modifies data and cannot be run here. Copy the SQL and run it in phpMyAdmin.',
                'rows'          => [],
                'row_count'     => 0,
                'description'   => '',
            ]);
            exit;
        }

        $result = runSql($sql, $apiKey);
        if ($result['curl_error']) {
            echo json_encode(['error' => 'DB curl error: ' . $result['curl_error'], 'generated_sql' => $sql]);
            exit;
        }
        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            echo json_encode(['error' => 'DB returned invalid JSON', 'generated_sql' => $sql, 'raw' => substr((string)$result['body'], 0, 300)]);
            exit;
        }
        if (!isset($data['rows'])) {
            echo json_encode(['error' => 'Unexpected DB response', 'generated_sql' => $sql, 'raw_db_response' => $data]);
            exit;
        }

        $description = '';
        if ($describe) {
            $descResult  = describeSql($nl, $data['rows'], $anthropicKey);
            $description = $descResult['error'] ? '[Description error: ' . $descResult['error'] . ']' : $descResult['text'];
        }

        http_response_code($result['status']);
        echo json_encode(array_merge($data, ['generated_sql' => $sql, 'description' => $description]));

    } else {
        $sql    = trim($body['sql'] ?? '');
        $result = runSql($sql, $apiKey);
        if ($result['curl_error']) {
            echo json_encode(['error' => 'DB curl error: ' . $result['curl_error']]);
            exit;
        }
        http_response_code($result['status']);
        echo $result['body'];
    }
    exit;
}

$savedPrompt    = file_exists(PROMPT_FILE) ? htmlspecialchars(file_get_contents(PROMPT_FILE)) : '';
$schemaCached   = file_exists(SCHEMA_FILE);
$schemaCachedAt = $schemaCached ? date('Y-m-d H:i:s', filemtime(SCHEMA_FILE)) : '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chrysalis DB</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:ital,wght@0,300;0,400;0,500;1,300&family=DM+Sans:wght@300;400;500&family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0e0e0f; --bg2: #161618; --bg3: #1e1e21;
    --border: rgba(255,255,255,0.08); --border2: rgba(255,255,255,0.14);
    --text: #f2f0ec; --text2: #b0ada4; --text3: #7a7770;
    --accent: #c8a96e; --ok: #5a9e72; --err: #b85c5c;
    --mono: 'DM Mono', monospace; --sans: 'DM Sans', sans-serif;
  }
  html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--sans); font-size: 14px; line-height: 1.6; }
  body { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
  header { display: flex; align-items: center; justify-content: space-between; padding: 14px 24px; border-bottom: 1px solid var(--border); flex-shrink: 0; background: var(--bg); }
  .logo { font-family: var(--mono); font-size: 13px; font-weight: 500; color: var(--accent); letter-spacing: 0.08em; text-transform: uppercase; }
  .logo span { color: var(--text3); font-weight: 300; }
  .key-area { display: flex; align-items: center; gap: 10px; }
  .key-label { font-size: 11px; color: var(--text3); letter-spacing: 0.06em; text-transform: uppercase; }
  #api-key { font-family: var(--mono); font-size: 12px; width: 220px; padding: 6px 10px; background: var(--bg3); border: 1px solid var(--border); border-radius: 6px; color: var(--text); outline: none; transition: border-color 0.15s; }
  #api-key:focus { border-color: var(--border2); }
  #api-key::placeholder { color: var(--text3); }
  .status-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--text3); transition: background 0.3s; }
  .status-dot.ok { background: var(--ok); }
  .status-dot.err { background: var(--err); }
  main { flex: 1; overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; scroll-behavior: smooth; }
  main::-webkit-scrollbar { width: 4px; }
  main::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 2px; }
  .empty-state { margin: auto; text-align: center; color: var(--text3); }
  .empty-state .glyph { font-family: var(--mono); font-size: 32px; color: var(--border2); display: block; margin-bottom: 12px; }
  .empty-state p { font-size: 13px; line-height: 1.8; }
  .empty-state code { font-family: var(--mono); font-size: 12px; color: var(--text2); background: var(--bg3); padding: 2px 6px; border-radius: 3px; }
  .msg-user { align-self: flex-end; max-width: 72%; background: var(--bg3); border: 1px solid var(--border); border-radius: 10px 10px 2px 10px; padding: 10px 14px; font-family: var(--mono); font-size: 12px; color: var(--text); white-space: pre-wrap; word-break: break-word; animation: fadeUp 0.15s ease; }
  .msg-result { align-self: flex-start; width: 100%; animation: fadeUp 0.15s ease; }
  @keyframes fadeUp { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
  .result-meta { font-size: 11px; color: var(--text3); margin-bottom: 8px; font-family: var(--mono); letter-spacing: 0.04em; }
  .result-meta .ok { color: var(--ok); }
  .table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; background: var(--bg2); }
  .table-wrap::-webkit-scrollbar { height: 4px; }
  .table-wrap::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 2px; }
  table { width: 100%; border-collapse: collapse; font-family: var(--mono); font-size: 12px; }
  th { background: var(--bg3); color: var(--text2); text-align: left; padding: 7px 12px; border-bottom: 1px solid var(--border); font-weight: 400; white-space: nowrap; letter-spacing: 0.03em; }
  td { padding: 6px 12px; border-bottom: 1px solid var(--border); color: var(--text); white-space: nowrap; max-width: 320px; overflow: hidden; text-overflow: ellipsis; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: var(--bg3); }
  .null-val { color: var(--text3); font-style: italic; }
  .err-box { background: rgba(184,92,92,0.1); border: 1px solid rgba(184,92,92,0.25); border-radius: 8px; padding: 10px 14px; font-family: var(--mono); font-size: 12px; color: #d08080; white-space: pre-wrap; word-break: break-word; }
  .no-rows { font-size: 12px; color: var(--text3); font-family: var(--mono); padding: 10px 0; font-style: italic; }
  footer { flex-shrink: 0; border-top: 1px solid var(--border); padding: 14px 24px; background: var(--bg); }
  .input-row { display: flex; gap: 10px; align-items: flex-end; }
  #sql-input { flex: 1; font-family: var(--mono); font-size: 13px; resize: vertical; min-height: 56px; max-height: 200px; padding: 10px 12px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); outline: none; transition: border-color 0.15s; line-height: 1.6; }
  #sql-input:focus { border-color: var(--border2); }
  #sql-input::placeholder { color: var(--text3); }
  #run-btn { font-family: var(--mono); font-size: 12px; font-weight: 500; letter-spacing: 0.06em; text-transform: uppercase; padding: 0 18px; height: 40px; background: var(--accent); color: #1a1408; border: none; border-radius: 8px; cursor: pointer; white-space: nowrap; transition: background 0.15s, transform 0.1s; }
  #run-btn:hover { background: #d9bb82; }
  #run-btn:active { transform: scale(0.97); }
  #run-btn:disabled { background: var(--bg3); color: var(--text3); cursor: not-allowed; transform: none; }
  .hint { margin-top: 8px; font-size: 11px; color: var(--text3); font-family: var(--mono); }
  .describe-btn { font-family: var(--mono); font-size: 12px; font-weight: 500; letter-spacing: 0.06em; text-transform: uppercase; padding: 0 14px; height: 40px; background: transparent; color: var(--text3); border: 1px solid var(--border); border-radius: 8px; cursor: pointer; white-space: nowrap; transition: all 0.15s; }
  .describe-btn.on { background: var(--bg3); color: var(--accent); border-color: var(--border2); }
  .describe-btn:hover { border-color: var(--border2); color: var(--text2); }
  .nl-description { margin-top: 24px; margin-bottom: 12px; font-size: 18px; font-family: 'Cormorant Garamond', serif; color: #e8c97a; line-height: 1.8; border-left: 2px solid var(--accent); padding-left: 12px; max-width: 60%; margin-left: auto; margin-right: auto; }
  .prose-block { font-size: 17px; font-family: 'Cormorant Garamond', serif; color: var(--text); line-height: 1.9; max-width: 640px; margin: 8px auto 0; white-space: pre-wrap; }
  .sources-toggle { font-family: var(--mono); font-size: 11px; color: var(--text3); background: none; border: none; cursor: pointer; padding: 8px 0 0; letter-spacing: 0.05em; text-transform: uppercase; display: block; }
  .sources-toggle:hover { color: var(--text2); }
  .sources-list { display: none; margin-top: 8px; border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
  .sources-list.open { display: block; }
  .source-item { padding: 8px 12px; border-bottom: 1px solid var(--border); font-family: var(--mono); font-size: 11px; }
  .source-item:last-child { border-bottom: none; }
  .source-nl { color: var(--text2); margin-bottom: 4px; }
  .source-sql { color: var(--text3); white-space: pre-wrap; word-break: break-all; font-size: 10px; }
  .source-count { color: var(--ok); font-size: 10px; margin-top: 2px; }
  .spinner { display: inline-block; width: 10px; height: 10px; border: 1.5px solid var(--border2); border-top-color: var(--text2); border-radius: 50%; animation: spin 0.6s linear infinite; vertical-align: middle; margin-right: 6px; }
  @keyframes spin { to { transform: rotate(360deg); } }
  .mode-toggle { display: flex; gap: 0; border: 1px solid var(--border); border-radius: 6px; overflow: hidden; flex-shrink: 0; }
  .mode-btn { font-family: var(--mono); font-size: 11px; letter-spacing: 0.06em; text-transform: uppercase; padding: 0 12px; height: 32px; background: transparent; color: var(--text3); border: none; cursor: pointer; transition: background 0.15s, color 0.15s; }
  .mode-btn.active { background: var(--bg3); color: var(--accent); }
  .mode-btn:hover:not(.active) { color: var(--text2); }
  .footer-top { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
  #system-prompt-wrap { margin-bottom: 10px; display: none; }
  #system-prompt-wrap.visible { display: block; }
  #system-prompt { width: 100%; font-family: var(--mono); font-size: 12px; resize: vertical; min-height: 80px; max-height: 240px; padding: 8px 10px; background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; color: var(--text2); outline: none; line-height: 1.6; }
  #system-prompt:focus { border-color: var(--border2); color: var(--text); }
  .prompt-toggle { cursor: pointer; color: var(--text3); font-size: 11px; font-family: var(--mono); background: none; border: none; padding: 0; text-transform: uppercase; letter-spacing: 0.05em; }
  .prompt-toggle:hover { color: var(--text2); }
  .generated-sql { font-family: var(--mono); font-size: 11px; color: var(--text3); background: var(--bg3); border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px; margin-bottom: 8px; white-space: pre-wrap; word-break: break-all; }
  .generated-sql-label { font-size: 10px; color: var(--text3); letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 4px; }
</style>
</head>
<body>
<header>
  <div class="logo">chrysalis <span>/ db</span></div>
  <div class="key-area">
    <span class="key-label">key</span>
    <input id="api-key" type="password" placeholder="x-api-key" autocomplete="off" />
    <div class="status-dot" id="status-dot"></div>
  </div>
</header>
<main id="log">
  <div class="empty-state" id="empty">
    <span class="glyph">⬡</span>
    <p>Read-only access to <code>sxnzlfun_chrysalis</code><br>
    Toggle SQL / NL / Write mode below, then run.<br>
    <code>Ctrl+Enter</code> or <code>Cmd+Enter</code> to run.</p>
  </div>
</main>
<footer>
  <div class="footer-top">
    <div class="mode-toggle">
      <button class="mode-btn active" id="btn-sql" onclick="setMode('sql')">SQL</button>
      <button class="mode-btn" id="btn-nl" onclick="setMode('nl')">NL</button>
      <button class="mode-btn" id="btn-write" onclick="setMode('write')">Write</button>
    </div>
    <button class="prompt-toggle" id="prompt-toggle-btn" style="display:none" onclick="togglePrompt()">System prompt &#9660;</button>
  </div>
  <div id="system-prompt-wrap">
    <textarea id="system-prompt" placeholder="Paste your Chrysalis system prompt here — sent with every NL and Write request…"><?php echo $savedPrompt; ?></textarea>
    <div style="display:flex;justify-content:flex-end;margin-top:6px;gap:8px;">
      <span id="prompt-save-status" style="font-family:var(--mono);font-size:11px;color:var(--text3);align-self:center;"></span>
      <span id="schema-status" style="font-family:var(--mono);font-size:11px;color:var(--text3);align-self:center;"><?php echo $schemaCached ? "schema cached " . $schemaCachedAt : "schema not cached"; ?></span>
      <button onclick="savePrompt()" style="font-family:var(--mono);font-size:11px;letter-spacing:0.05em;text-transform:uppercase;padding:4px 12px;background:transparent;border:1px solid var(--border2);border-radius:5px;color:var(--text2);cursor:pointer;">Save prompt</button>
      <button onclick="refreshSchema()" id="refresh-schema-btn" style="font-family:var(--mono);font-size:11px;letter-spacing:0.05em;text-transform:uppercase;padding:4px 12px;background:transparent;border:1px solid var(--border2);border-radius:5px;color:var(--text2);cursor:pointer;">Refresh schema</button>
    </div>
  </div>
  <div class="input-row">
    <textarea id="sql-input" placeholder="SELECT * FROM characters LIMIT 10"></textarea>
    <button id="describe-btn" class="describe-btn" onclick="toggleDescribe()" title="Toggle natural language description">Describe</button>
    <button id="run-btn" onclick="runQuery()">Run</button>
  </div>
  <div class="hint" id="hint">&nbsp;</div>
</footer>
<script>
let mode = 'sql';
let describeMode = false;
let promptVisible = false;
const log = document.getElementById('log');
const input = document.getElementById('sql-input');
const btn = document.getElementById('run-btn');
const hint = document.getElementById('hint');
const dot = document.getElementById('status-dot');

input.addEventListener('keydown', e => {
  if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); runQuery(); }
});

function toggleDescribe() {
  describeMode = !describeMode;
  document.getElementById('describe-btn').className = 'describe-btn' + (describeMode ? ' on' : '');
}

function setMode(m) {
  mode = m;
  document.getElementById('btn-sql').className   = 'mode-btn' + (m === 'sql'   ? ' active' : '');
  document.getElementById('btn-nl').className    = 'mode-btn' + (m === 'nl'    ? ' active' : '');
  document.getElementById('btn-write').className = 'mode-btn' + (m === 'write' ? ' active' : '');
  if (m === 'sql') {
    input.placeholder = 'SELECT * FROM characters LIMIT 10';
    document.getElementById('describe-btn').style.display = '';
    document.getElementById('prompt-toggle-btn').style.display = 'none';
    document.getElementById('system-prompt-wrap').className = 'system-prompt-wrap';
    promptVisible = false;
  } else if (m === 'nl') {
    input.placeholder = 'Show me all rehearsals in week 3\u2026';
    document.getElementById('describe-btn').style.display = '';
    document.getElementById('prompt-toggle-btn').style.display = '';
  } else if (m === 'write') {
    input.placeholder = 'Describe the scene you want to write\u2026';
    document.getElementById('describe-btn').style.display = 'none';
    document.getElementById('prompt-toggle-btn').style.display = '';
  }
}

function togglePrompt() {
  promptVisible = !promptVisible;
  document.getElementById('system-prompt-wrap').className = 'system-prompt-wrap' + (promptVisible ? ' visible' : '');
  document.getElementById('prompt-toggle-btn').innerHTML = 'System prompt ' + (promptVisible ? '&#9650;' : '&#9660;');
}

async function runQuery() {
  const val = input.value.trim();
  const key = document.getElementById('api-key').value.trim();
  if (!val) return;
  if (!key) { setHint('Paste your API key first.', true); return; }

  document.getElementById('empty') && document.getElementById('empty').remove();
  addUserMsg(val);
  input.value = '';
  btn.disabled = true;
  setHint('');
  dot.className = 'status-dot';

  const loaderLabel = mode === 'write' ? 'researching & writing\u2026' : mode === 'nl' ? 'translating\u2026' : 'running\u2026';
  const loader = addLoader(loaderLabel);

  let body;
  if (mode === 'write') {
    body = { mode: 'write', prompt: val, system_prompt: document.getElementById('system-prompt').value.trim() || undefined };
  } else if (mode === 'nl') {
    body = { mode: 'nl', nl: val, system_prompt: document.getElementById('system-prompt').value.trim() || undefined, describe: describeMode };
  } else {
    body = { mode: 'sql', sql: val };
  }

  try {
    const res = await fetch(window.location.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-api-key': key },
      body: JSON.stringify(body)
    });
    const data = await res.json();
    loader.remove();
    if (!res.ok) {
      addError(data.error || 'HTTP ' + res.status);
      dot.className = 'status-dot err';
    } else if (data.status === 'write_only') {
      addWriteResult(data.generated_sql, data.message);
      dot.className = 'status-dot ok';
    } else if (data.error) {
      addError(data.error + (data.generated_sql ? '\n\nGenerated SQL:\n' + data.generated_sql : '') + (data.raw_db_response ? '\n\nDB response:\n' + JSON.stringify(data.raw_db_response, null, 2) : ''));
      dot.className = 'status-dot err';
    } else if (mode === 'write' && data.prose) {
      addProseResult(data.prose, data.sources || []);
      dot.className = 'status-dot ok';
    } else {
      addResult(data, mode === 'nl' ? data.generated_sql : null, data.description || '');
      dot.className = 'status-dot ok';
    }
  } catch (e) {
    loader.remove();
    addError('Network error \u2014 ' + e.message);
    dot.className = 'status-dot err';
  }

  btn.disabled = false;
  scrollBottom();
}

function addUserMsg(txt) {
  const d = document.createElement('div');
  d.className = 'msg-user';
  d.textContent = txt;
  log.appendChild(d);
  scrollBottom();
}

function addLoader(label) {
  const d = document.createElement('div');
  d.className = 'msg-result result-meta';
  d.innerHTML = '<span class="spinner"></span>' + label;
  log.appendChild(d);
  scrollBottom();
  return d;
}

function addError(msg) {
  const wrap = document.createElement('div');
  wrap.className = 'msg-result';
  const box = document.createElement('div');
  box.className = 'err-box';
  box.textContent = msg;
  wrap.appendChild(box);
  log.appendChild(wrap);
}

function addProseResult(prose, sources) {
  const wrap = document.createElement('div');
  wrap.className = 'msg-result';

  const p = document.createElement('div');
  p.className = 'prose-block';
  p.textContent = prose;
  wrap.appendChild(p);

  if (sources && sources.length > 0) {
    const toggle = document.createElement('button');
    toggle.className = 'sources-toggle';
    toggle.textContent = 'Sources (' + sources.length + ') \u25be';
    wrap.appendChild(toggle);

    const list = document.createElement('div');
    list.className = 'sources-list';
    sources.forEach(s => {
      const item = document.createElement('div');
      item.className = 'source-item';
      item.innerHTML = '<div class="source-nl">' + escHtml(s.nl) + '</div>' +
                       '<div class="source-sql">' + escHtml(s.sql) + '</div>' +
                       '<div class="source-count">' + s.row_count + ' row' + (s.row_count !== 1 ? 's' : '') + '</div>';
      list.appendChild(item);
    });
    wrap.appendChild(list);

    toggle.addEventListener('click', () => {
      const open = list.classList.toggle('open');
      toggle.textContent = 'Sources (' + sources.length + ') ' + (open ? '\u25b4' : '\u25be');
    });
  }

  log.appendChild(wrap);
}

function addResult(data, generatedSql, description) {
  const wrap = document.createElement('div');
  wrap.className = 'msg-result';

  if (generatedSql) {
    const sqlWrap = document.createElement('div');
    sqlWrap.className = 'generated-sql';
    const lbl = document.createElement('div');
    lbl.className = 'generated-sql-label';
    lbl.textContent = 'generated sql';
    sqlWrap.appendChild(lbl);
    const code = document.createElement('div');
    code.textContent = generatedSql;
    sqlWrap.appendChild(code);
    wrap.appendChild(sqlWrap);
  }

  const meta = document.createElement('div');
  meta.className = 'result-meta';
  meta.innerHTML = '<span class="ok">ok</span> &mdash; ' + data.row_count + ' row' + (data.row_count !== 1 ? 's' : '') + ' &middot; ' + escHtml(data.database) + ' &middot; limit ' + data.limit_applied;
  wrap.appendChild(meta);

  if (data.rows && data.rows.length > 0) {
    const cols = Object.keys(data.rows[0]);
    const tw = document.createElement('div');
    tw.className = 'table-wrap';
    const t = document.createElement('table');
    const thead = document.createElement('thead');
    const hr = document.createElement('tr');
    cols.forEach(c => { const th = document.createElement('th'); th.textContent = c; hr.appendChild(th); });
    thead.appendChild(hr);
    t.appendChild(thead);
    const tbody = document.createElement('tbody');
    data.rows.forEach(row => {
      const tr = document.createElement('tr');
      cols.forEach(c => {
        const td = document.createElement('td');
        const v = row[c];
        if (v === null) { td.textContent = 'NULL'; td.className = 'null-val'; }
        else { td.textContent = String(v); }
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    t.appendChild(tbody);
    tw.appendChild(t);
    wrap.appendChild(tw);
  } else {
    const nr = document.createElement('div');
    nr.className = 'no-rows';
    nr.textContent = 'No rows returned.';
    wrap.appendChild(nr);
  }

  if (description) {
    const desc = document.createElement('div');
    desc.className = 'nl-description';
    desc.textContent = description;
    wrap.appendChild(desc);
  }
  log.appendChild(wrap);
}

function addWriteResult(sql, message) {
  const wrap = document.createElement('div');
  wrap.className = 'msg-result';
  const notice = document.createElement('div');
  notice.style.cssText = 'font-family:var(--mono);font-size:11px;color:var(--accent);letter-spacing:0.05em;text-transform:uppercase;margin-bottom:8px;';
  notice.textContent = message;
  wrap.appendChild(notice);
  const sqlWrap = document.createElement('div');
  sqlWrap.className = 'generated-sql';
  sqlWrap.style.cursor = 'pointer';
  sqlWrap.title = 'Click to copy';
  const lbl = document.createElement('div');
  lbl.className = 'generated-sql-label';
  lbl.textContent = 'generated sql \u2014 click to copy';
  sqlWrap.appendChild(lbl);
  const code = document.createElement('div');
  code.textContent = sql;
  sqlWrap.appendChild(code);
  sqlWrap.addEventListener('click', () => {
    navigator.clipboard.writeText(sql).then(() => {
      lbl.textContent = 'copied!';
      setTimeout(() => { lbl.textContent = 'generated sql \u2014 click to copy'; }, 2000);
    });
  });
  wrap.appendChild(sqlWrap);
  log.appendChild(wrap);
}

function setHint(msg, isErr) {
  hint.textContent = msg || '\u00a0';
  hint.style.color = isErr ? '#b85c5c' : 'var(--text3)';
}

async function refreshSchema() {
  const key = document.getElementById("api-key").value.trim();
  if (!key) { setHint("Paste your API key first.", true); return; }
  const b = document.getElementById("refresh-schema-btn");
  const status = document.getElementById("schema-status");
  b.disabled = true;
  status.textContent = "refreshing\u2026";
  try {
    const res = await fetch(window.location.href, {
      method: "POST",
      headers: {"Content-Type": "application/json", "x-api-key": key},
      body: JSON.stringify({mode: "refresh_schema"})
    });
    const data = await res.json();
    status.textContent = data.status === "ok" ? "cached " + data.cached_at + " (" + data.table_count + " tables)" : "error";
  } catch(e) { status.textContent = "error"; }
  b.disabled = false;
}

async function savePrompt() {
  const prompt = document.getElementById("system-prompt").value;
  const key = document.getElementById("api-key").value.trim();
  if (!key) { setHint("Paste your API key first.", true); return; }
  const status = document.getElementById("prompt-save-status");
  status.textContent = "saving\u2026";
  try {
    const res = await fetch(window.location.href, {
      method: "POST",
      headers: {"Content-Type": "application/json", "x-api-key": key},
      body: JSON.stringify({mode: "save_prompt", prompt})
    });
    const data = await res.json();
    status.textContent = data.status === "ok" ? "saved" : "error";
    setTimeout(() => { status.textContent = ""; }, 2000);
  } catch(e) { status.textContent = "error"; }
}

function scrollBottom() { log.scrollTop = log.scrollHeight; }
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
</body>
</html>
