<?php

declare(strict_types=1);

// See: private/docs/calendar/beat_classsets.md
// for classset definitions and classifier contract.

const CALENDAR_BEAT_EXTRACTOR_VERSION = 'v3-db-rules-classset-id';

/**
 * Optional classset-specific fallback overrides.
 *
 * Prefer explicit classset defaults over global transition fallback.
 *
 * Example:
 * const CALENDAR_BEAT_CLASSSET_DEFAULT_CODES = [
 *     'DEFAULT' => 'transition',
 *     'PERSONAL' => 'reflection',
 *     'INTIMATE' => 'tension',
 * ];
 */
const CALENDAR_BEAT_CLASSSET_DEFAULT_CODES = [];

function generate_calendar_batch_from_prose(
    PDO $pdo,
    string $parentEventEntityId,
    string $prose
): array {
    $parentEventEntityId = trim($parentEventEntityId);
    $prose = trim($prose);

    if ($parentEventEntityId === '') {
        throw new InvalidArgumentException('parent_event_entity_id is required');
    }

    if ($prose === '') {
        throw new InvalidArgumentException('prose is required');
    }

    $classset = resolve_classset_for_event($pdo, $parentEventEntityId);

    $classsetId = (string)$classset['id'];
    $classsetCode = (string)$classset['code'];

    $allowedCodes = get_allowed_beat_codes_for_classset($pdo, $classsetId);

    $beats = extract_calendar_beats(
        $pdo,
        $prose,
        $classsetId,
        $classsetCode,
        $allowedCodes
    );

    $planSeed = json_encode([
        'parent' => $parentEventEntityId,
        'version' => CALENDAR_BEAT_EXTRACTOR_VERSION,
        'classset_id' => $classsetId,
        'classset_code' => $classsetCode,
        'beats' => $beats,
    ]);

    $planId = hash('sha256', (string)$planSeed);

    $operations = [];

    foreach ($beats as $i => $beat) {
        $summary = trim((string)($beat['summary'] ?? ''));

        if ($summary === '') {
            continue;
        }

        $beatHash = hash(
            'sha256',
            mb_strtolower($summary . "\n\n" . (string)($beat['prose_body'] ?? ''))
        );

        $operations[] = [
            'operation' => 'createCalendarSubevent',
            'parent_event_entity_id' => $parentEventEntityId,
            'event_label' => $summary,
            'prose_body' => $beat['prose_body'] ?? null,
            'beat_type_id' => resolve_beat_type_id(
                $pdo,
                $classsetId,
                (string)$beat['type']
            ),
            'beat_inference' => $beat['inference'] ?? null,
            'client_id' => 'calendar_event:' . $parentEventEntityId . ':slot:' . $i,
            'order_index' => $i,
            'beat_hash' => $beatHash,
        ];
    }

    if (count($operations) > 50) {
        throw new RuntimeException('Batch too large');
    }

    return [
        'status' => 'ok',
        'mode' => 'plan_only',
        'parent_event_entity_id' => $parentEventEntityId,
        'beat_extractor_version' => CALENDAR_BEAT_EXTRACTOR_VERSION,
        'classset_id' => $classsetId,
        'classset_code' => $classsetCode,
        'plan_id' => $planId,
        'operation_count' => count($operations),
        'beats' => $beats,
        'operations' => $operations,
    ];
}

