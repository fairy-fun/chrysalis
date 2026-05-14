<?php

declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_subevent_segmenter.php';
require_once __DIR__ . '/calendar_subevent_service.php';

function build_subevent_client_id(
    string $parentEventEntityId,
    int $slot
): string {

    $parentEventEntityId = trim($parentEventEntityId);

    if ($parentEventEntityId === '') {
        throw new InvalidArgumentException(
            'parentEventEntityId must be non-empty'
        );
    }

    if ($slot < 1) {
        throw new InvalidArgumentException(
            'slot must be >= 1'
        );
    }

    return sprintf(
        '%s:slot:%d',
        $parentEventEntityId,
        $slot
    );
}

function persist_segmented_subevents(
    PDO $pdo,
    string $parentEventEntityId,
    array $subevents
): array {

    $persisted = [];

    foreach ($subevents as $index => $subevent) {

        $slot = (int)($subevent['slot'] ?? 0);

        $summary = trim((string)($subevent['summary'] ?? ''));

        $proseBody = trim((string)($subevent['prose_body'] ?? ''));

        if ($slot < 1) {
            throw new RuntimeException(
                'Invalid subevent slot'
            );
        }

        if ($summary === '') {
            throw new RuntimeException(
                'Subevent summary is required'
            );
        }

        if ($proseBody === '') {
            throw new RuntimeException(
                'Subevent prose_body is required'
            );
        }

        $persisted[] = create_calendar_subevent_core(
            $pdo,
            [
                'client_id' => build_subevent_client_id(
                    $parentEventEntityId,
                    $slot
                ),
                'subevent_index' => $slot,
                'parent_event_entity_id' => $parentEventEntityId,
                'event_label' => $summary,
                'prose_body' => $proseBody,
            ]
        );
    }

    return $persisted;
}

function execute_calendar_batch_from_prose(
    PDO $pdo,
    string $parentEventEntityId,
    string $prose
): array {

    $parentEventEntityId = trim($parentEventEntityId);

    if ($parentEventEntityId === '') {
        throw new InvalidArgumentException(
            'parentEventEntityId must be non-empty'
        );
    }

    $subevents = segment_prose_into_subevents($prose);

    if ($subevents === []) {
        return [
            'status' => 'ok',
            'parent_event_entity_id' => $parentEventEntityId,
            'subevent_count' => 0,
            'persisted_count' => 0,
            'results' => [],
        ];
    }

    $persisted = persist_segmented_subevents(
        $pdo,
        $parentEventEntityId,
        $subevents
    );

    return [
        'status' => 'ok',
        'parent_event_entity_id' => $parentEventEntityId,
        'subevent_count' => count($subevents),
        'persisted_count' => count($persisted),
        'results' => $persisted,
    ];
}