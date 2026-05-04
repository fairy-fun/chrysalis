<?php

declare(strict_types=1);

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

    $beats = extract_calendar_beats($prose);

    $operations = [];

    foreach ($beats as $beat) {
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
        ];
    }

    if (count($operations) > 50) {
        throw new RuntimeException('Batch too large');
    }

    return [
        'status' => 'ok',
        'mode' => 'plan_only',
        'parent_event_entity_id' => $parentEventEntityId,
        'operation_count' => count($operations),
        'operations' => $operations,
    ];
}

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

function extract_calendar_beats(string $prose): array {
    $segments = split_prose_into_candidate_segments($prose);

    $beats = [];

    foreach ($segments as $segment) {
        $summary = normalise_calendar_beat_summary($segment);

        if ($summary === '') {
            continue;
        }

        $classification = classify_calendar_beat_type($summary);

        $beats[] = [
            'type' => $classification['code'],
            'summary' => $summary,
            'inference' => $classification,
        ];
    }

    return dedupe_calendar_beats($beats);
}

function split_prose_into_candidate_segments(string $prose): array {
    $prose = trim($prose);

    $lines = preg_split('/\R+/', $prose) ?: [];

    $segments = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        $line = preg_replace('/^\s*[-*•]\s*/', '', $line);
        $line = preg_replace('/^\s*\d+[\).\s-]+/', '', $line);
        $line = trim((string)$line);

        if ($line === '') {
            continue;
        }

        $sentenceParts = preg_split('/(?<=[.!?])\s+/', $line) ?: [$line];

        foreach ($sentenceParts as $part) {
            $part = trim($part);

            if ($part !== '') {
                $segments[] = $part;
            }
        }
    }

    return $segments;
}

function normalise_calendar_beat_summary(string $segment): string {
    $segment = trim($segment);

    $segment = preg_replace('/\s+/', ' ', $segment);
    $segment = trim((string)$segment);

    if ($segment === '') {
        return '';
    }

    if (mb_strlen($segment) > 140) {
        $segment = mb_substr($segment, 0, 137) . '...';
    }

    return ucfirst($segment);
}

function classify_calendar_beat_type(string $summary): array {
    $lower = mb_strtolower($summary);

    $rules = [
        'instruction' => [
            'explain',
            'rule',
            'principle',
            'concept',
            'frame',
        ],
        'demonstration' => [
            'demonstrat',
            'show',
            'perform',
            'model',
        ],
        'correction' => [
            'correct',
            'incorrect',
            'error',
            'fix',
            'adjust',
        ],
        'interaction' => [
            'touch',
            'takes my',
            'dialogue',
            'asks',
            'answers',
            'responds',
        ],
        'evaluation' => [
            'acceptable',
            'good',
            'nods',
            'judges',
            'approval',
            'assess',
        ],
        'reflection' => [
            'i think',
            'i realise',
            'i realize',
            'i notice',
            'has been downgraded',
            'system',
            'meta',
        ],
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

function dedupe_calendar_beats(array $beats): array {
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