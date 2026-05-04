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

    $labels = split_calendar_prose_into_subevent_labels($prose);

    $operations = [];

    foreach ($labels as $label) {
        $label = trim($label);

        if ($label === '') {
            continue;
        }

        $operations[] = [
            'operation' => 'createCalendarSubevent',
            'parent_event_entity_id' => $parentEventEntityId,
            'event_label' => $label,
        ];
    }

    return [
        'status' => 'ok',
        'mode' => 'plan_only',
        'parent_event_entity_id' => $parentEventEntityId,
        'operation_count' => count($operations),
        'operations' => $operations,
    ];
}

function split_calendar_prose_into_subevent_labels(string $prose): array {
    $lines = preg_split('/\R+/', $prose) ?: [];

    $labels = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        $line = preg_replace('/^\s*[-*•]\s*/', '', $line);
        $line = preg_replace('/^\s*\d+[\).\s-]+/', '', $line);
        $line = trim((string) $line);

        if ($line !== '') {
            $labels[] = $line;
        }
    }

    return array_values(array_unique($labels));
}