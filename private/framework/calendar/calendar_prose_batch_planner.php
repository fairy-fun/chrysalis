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

function extract_calendar_beats(string $prose): array {
    $segments = split_prose_into_candidate_segments($prose);

    $beats = [];

    foreach ($segments as $segment) {
        $summary = normalise_calendar_beat_summary($segment);

        if ($summary === '') {
            continue;
        }

        $beats[] = [
            'type' => classify_calendar_beat_type($summary),
            'summary' => $summary,
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

function classify_calendar_beat_type(string $summary): string {
    $lower = mb_strtolower($summary);

    if (str_contains($lower, 'demonstrat')) {
        return 'demonstration';
    }

    if (
        str_contains($lower, 'correct') ||
        str_contains($lower, 'adjust') ||
        str_contains($lower, 'fix')
    ) {
        return 'correction';
    }

    if (
        str_contains($lower, 'explain') ||
        str_contains($lower, 'says') ||
        str_contains($lower, 'tells')
    ) {
        return 'instruction';
    }

    if (
        str_contains($lower, 'watch') ||
        str_contains($lower, 'notice') ||
        str_contains($lower, 'observe')
    ) {
        return 'observation';
    }

    if (
        str_contains($lower, 'evaluate') ||
        str_contains($lower, 'assess')
    ) {
        return 'evaluation';
    }

    return 'action';
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