function resolve_classset_for_event(PDO $pdo, string $eventEntityId): array
{
    static $stmt = null;

    if ($stmt === null) {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(m.classset_id, 'CLASSSET-CALENDAR-BEAT-001') AS classset_id,
                cs.code AS classset_code,
                cs.label AS classset_label
            FROM calendar_events e
            LEFT JOIN calendar_domain_beat_classset_map m
                ON m.domain_id = e.domain_id
            LEFT JOIN calendar_beat_classsets cs
                ON cs.id = COALESCE(m.classset_id, 'CLASSSET-CALENDAR-BEAT-001')
            WHERE e.entity_id = :event_entity_id
            LIMIT 1
        ");
    }

    $stmt->execute([
        ':event_entity_id' => $eventEntityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Parent event not found: ' . $eventEntityId);
    }

    $classsetId = trim((string)$row['classset_id']);
    $classsetCode = trim((string)$row['classset_code']);
    $classsetLabel = trim((string)$row['classset_label']);

    if ($classsetId === '') {
        throw new RuntimeException('Missing classset id for event: ' . $eventEntityId);
    }

    if ($classsetCode === '') {
        throw new RuntimeException('Missing classset code for classset_id: ' . $classsetId);
    }

    return [
        'id' => $classsetId,
        'code' => $classsetCode,
        'label' => $classsetLabel,
    ];
}

function get_allowed_beat_codes_for_classset(PDO $pdo, string $classsetId): array
{
    static $cache = [];
    static $stmt = null;

    $classsetId = trim($classsetId);

    if ($classsetId === '') {
        throw new RuntimeException('Missing beat classset id');
    }

    if (isset($cache[$classsetId])) {
        return $cache[$classsetId];
    }

    if ($stmt === null) {
        $stmt = $pdo->prepare("
            SELECT code
            FROM cvt_calendar_beat_type
            WHERE set_id = :set_id
            ORDER BY code
        ");
    }

    $stmt->execute([
        ':set_id' => $classsetId,
    ]);

    $codes = [];

    while (($code = $stmt->fetchColumn()) !== false) {
        $code = mb_strtolower(trim((string)$code));

        if ($code !== '') {
            $codes[] = $code;
        }
    }

    $codes = array_values(array_unique($codes));

    if ($codes === []) {
        throw new RuntimeException('No beat codes registered for classset: ' . $classsetId);
    }

    $cache[$classsetId] = $codes;

    return $codes;
}

function resolve_beat_type_id(PDO $pdo, string $classsetId, string $code): string
{
    static $cache = [];
    static $stmt = null;

    $classsetId = trim($classsetId);
    $code = mb_strtolower(trim($code));

    if ($classsetId === '') {
        throw new RuntimeException('Missing beat classset id');
    }

    if ($code === '') {
        throw new RuntimeException('Missing beat code');
    }

    $cacheKey = $classsetId . '::' . $code;

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    if ($stmt === null) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM cvt_calendar_beat_type
            WHERE set_id = :set_id
              AND code = :code
            LIMIT 1
        ");
    }

    $stmt->execute([
        ':set_id' => $classsetId,
        ':code' => $code,
    ]);

    $id = $stmt->fetchColumn();

    if (!is_string($id) || $id === '') {
        throw new RuntimeException(
            'Unknown beat type for classset ' . $classsetId . ': ' . $code
        );
    }

    $cache[$cacheKey] = $id;

    return $id;
}

function extract_calendar_beats(
    PDO $pdo,
    string $prose,
    string $classsetId,
    string $classsetCode,
    array $allowedCodes
): array {
    $segments = split_prose_into_candidate_segments($prose);

    $beats = [];

    foreach ($segments as $segment) {

        $segment = trim((string)$segment);

        if ($segment === '') {
            continue;
        }

        $classification = classify_calendar_beat_type(
            $pdo,
            $segment,
            $classsetId,
            $classsetCode,
            $allowedCodes
        );

        $beats[] = [
            'type' => $classification['code'],
            'summary' => extract_beat_line($segment),
            'prose_body' => $segment,
            'inference' => $classification,
        ];
    }

    return dedupe_calendar_beats($beats);
}

function classify_calendar_beat_type(
    PDO $pdo,
    string $summary,
    string $classsetId,
    string $classsetCode,
    array $allowedCodes
): array {
    static $rulesCache = [];
    static $stmt = null;

    $classsetId = trim($classsetId);
    $classsetCode = trim($classsetCode);
    $allowedCodes = normalise_calendar_allowed_codes($allowedCodes);

    if ($classsetId === '') {
        throw new RuntimeException('Missing classset id');
    }

    if ($allowedCodes === []) {
        throw new RuntimeException('No allowed beat codes supplied for classset ' . $classsetCode);
    }

    if (!isset($rulesCache[$classsetId])) {
        if ($stmt === null) {
            $stmt = $pdo->prepare("
                SELECT id, code, pattern, match_type, weight, priority
                FROM calendar_beat_type_rules
                WHERE set_id = :set_id
                  AND is_active = 1
                ORDER BY priority DESC, weight DESC, id ASC
            ");
        }

        $stmt->execute([
            ':set_id' => $classsetId,
        ]);

        $rulesCache[$classsetId] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $summaryNorm = normalise_calendar_rule_text($summary);
    $matches = [];

    foreach ($rulesCache[$classsetId] as $rule) {
        $code = mb_strtolower(trim((string)($rule['code'] ?? '')));
        $pattern = trim((string)($rule['pattern'] ?? ''));
        $matchType = mb_strtolower(trim((string)($rule['match_type'] ?? '')), 'UTF-8');

        if ($code === '' || $pattern === '') {
            continue;
        }

        // Critical invariant guard:
        // classifier may only emit codes allowed for the already-resolved classset.
        if (!in_array($code, $allowedCodes, true)) {
            continue;
        }

        if (!calendar_beat_rule_matches($summaryNorm, $pattern, $matchType)) {
            continue;
        }

        $matches[] = [
            'code' => $code,
            'rule_id' => (int)$rule['id'],
            'priority' => (int)$rule['priority'],
            'weight' => (int)$rule['weight'],
        ];
    }

    if ($matches === []) {
        $fallbackCode = resolve_calendar_beat_fallback_code(
            $classsetCode,
            $allowedCodes
        );

        return validate_calendar_beat_classification([
            'code' => $fallbackCode,
            'rule' => 'db:no_match',
            'rule_id' => null,
            'confidence' => 'fallback',
            'classset' => $classsetCode,
        ], $allowedCodes);
    }

    usort($matches, static function (array $a, array $b): int {
        return [$b['priority'], $b['weight'], $a['rule_id']]
            <=> [$a['priority'], $a['weight'], $b['rule_id']];
    });

    $winner = $matches[0];

    return validate_calendar_beat_classification([
        'code' => $winner['code'],
        'rule' => 'db:rule_match',
        'rule_id' => $winner['rule_id'],
        'confidence' => 'rule',
        'classset' => $classsetCode,
    ], $allowedCodes);
}

function calendar_beat_rule_matches(
    string $summaryNorm,
    string $pattern,
    string $matchType
): bool {
    $patternNorm = normalise_calendar_rule_text($pattern);

    return match ($matchType) {
        'exact' => $summaryNorm === $patternNorm,

        'prefix' => $patternNorm !== ''
            && str_starts_with($summaryNorm, $patternNorm),

        'regex' => calendar_beat_regex_matches($summaryNorm, $pattern),

        'contains' => $patternNorm !== ''
            && str_contains($summaryNorm, $patternNorm),

        default => false,
    };
}

function calendar_beat_regex_matches(string $summaryNorm, string $pattern): bool
{
    if (trim($pattern) === '') {
        return false;
    }

    $regex = $pattern;

    if (@preg_match($regex, '') === false) {
        $regex = '/' . str_replace('/', '\/', $pattern) . '/u';
    }

    return @preg_match($regex, $summaryNorm) === 1;
}

function resolve_calendar_beat_fallback_code(
    string $classsetCode,
    array $allowedCodes
): string {
    $classsetCode = trim($classsetCode);

    $configuredDefault = CALENDAR_BEAT_CLASSSET_DEFAULT_CODES[$classsetCode]
        ?? CALENDAR_BEAT_CLASSSET_DEFAULT_CODES[strtoupper($classsetCode)]
        ?? null;

    if (is_string($configuredDefault) && trim($configuredDefault) !== '') {
        $configuredDefault = mb_strtolower(trim($configuredDefault));

        if (!in_array($configuredDefault, $allowedCodes, true)) {
            throw new RuntimeException(
                'Configured fallback beat code "' . $configuredDefault .
                '" is not allowed for classset ' . $classsetCode
            );
        }

        return $configuredDefault;
    }

    // Temporary migration fallback only.
    if (in_array('transition', $allowedCodes, true)) {
        return 'transition';
    }

    throw new RuntimeException(
        'No fallback beat code configured for classset ' . $classsetCode .
        '; configure CALENDAR_BEAT_CLASSSET_DEFAULT_CODES or allow transition explicitly.'
    );
}

function normalise_calendar_allowed_codes(array $allowedCodes): array
{
    $normalised = [];

    foreach ($allowedCodes as $code) {
        $code = mb_strtolower(trim((string)$code));

        if ($code !== '') {
            $normalised[] = $code;
        }
    }

    return array_values(array_unique($normalised));
}

function normalise_calendar_rule_text(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);

    return $text ?? '';
}

function validate_calendar_beat_classification(array $classification, array $allowedCodes): array
{
    $code = mb_strtolower(trim((string)($classification['code'] ?? '')));

    if ($code === '') {
        throw new RuntimeException('Classifier emitted empty beat code');
    }

    $allowedCodes = normalise_calendar_allowed_codes($allowedCodes);

    if (!in_array($code, $allowedCodes, true)) {
        throw new RuntimeException(
            "Classifier emitted invalid beat code '{$code}' for resolved classset"
        );
    }

    $classification['code'] = $code;

    return $classification;
}

function split_prose_into_candidate_segments(string $prose): array
{
    $text = str_replace(["\r\n", "\r"], "\n", $prose);
    $text = trim($text);

    if ($text === '') {
        return [];
    }

    /*
     * Canonical prose execution boundary:
     *
     * double newline
     *
     * NOT sentence boundaries.
     *
     * Each block becomes one candidate subevent body.
     */

    $blocks = preg_split("/\n\s*\n/", $text);

    $segments = [];

    foreach ($blocks as $block) {
        $block = trim((string)$block);

        if ($block === '') {
            continue;
        }

        /*
         * Preserve prose integrity.
         * Collapse internal whitespace only.
         */

        $block = preg_replace('/\s+/', ' ', $block);

        $segments[] = trim((string)$block);
    }

    return $segments;
}


/*legacy*/
function normalise_calendar_beat_summary(string $segment): string
{
    $segment = trim($segment);
    $segment = preg_replace('/\s+/', ' ', $segment);

    if ($segment === '') {
        return '';
    }

    if (mb_strlen($segment) > 140) {
        $segment = mb_substr($segment, 0, 137) . '...';
    }

    return ucfirst($segment);
}

function extract_beat_line(string $prose): string
{
    $prose = trim($prose);

    if ($prose === '') {
        return '';
    }

    /*
     * Temporary heuristic:
     * first sentence becomes execution label.
     */

    $parts = preg_split('/(?<=[.!?])\s+/', $prose, 2);

    $line = trim((string)($parts[0] ?? $prose));

    if (mb_strlen($line) > 140) {
        $line = mb_substr($line, 0, 137) . '...';
    }

    return $line;
}

function dedupe_calendar_beats(array $beats): array
{
    $seen = [];
    $deduped = [];

    foreach ($beats as $beat) {
        $summary = trim((string)($beat['summary'] ?? ''));

        if ($summary === '') {
            continue;
        }

        $key = mb_strtolower(
            $summary . "\n\n" . (string)($beat['prose_body'] ?? '')
        );

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $deduped[] = $beat;
    }

    return $deduped;
}