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
        'calendar_event:%s:slot:%d',
        $parentEventEntityId,
        $slot
    );
}


function persist_segmented_subevents(
    PDO $pdo,
    string $parentEventEntityId,
    array $subevents
): array {

    $results = [];

    foreach ($subevents as $subevent) {

        $slot = (int)($subevent['slot'] ?? 0);

        if ($slot < 1) {
            throw new RuntimeException('Invalid subevent slot');
        }

        $summary = trim((string)($subevent['summary'] ?? ''));

        if ($summary === '') {
            throw new RuntimeException('Subevent summary is required');
        }

        $proseBody = trim((string)($subevent['prose_body'] ?? ''));

        if ($proseBody === '') {
            throw new RuntimeException('Subevent prose_body is required');
        }

        $clientId = build_subevent_client_id(
            $parentEventEntityId,
            $slot
        );

        $result = create_calendar_subevent_core(
            $pdo,
            [
                'parent_event_entity_id' => $parentEventEntityId,
                'client_id' => $clientId,
                'event_label' => $summary,
                'prose_body' => $proseBody,
            ]
        );

        $results[] = [
            'slot' => $slot,
            'client_id' => $clientId,
            'result' => $result,
        ];
    }

    return $results;
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


