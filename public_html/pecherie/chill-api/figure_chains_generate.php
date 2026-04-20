<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/* ------------------ RESPONSE ------------------ */

function respond(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ------------------ CONFIG ------------------ */

function getConfig(): array
{
    $config = require __DIR__ . '/../../../pecherie_config.php';

    if (!is_array($config)) {
        respond(500, ['error' => 'Invalid server configuration']);
    }

    return $config;
}

/* ------------------ AUTH ------------------ */

function requireAuth(): void
{
    $config = getConfig();
    $expected = trim((string) ($config['pecherie_api_key'] ?? ''));

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $provided = null;

    foreach ($headers as $key => $value) {
        if (strtolower((string) $key) === 'x-api-key') {
            $provided = trim((string) $value);
            break;
        }
    }

    if ($provided === null && isset($_SERVER['HTTP_X_API_KEY'])) {
        $provided = trim((string) $_SERVER['HTTP_X_API_KEY']);
    }

    if ($expected === '') {
        respond(500, ['error' => 'Server auth is not configured']);
    }

    if ($provided === null || !hash_equals($expected, $provided)) {
        respond(401, ['error' => 'Unauthorized']);
    }
}

/* ------------------ INPUT ------------------ */

function input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        respond(400, ['error' => 'Invalid JSON']);
    }

    return $data;
}

/* ------------------ DB ------------------ */

