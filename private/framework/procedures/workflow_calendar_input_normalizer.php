<?php

declare(strict_types=1);

require_once __DIR__ . '/workflow_value_resolver.php';

/**
 * --------------------------------------------------------------------------
 * Calendar workflow input normalizer
 * --------------------------------------------------------------------------
 *
 * Purpose
 * -------
 *
 * Converts human-oriented Book chronology input into canonical workflow
 * primitives before any locality resolution occurs.
 *
 * This layer is intentionally:
 *
 * - SQL-light
 * - ontology-safe
 * - deterministic
 * - audit-friendly
 *
 * It must NOT:
 *
 * - reconstruct chronology locality
 * - traverse projection topology
 * - infer chronology containers
 * - use chronology_address
 * - inspect recursive ancestry
 * - derive event identity
 * - generate SQL predicates
 *
 * Canonical output primitives:
 *
 *   projection_id
 *   week_index
 *   day_index
 *   time_index
 *
 * Downstream workflows remain responsible for:
 *
 *   tuple -> canonical book_time_id resolution
 *
 * via:
 *
 *   resolve_calendar_book_time_id()
 *
 * --------------------------------------------------------------------------
 */

function fw_execute_workflow_calendar_normalize_book_event_input(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $payload = fw_resolve_workflow_value(
        $action['payload'] ?? [],
        $input,
        $context
    );

    /*
    |--------------------------------------------------------------------------
    | Raw input
    |--------------------------------------------------------------------------
    */

    $projectionInput = trim((string)(
        $payload['projection'] ??
        $payload['book'] ??
        ''
    ));

    $weekInput = trim((string)(
        $payload['week'] ??
        ''
    ));

    $dayInput = trim((string)(
        $payload['day'] ??
        ''
    ));

    $timeInput = trim((string)(
        $payload['time'] ??
        ''
    ));

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($projectionInput === '') {

        throw new RuntimeException(
            'Book projection input is required'
        );
    }

    if ($weekInput === '') {

        throw new RuntimeException(
            'Book week input is required'
        );
    }

    if ($dayInput === '') {

        throw new RuntimeException(
            'Book day input is required'
        );
    }

    if ($timeInput === '') {

        throw new RuntimeException(
            'Book time input is required'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize projection token
    |--------------------------------------------------------------------------
    |
    | Allowed examples:
    |
    |   1
    |   Book 1
    |   book1
    |   BOOK-1
    |
    | Canonical lookup authority remains:
    |
    |   calendar_projections.id
    |
    */

    $normalizedProjectionToken = strtolower(
        preg_replace('/[^a-z0-9]/', '', $projectionInput)
    );

    $projectionId = null;

    if (ctype_digit($normalizedProjectionToken)) {

        $projectionId = (int)$normalizedProjectionToken;

    } elseif (
        preg_match('/^book([0-9]+)$/', $normalizedProjectionToken, $matches)
    ) {

        $projectionId = (int)$matches[1];
    }

    if (!is_int($projectionId) || $projectionId < 1) {

        throw new RuntimeException(
            'Unable to normalize Book projection input'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical projection validation
    |--------------------------------------------------------------------------
    */

    $projectionStmt = $pdo->prepare("
        SELECT
            id,
            entity_id,
            projection_type_id,
            projection_code
        FROM calendar_projections
        WHERE id = :id
          AND projection_type_id = 'projection_type_book'
        LIMIT 1
    ");

    $projectionStmt->execute([
        ':id' => $projectionId,
    ]);

    $projection = $projectionStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($projection)) {

        throw new RuntimeException(
            'Canonical Book projection does not exist'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize week index
    |--------------------------------------------------------------------------
    |
    | Allowed:
    |
    |   1
    |   Week 1
    |   week1
    |
    */

    $weekIndex = fw_normalize_calendar_index_value(
        $weekInput,
        'week'
    );

    /*
    |--------------------------------------------------------------------------
    | Normalize time index
    |--------------------------------------------------------------------------
    |
    | Allowed:
    |
    |   3
    |   Time 3
    |   time3
    |
    */

    $timeIndex = fw_normalize_calendar_index_value(
        $timeInput,
        'time'
    );

    /*
    |--------------------------------------------------------------------------
    | Normalize day index
    |--------------------------------------------------------------------------
    |
    | Canonical Book chronology doctrine:
    |
    |   Monday    => 1
    |   Tuesday   => 2
    |   Wednesday => 3
    |   Thursday  => 4
    |   Friday    => 5
    |   Saturday  => 6
    |   Sunday    => 7
    |
    */

    $dayIndex = fw_normalize_calendar_day_index(
        $dayInput
    );

    /*
    |--------------------------------------------------------------------------
    | Explicit ontology protection
    |--------------------------------------------------------------------------
    */

    $forbiddenKeys = [

        'chronology_address',

        'sequence_index',

        'parent_event_id',

        'week_id',

        'day_id',

        'book_time_id',
    ];

    foreach ($forbiddenKeys as $key) {

        if (array_key_exists($key, $payload)) {

            throw new RuntimeException(
                'Forbidden ontology field detected: ' . $key
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical normalized output
    |--------------------------------------------------------------------------
    */

    return [

        'success' => true,

        'context' => array_merge(
            $context,
            [

                'calendar_normalized_input' => [

                    'projection_id' => $projectionId,

                    'week_index' => $weekIndex,

                    'day_index' => $dayIndex,

                    'time_index' => $timeIndex,
                ],

                'calendar_projection' => $projection,
            ]
        ),
    ];
}

/**
 * --------------------------------------------------------------------------
 * Normalize integer chronology index
 * --------------------------------------------------------------------------
 */

function fw_normalize_calendar_index_value(
    string $value,
    string $prefix
): int {

    $normalized = strtolower(trim($value));

    $normalized = preg_replace(
        '/[^a-z0-9]/',
        '',
        $normalized
    );

    if (str_starts_with($normalized, $prefix)) {

        $normalized = substr(
            $normalized,
            strlen($prefix)
        );
    }

    if (
        !ctype_digit($normalized)
        || (int)$normalized < 1
    ) {

        throw new RuntimeException(
            'Invalid ' . $prefix . ' index'
        );
    }

    return (int)$normalized;
}

/**
 * --------------------------------------------------------------------------
 * Normalize canonical Book day index
 * --------------------------------------------------------------------------
 */

function fw_normalize_calendar_day_index(
    string $value
): int {

    $normalized = strtolower(trim($value));

    $normalized = preg_replace(
        '/[^a-z]/',
        '',
        $normalized
    );

    $map = [

        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 7,
    ];

    if (isset($map[$normalized])) {
        return $map[$normalized];
    }

    /*
    |--------------------------------------------------------------------------
    | Numeric fallback
    |--------------------------------------------------------------------------
    */

    $numeric = preg_replace(
        '/[^0-9]/',
        '',
        strtolower(trim($value))
    );

    if (
        ctype_digit($numeric)
        && (int)$numeric >= 1
        && (int)$numeric <= 7
    ) {

        return (int)$numeric;
    }

    throw new RuntimeException(
        'Invalid canonical Book day input'
    );
}