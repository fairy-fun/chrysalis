<?php

declare(strict_types=1);

const CALENDAR_BEAT_EXTRACTOR_VERSION = 'v1';


function generate_calendar_batch_from_prose(
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

    // -----------------------------
    // Beat extraction (deterministic)
    // -----------------------------
    $beats = extract_calendar_beats($prose);

    // -----------------------------
    // Plan identity (stable)
    // -----------------------------
    $planSeed = json_encode([
        'parent' => $parentEventEntityId,
        'version' => CALENDAR_BEAT_EXTRACTOR_VERSION,
        'beats' => $beats,
    ]);

    $planId = hash('sha256', $planSeed);

    // -----------------------------
    // Build operations
    // -----------------------------
    $operations = [];

    foreach ($beats as $i => $beat) {

        $summary = trim((string)($beat['summary'] ?? ''));
        if ($summary === '') {
            continue;
        }

        $operations[] = [
            'operation' => 'createCalendarSubevent',
            'parent_event_entity_id' => $parentEventEntityId,
            'event_label' => $summary,
            'beat_type_id' => map_calendar_beat_code_to_id((string)$beat['type']),
            'beat_inference' => $beat['inference'] ?? null,

            // ✅ idempotency
            'client_id' => $planId . ':' . $i,

            // ✅ stable ordering
            'order_index' => $i,
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
        'plan_id' => $planId,
        'operation_count' => count($operations),
        'beats' => $beats,
        'operations' => $operations,
    ];
}


# =========================================================
# BEAT PIPELINE
# =========================================================

function extract_calendar_beats(string $prose): array {

    $segments = split_prose_into_candidate_segments($prose);

    $beats = [];

    foreach ($segments as $segment) {

        $summary = normalise_calendar_beat_summary($segment);
        if ($summary === '') continue;

        $classification = classify_calendar_beat_type($summary);

        $beats[] = [
            'type' => $classification['code'],
            'summary' => $summary,
            'inference' => $classification,
        ];
    }

    return dedupe_calendar_beats($beats);
}


# =========================================================
# 🔥 FIXED SEGMENTATION (core change)
# =========================================================

function split_prose_into_candidate_segments(string $prose): array
{
    $text = str_replace(["\r\n", "\r"], "\n", $prose);
    $text = trim($text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    // --- paragraph + scene split
    $parts = preg_split("/\n\n|(?:^|\n)(---|\*\*\*)(?:\n|$)/", $text);

    $fragments = [];

    foreach ($parts as $part) {

        $part = trim($part);
        if ($part === '') continue;

        $lines = explode("\n", $part);
        $buffer = '';

        foreach ($lines as $line) {

            $line = trim($line);
            if ($line === '') continue;

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

    // --- secondary splits
    $segments = [];

    foreach ($fragments as $frag) {

        $parts = preg_split('/
            (?<=[.!?])\s+(?=(He|She|They|I|We)\s)
            |
            \s+(?=(Then|Suddenly|But|After that|When)\s)
        /x', $frag);

        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $segments[] = $p;
            }
        }
    }

    // --- merge small fragments
    $beats = [];

    foreach ($segments as $seg) {
        if (strlen($seg) < 80 && !empty($beats)) {
            $beats[count($beats) - 1] .= ' ' . $seg;
        } else {
            $beats[] = $seg;
        }
    }

    // --- final normalize
    return array_values(array_map(function ($b) {
        return trim(preg_replace('/\s+/', ' ', $b));
    }, $beats));
}


function starts_with_dialogue(string $line): bool
{
    $first = mb_substr($line, 0, 1);

    return $first === '"' ||
        $first === "'" ||
        $first === '—';
}


# =========================================================
# CLASSIFICATION (unchanged)
# =========================================================

function allowed_calendar_beat_types(): array {
    return [
        'instruction',
        'demonstration',
        'correction',
        'interaction',
        'evaluation',
        'reflection',
        'transition',
    ];
}

function map_calendar_beat_code_to_id(string $code): string
{
    static $map = [
        'instruction' => 'BEAT_INSTRUCTION',
        'demonstration' => 'BEAT_DEMONSTRATION',
        'correction' => 'BEAT_CORRECTION',
        'interaction' => 'BEAT_INTERACTION',
        'evaluation' => 'BEAT_EVALUATION',
        'reflection' => 'BEAT_REFLECTION',
        'transition' => 'BEAT_TRANSITION',
    ];

    if (!isset($map[$code])) {
        throw new RuntimeException('Unknown beat code: ' . $code);
    }

    return $map[$code];
}

function classify_calendar_beat_type(string $summary): array {

    $lower = mb_strtolower($summary);

    $rules = [
        'instruction' => ['explain','rule','principle','concept','frame'],
        'demonstration' => ['demonstrat','show','perform','model'],
        'correction' => ['correct','incorrect','error','fix','adjust'],
        'interaction' => ['touch','takes my','dialogue','asks','answers','responds'],
        'evaluation' => ['acceptable','good','nods','judges','approval','assess'],
        'reflection' => ['i think','i realise','i realize','i notice','system','meta'],
    ];

    foreach ($rules as $code => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return [
                    'code' => $code,
                    'rule' => 'keyword:' . $keyword,
                    'confidence' => 'rule',
                ];
            }
        }
    }

    return [
        'code' => 'transition',
        'rule' => 'default:no_keyword_match',
        'confidence' => 'fallback',
    ];
}

function normalise_calendar_beat_summary(string $segment): string {

    $segment = trim($segment);
    $segment = preg_replace('/\s+/', ' ', $segment);

    if ($segment === '') return '';

    if (mb_strlen($segment) > 140) {
        $segment = mb_substr($segment, 0, 137) . '...';
    }

    return ucfirst($segment);
}

function dedupe_calendar_beats(array $beats): array {

    $seen = [];
    $deduped = [];

    foreach ($beats as $beat) {

        $summary = trim((string)($beat['summary'] ?? ''));
        if ($summary === '') continue;

        $key = mb_strtolower($summary);

        if (isset($seen[$key])) continue;

        $seen[$key] = true;
        $deduped[] = $beat;
    }

    return $deduped;
}