function db(array $config): PDO
{
    $db = $config['db'] ?? null;

    if (!is_array($db)) {
        respond(500, ['error' => 'Database config missing']);
    }

    $host = trim((string) ($db['host'] ?? ''));
    $name = trim((string) ($db['name'] ?? ''));
    $user = trim((string) ($db['user'] ?? ''));
    $pass = (string) ($db['pass'] ?? '');
    $charset = trim((string) ($db['charset'] ?? 'utf8mb4'));

    if ($host === '' || $name === '' || $user === '') {
        respond(500, ['error' => 'Database config incomplete']);
    }

    return new PDO(
        "mysql:host={$host};dbname={$name};charset={$charset}",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

/* ------------------ MAIN ------------------ */

function run(PDO $pdo, array $in): array
{
    $startFigureClassvalId = $in['start_figure_classval_id'] ?? null;
    $danceId = $in['dance_id'] ?? null;
    $chainLength = (int) ($in['chain_length'] ?? 3);
    $syllabusCode = $in['syllabus_code'] ?? 'bronze';
    $allowedLegalityCodes = $in['allowed_legality_codes'] ?? ['always_allowed', 'conditional'];
    $limit = (int) ($in['limit'] ?? 50);

    if (!is_string($startFigureClassvalId) || trim($startFigureClassvalId) === '') {
        respond(400, ['error' => 'start_figure_classval_id required']);
    }

    if (!is_numeric($danceId)) {
        respond(400, ['error' => 'dance_id must be numeric']);
    }
    $danceId = (int) $danceId;

    if ($chainLength < 1 || $chainLength > 25) {
        respond(400, ['error' => 'chain_length must be between 1 and 25']);
    }

    if (!is_string($syllabusCode) || trim($syllabusCode) === '') {
        respond(400, ['error' => 'syllabus_code required']);
    }

    if (!is_array($allowedLegalityCodes) || count($allowedLegalityCodes) === 0) {
        respond(400, ['error' => 'allowed_legality_codes must be a non-empty array']);
    }

    $limit = max(1, min($limit, 200));

    try {
        $stmt = $pdo->prepare("
            SELECT sl.id
            FROM sxnzlfun_chrysalis.syllabus_level_classvals sl
            WHERE sl.code = :code
            LIMIT 1
        ");
        $stmt->execute([':code' => trim($syllabusCode)]);
        $syllabusLevelId = $stmt->fetchColumn();

        if (!$syllabusLevelId) {
            respond(400, ['error' => 'Unknown syllabus_code']);
        }

        $legalityCodePlaceholders = [];
        $legalityCodeParams = [];

        foreach (array_values($allowedLegalityCodes) as $i => $code) {
            if (!is_string($code) || trim($code) === '') {
                respond(400, ['error' => 'allowed_legality_codes must contain only non-empty strings']);
            }

            $ph = ":leg_code_$i";
            $legalityCodePlaceholders[] = $ph;
            $legalityCodeParams[$ph] = trim($code);
        }

        $sqlResolveLegalities = "
            SELECT tl.id, tl.code
            FROM sxnzlfun_chrysalis.transition_legality_classvals tl
            WHERE tl.code IN (" . implode(', ', $legalityCodePlaceholders) . ")
        ";

        $stmt = $pdo->prepare($sqlResolveLegalities);
        foreach ($legalityCodeParams as $ph => $val) {
            $stmt->bindValue($ph, $val, PDO::PARAM_STR);
        }
        $stmt->execute();

        $legalityRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$legalityRows) {
            respond(400, ['error' => 'No valid allowed_legality_codes found']);
        }

        $transitionLegalityIds = array_values(array_column($legalityRows, 'id'));

        $legalityIdPlaceholders = [];
        $params = [
            ':start_figure_classval_id' => trim($startFigureClassvalId),
            ':dance_id' => $danceId,
            ':syllabus_level_id' => $syllabusLevelId,
            ':chain_length_limit' => $chainLength,
            ':chain_length_exact' => $chainLength,
        ];

        foreach ($transitionLegalityIds as $i => $id) {
            $ph = ":leg_id_$i";
            $legalityIdPlaceholders[] = $ph;
            $params[$ph] = $id;
        }

        $sql = "
            WITH RECURSIVE
            filtered_transitions AS (
                SELECT
                    ft.predecessor_figure_entity_id,
                    ft.successor_figure_entity_id,
                    ft.dance_id
                FROM sxnzlfun_chrysalis.figure_transitions ft
                WHERE ft.dance_id = :dance_id
                  AND ft.syllabus_level_id = :syllabus_level_id
                  AND ft.transition_legality_id IN (" . implode(', ', $legalityIdPlaceholders) . ")
            ),
            figure_chain AS (
                SELECT
                    v.predecessor_figure_entity_id AS start_figure_entity_id,
                    v.predecessor_figure_classval_id AS start_figure_classval_id,
                    v.predecessor_figure AS start_figure,
                    v.following_figure_entity_id AS current_figure_entity_id,
                    v.following_figure_classval_id AS current_figure_classval_id,
                    v.following_figure AS current_figure,
                    v.dance_id,
                    1 AS depth,
                    CONCAT(
                        ',',
                        v.predecessor_figure_entity_id,
                        ',',
                        v.following_figure_entity_id,
                        ','
                    ) AS visited_figure_entity_ids,
                    CAST(v.formatted_output AS CHAR(10000)) AS steps_blob
                FROM sxnzlfun_chrysalis.vw_figure_following_conditions v
                INNER JOIN filtered_transitions ft
                    ON ft.predecessor_figure_entity_id = v.predecessor_figure_entity_id
                   AND ft.successor_figure_entity_id = v.following_figure_entity_id
                   AND ft.dance_id = v.dance_id
                WHERE v.predecessor_figure_classval_id = :start_figure_classval_id

                UNION ALL

                SELECT
                    fc.start_figure_entity_id,
                    fc.start_figure_classval_id,
                    fc.start_figure,
                    v.following_figure_entity_id AS current_figure_entity_id,
                    v.following_figure_classval_id AS current_figure_classval_id,
                    v.following_figure AS current_figure,
                    v.dance_id,
                    fc.depth + 1 AS depth,
                    CONCAT(fc.visited_figure_entity_ids, v.following_figure_entity_id, ',') AS visited_figure_entity_ids,
                    CONCAT(fc.steps_blob, '¦¦STEP¦¦', v.formatted_output) AS steps_blob
                FROM figure_chain fc
                INNER JOIN sxnzlfun_chrysalis.vw_figure_following_conditions v
                    ON v.predecessor_figure_entity_id = fc.current_figure_entity_id
                   AND v.dance_id = fc.dance_id
                INNER JOIN filtered_transitions ft
                    ON ft.predecessor_figure_entity_id = v.predecessor_figure_entity_id
                   AND ft.successor_figure_entity_id = v.following_figure_entity_id
                   AND ft.dance_id = v.dance_id
                WHERE fc.depth < :chain_length_limit
                  AND LOCATE(
                        CONCAT(',', v.following_figure_entity_id, ','),
                        fc.visited_figure_entity_ids
                  ) = 0
            )
            SELECT
                fc.start_figure_entity_id,
                fc.start_figure_classval_id,
                fc.start_figure,
                fc.current_figure_entity_id AS end_figure_entity_id,
                fc.current_figure_classval_id AS end_figure_classval_id,
                fc.current_figure AS end_figure,
                fc.dance_id,
                fc.depth AS chain_length,
                fc.steps_blob
            FROM figure_chain fc
            WHERE fc.depth = :chain_length_exact
            ORDER BY fc.start_figure, fc.current_figure
            LIMIT $limit
        ";

        $stmt = $pdo->prepare($sql);

        foreach ($params as $ph => $val) {
            $stmt->bindValue($ph, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $chains = [];
        foreach ($rows as $row) {
            $steps = array_values(array_filter(
                explode('¦¦STEP¦¦', (string) $row['steps_blob']),
                static fn(string $v): bool => $v !== ''
            ));

            $chains[] = [
                'start_figure_entity_id' => (string) $row['start_figure_entity_id'],
                'start_figure_classval_id' => (string) $row['start_figure_classval_id'],
                'start_figure' => (string) $row['start_figure'],
                'end_figure_entity_id' => (string) $row['end_figure_entity_id'],
                'end_figure_classval_id' => (string) $row['end_figure_classval_id'],
                'end_figure' => (string) $row['end_figure'],
                'dance_id' => (int) $row['dance_id'],
                'chain_length' => (int) $row['chain_length'],
                'steps' => $steps,
            ];
        }

        return [
            'status' => 'ok',
            'chains' => $chains,
        ];
    } catch (Throwable $e) {
        respond(500, [
            'error' => 'Database failure',
            'message' => $e->getMessage(),
        ]);
    }
}

/* ------------------ ENTRY ------------------ */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$config = getConfig();
$data = input();
$pdo = db($config);

respond(200, run($pdo, $data));