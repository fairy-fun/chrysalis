<?php

declare(strict_types=1);

require_once __DIR__ . '/prose_metadata_deriver.php';
require_once __DIR__ . '/../calendar/calendar_subevent_service.php';

/**
 * Deterministic prose → subevent segmentation runtime.
 *
 * Owns:
 * - segmentation
 * - canonical slot assignment
 * - replay-safe client_id generation
 * - persistence glue into create_calendar_subevent_core()
 */

function segment_prose_into_subevents(
    string $prose
): array {

    $normalized = normalize_subevent_prose($prose);

    if ($normalized === '') {
        return [];
    }

    $blocks = preg_split(
        "/\n{2,}/",
        $normalized
    );

    if (!is_array($blocks)) {
        return [];
    }

    $blocks = array_values(
        array_filter(
            array_map(
                static fn (string $block): string => trim($block),
                $blocks
            ),
            static fn (string $block): bool => $block !== ''
        )
    );

    $merged = [];

    foreach ($blocks as $block) {

        $words = preg_split('/\s+/', trim($block));
        $wordCount = is_array($words) ? count($words) : 0;

        if (
            $wordCount < 40 &&
            !empty($merged)
        ) {
            $last = count($merged) - 1;
            $merged[$last] .= "\n\n" . $block;
            continue;
        }

        $merged[] = $block;
    }

    $subevents = [];
    $slot = 1;

    foreach ($merged as $block) {

        $metadata = derive_prose_metadata($block);

        $summary = trim(
            (string)($metadata['summary'] ?? '')
        );

        if ($summary === '') {
            $summary = 'Subevent ' . $slot;
        }

        $subevents[] = [
            'slot' => $slot,
            'summary' => $summary,
            'prose_body' => $block,
        ];

        $slot++;
    }

    return $subevents;
}

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

    $parentEventEntityId = trim($parentEventEntityId);

    if ($parentEventEntityId === '') {
        throw new InvalidArgumentException(
            'parentEventEntityId must be non-empty'
        );
    }

    $results = [];

    foreach ($subevents as $subevent) {

        $slot = (int)($subevent['slot'] ?? 0);

        if ($slot < 1) {
            throw new RuntimeException(
                'Invalid subevent slot'
            );
        }

        $summary = trim(
            (string)($subevent['summary'] ?? '')
        );

        if ($summary === '') {
            throw new RuntimeException(
                'Subevent summary is required'
            );
        }

        $proseBody = trim(
            (string)($subevent['prose_body'] ?? '')
        );

        if ($proseBody === '') {
            throw new RuntimeException(
                'Subevent prose_body is required'
            );
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

function segment_and_persist_prose_subevents(
    PDO $pdo,
    string $parentEventEntityId,
    string $prose
): array {

    $subevents = segment_prose_into_subevents(
        $prose
    );

    if ($subevents === []) {
        return [
            'subevents' => [],
            'persisted' => [],
        ];
    }

    return [
        'subevents' => $subevents,
        'persisted' => persist_segmented_subevents(
            $pdo,
            $parentEventEntityId,
            $subevents
        ),
    ];
}

function normalize_subevent_prose(
    string $prose
): string {

    $prose = str_replace(
        ["\r\n", "\r"],
        "\n",
        $prose
    );

    $prose = preg_replace(
        "/[ \t]+/",
        ' ',
        $prose
    ) ?? '';

    $prose = preg_replace(
        "/\n{3,}/",
        "\n\n",
        $prose
    ) ?? '';

    return trim($prose);
}