<?php

declare(strict_types=1);

// See: private/docs/calendar/beat_classsets.md
// for classset definitions and classifier contract.

const CALENDAR_BEAT_EXTRACTOR_VERSION = 'v2-classset-aware';

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

    $classsetId = $classset['id'];
    $classsetCode = $classset['code'];

    $allowedCodes = get_allowed_beat_codes_for_classset($pdo, $classsetId);

    $beats = extract_calendar_beats($prose, $classsetCode, $allowedCodes);

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

        $beatHash = hash('sha256', mb_strtolower($summary));

        $operations[] = [
            'operation' => 'createCalendarSubevent',
            'parent_event_entity_id' => $parentEventEntityId,
            'event_label' => $summary,
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
    string $prose,
    string $classsetCode,
    array $allowedCodes
): array {
    $segments = split_prose_into_candidate_segments($prose);

    $beats = [];

    foreach ($segments as $segment) {
        $summary = normalise_calendar_beat_summary($segment);

        if ($summary === '') {
            continue;
        }

        $classification = classify_calendar_beat_type(
            $summary,
            $classsetCode,
            $allowedCodes
        );

        $beats[] = [
            'type' => $classification['code'],
            'summary' => $summary,
            'inference' => $classification,
        ];
    }

    return dedupe_calendar_beats($beats);
}

function split_prose_into_candidate_segments(string $prose): array
{
    $text = str_replace(["\r\n", "\r"], "\n", $prose);
    $text = trim($text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    $parts = preg_split("/\n\n|(?:^|\n)(---|\*\*\*)(?:\n|$)/", $text);

    $fragments = [];

    foreach ($parts as $part) {
        $part = trim((string)$part);

        if ($part === '') {
            continue;
        }

        $lines = explode("\n", $part);
        $buffer = '';

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (starts_with_dialogue($line)) {
                if ($buffer !== '') {
                    $fragments[] = trim($buffer);
                    $buffer = '';
                }

                $fragments[] = $line;
            } else {
                $buffer = $buffer === ''
                    ? $line
                    : $buffer . ' ' . $line;
            }
        }

        if ($buffer !== '') {
            $fragments[] = trim($buffer);
        }
    }

    $segments = [];

    foreach ($fragments as $frag) {
        $parts = preg_split('/
            (?<=[.!?])\s+(?=(He|She|They|I|We)\s)
            |
            \s+(?=(Then|Suddenly|But|After that|When)\s)
        /x', $frag);

        foreach ($parts as $p) {
            $p = trim((string)$p);

            if ($p !== '') {
                $segments[] = $p;
            }
        }
    }

    $beats = [];

    foreach ($segments as $seg) {
        if (strlen($seg) < 80 && !empty($beats)) {
            $beats[count($beats) - 1] .= ' ' . $seg;
        } else {
            $beats[] = $seg;
        }
    }

    return array_values(array_map(function ($b) {
        return trim((string)preg_replace('/\s+/', ' ', $b));
    }, $beats));
}

function starts_with_dialogue(string $line): bool
{
    $first = mb_substr($line, 0, 1);

    return $first === '"' ||
        $first === "'" ||
        $first === '—';
}

function classify_calendar_beat_type(
    string $summary,
    string $classsetCode,
    array $allowedCodes
): array {
    global $pdo; // or pass it in properly if you prefer

    // Resolve classsetId from code (you may already have this upstream)
    static $classsetStmt = null;

    if ($classsetStmt === null) {
        $classsetStmt = $pdo->prepare("
            SELECT id FROM calendar_beat_classsets
            WHERE code = :code
            LIMIT 1
        ");
    }

    $classsetStmt->execute([':code' => $classsetCode]);
    $classsetId = $classsetStmt->fetchColumn();

    if (!$classsetId) {
        throw new RuntimeException('Unknown classset: ' . $classsetCode);
    }

    // Load rules
    static $rulesStmt = null;

    if ($rulesStmt === null) {
        $rulesStmt = $pdo->prepare("
            SELECT code, pattern, match_type, weight, priority
            FROM calendar_beat_type_rules
            WHERE set_id = :set_id
              AND is_active = 1
            ORDER BY priority DESC, weight DESC, id ASC
        ");
    }

    $rulesStmt->execute([':set_id' => $classsetId]);
    $rules = $rulesStmt->fetchAll(PDO::FETCH_ASSOC);

    $summaryLower = mb_strtolower($summary);

    $scores = [];

    foreach ($rules as $rule) {
        $pattern = mb_strtolower(trim($rule['pattern']));
        $code = mb_strtolower(trim($rule['code']));
        $match = false;

        switch ($rule['match_type']) {
            case 'exact':
                $match = ($summaryLower === $pattern);
                break;

            case 'prefix':
                $match = str_starts_with($summaryLower, $pattern);
                break;

            case 'regex':
                $match = preg_match($pattern, $summary) === 1;
                break;

            case 'contains':
            default:
                $match = str_contains($summaryLower, $pattern);
                break;
        }

        if ($match) {
            $scores[$code] = ($scores[$code] ?? 0) + (int)$rule['weight'];
        }
    }

    if (empty($scores)) {
        return validate_calendar_beat_classification([
            'code' => 'transition',
            'rule' => 'db:no_match',
            'confidence' => 'fallback',
            'classset' => $classsetCode,
        ], $allowedCodes);
    }

    arsort($scores);
    $bestCode = array_key_first($scores);

    return validate_calendar_beat_classification([
        'code' => $bestCode,
        'rule' => 'db:scored',
        'confidence' => 'rule',
        'classset' => $classsetCode,
    ], $allowedCodes);
}

function validate_calendar_beat_classification(array $classification, array $allowedCodes): array
{
    $code = mb_strtolower(trim((string)($classification['code'] ?? '')));

    if ($code === '') {
        throw new RuntimeException('Classifier emitted empty beat code');
    }

    $allowedCodes = array_map(
        static fn($value): string => mb_strtolower(trim((string)$value)),
        $allowedCodes
    );

    if (!in_array($code, $allowedCodes, true)) {
        throw new RuntimeException(
            "Classifier emitted invalid beat code '{$code}' for resolved classset"
        );
    }

    $classification['code'] = $code;

    return $classification;
}

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

function dedupe_calendar_beats(array $beats): array
{
    $seen = [];
    $deduped = [];

    foreach ($beats as $beat) {
        $summary = trim((string)($beat['summary'] ?? ''));

        if ($summary === '') {
            continue;
        }

        $key = mb_strtolower($summary);

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $deduped[] = $beat;
    }

    return $deduped;
}