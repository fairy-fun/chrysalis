<?php

declare(strict_types=1);

require_once __DIR__ . '/prose_metadata_deriver.php';
require_once __DIR__ . '/../calendar/calendar_subevent_service.php';

/**
 * Deterministic prose subevent segmentation runtime.
 *
 * Doctrine:
 * - segmentation must be replay-safe
 * - slot ordering is canonical
 * - client identity derives ONLY from parent chronology identity + slot
 * - segmentation stability matters more than fragmentation detail
 *
 * This implementation intentionally begins conservative:
 * - paragraph-group segmentation
 * - continuity pressure defaults toward merge
 * - deterministic ordering
 *
 * Future semantic segmentation layers may refine boundaries,
 * but must preserve deterministic replay guarantees.
 */

/**
 * Segment prose into deterministic runtime subevents.
 *
 * Return shape:
 *
 * [
 *     [
 *         'slot' => 1,
 *         'summary' => '...',
 *         'prose_body' => '...',
 *     ],
 * ]
 */
function segment_prose_into_subevents(
    string $prose
): array {

    $normalized = normalize_subevent_prose($prose);

    if ($normalized === '') {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical first-pass segmentation
    |--------------------------------------------------------------------------
    |
    | Current doctrine:
    |
    | - double line breaks are candidate structural boundaries
    | - continuity pressure merges weak fragments
    | - ordering is deterministic
    |
    */

    $candidateBlocks = preg_split(
        "/\n{2,}/",
        $normalized
    );

    if (!is_array($candidateBlocks)) {
        return [];
    }

    $candidateBlocks = array_values(
        array_filter(
            array_map(
                static fn (string $v): string => trim($v),
                $candidateBlocks
            ),
            static fn (string $v): bool => $v !== ''
        )
    );

    /*
    |--------------------------------------------------------------------------
    | Continuity pressure
    |--------------------------------------------------------------------------
    |
    | Very small fragments are merged forward to preserve
    | chronology identity stability.
    |
    */

    $mergedBlocks = [];

    foreach ($candidateBlocks as $block) {

        $wordCount = count(
            preg_split('/\s+/', trim($block)) ?: []
        );

        $isWeakFragment = $wordCount < 40;

        if (
            $isWeakFragment &&
            !empty($mergedBlocks)
        ) {
            $lastIndex = count($mergedBlocks) - 1;

            $mergedBlocks[$lastIndex] .= "\n\n" . $block;

            continue;
        }

        $mergedBlocks[] = $block;
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical slot generation
    |--------------------------------------------------------------------------
    */

    $subevents = [];

    $slot = 1;

    foreach ($mergedBlocks as $block) {

        $metadata = derive_prose_metadata($block);

        $summary = $metadata['summary']
            ?? ('Subevent ' . $slot);

        $summary = trim($summary);

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

/**
 * Canonical replay-safe runtime identity.
 *
 * Identity derives ONLY from:
 * - parent chronology identity
 * - canonical slot ordering
 *
 * Never from:
 * - prose text
 * - hashes
 * - planner ids
 * - rendering state
 */
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

/**
 * Persist deterministic segmented subevents.
 *
 * Persistence doctrine:
 * - slot ordering is precomputed
 * - persistence does not determine chronology
 * - client_id guarantees replay-safe idempotency
 */
function persist_segmented_subevents(
    PDO $pdo,
    string $parentEventEntityId,
    array $subevents
): array {

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

/**
 * Full deterministic segmentation + persistence pipeline.
 */
function segment_and_persist_prose_subevents(
    PDO $pdo,
    string $parentEventEntityId,
    string $prose
): array {

    $subevents = segment_prose_into_subevents(
        $prose
    );

    return [
        'subevents' => $subevents,
        'persisted' => persist_segmented_subevents(
            $pdo,
            $parentEventEntityId,
            $subevents
        ),
    ];
}

/**
 * Normalize prose into deterministic replay-safe form.
 */
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