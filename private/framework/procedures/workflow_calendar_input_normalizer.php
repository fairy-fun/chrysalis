<?php

declare(strict_types=1);

require_once __DIR__ . '/workflow_value_resolver.php';

/**
 * Calendar workflow input normalizer.
 *
 * Converts human-oriented Book chronology input into canonical workflow
 * primitives before locality resolution occurs.
 *
 * This layer is intentionally SQL-light, deterministic, and ontology-safe.
 * It must not reconstruct chronology locality, infer containers, use
 * chronology_address, inspect recursive ancestry, derive event identity, or
 * generate SQL predicates.
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

    $projectionInput = trim((string)(
        $payload['projection'] ??
        $payload['projection_id'] ??
        $payload['book'] ??
        ''
    ));

    $weekInput = trim((string)(
        $payload['week'] ??
        $payload['week_index'] ??
        ''
    ));

    $dayInput = trim((string)(
        $payload['day'] ??
        $payload['day_index'] ??
        ''
    ));

    $timeInput = trim((string)(
        $payload['time'] ??
        $payload['time_index'] ??
        ''
    ));

    if ($projectionInput === '') {
        throw new RuntimeException('Book projection input is required');
    }

    if ($weekInput === '') {
        throw new RuntimeException('Book week input is required');
    }

    if ($dayInput === '') {
        throw new RuntimeException('Book day input is required');
    }

        if ($timeInput === '') {
            throw new RuntimeException('Book time input is required');
        }

        $projectionCode = fw_normalize_calendar_book_projection_code(
            $projectionInput
        );

        throw new RuntimeException(
            'DEBUG projectionCode=' .
            var_export($projectionCode, true)
        );

    /*$projectionCode = fw_normalize_calendar_book_projection_code(
        $projectionInput
    );*/

    $projectionStmt = $pdo->prepare('
        SELECT
            id,
            entity_id,
            projection_type_id,
            projection_code
        FROM calendar_projections
        WHERE projection_type_id = :projection_type_id
          AND projection_code = :projection_code
        LIMIT 1
    ');

    $projectionStmt->execute([
        'projection_type_id' => 'projection_type_book',
        'projection_code' => $projectionCode,
    ]);

    $projection = $projectionStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($projection)) {
        throw new RuntimeException(
            'Canonical Book projection does not exist'
        );
    }

    $weekIndex = fw_normalize_calendar_index_value(
        $weekInput,
        'week'
    );

    $dayIndex = fw_normalize_calendar_day_index(
        $dayInput
    );

    $timeIndex = fw_normalize_calendar_index_value(
        $timeInput,
        'time'
    );

    return [
        'success' => true,
        'context' => array_merge(
            $context,
            [
                'projection' => $projection,
                'calendar_normalized_input' => [
                    'projection_id' => (int)$projection['id'],
                    'projection_code' => $projection['projection_code'],
                    'week_index' => $weekIndex,
                    'day_index' => $dayIndex,
                    'time_index' => $timeIndex,
                ],
            ]
        ),
    ];
}

function fw_normalize_calendar_book_projection_code(string $value): string
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        throw new RuntimeException(
            'Book projection input is required'
        );
    }

    if (preg_match('/^book_projection_BOOK-[0-9]{3}$/', $trimmed) === 1) {
        return $trimmed;
    }

    if (preg_match('/^BOOK-[0-9]{3}$/', $trimmed) === 1) {
        return 'book_projection_' . $trimmed;
    }

    $normalized = strtolower(
        preg_replace('/[^a-z0-9]/', '', $trimmed) ?? ''
    );

    $bookNumber = null;

    if (ctype_digit($normalized)) {
        $bookNumber = (int)$normalized;
    } elseif (preg_match('/^book([0-9]+)$/', $normalized, $matches) === 1) {
        $bookNumber = (int)$matches[1];
    }

    if (!is_int($bookNumber) || $bookNumber < 1) {
        throw new RuntimeException(
            'Unable to normalize Book projection input'
        );
    }

    return 'book_projection_BOOK-' . str_pad(
        (string)$bookNumber,
        3,
        '0',
        STR_PAD_LEFT
    );
}

function fw_normalize_calendar_index_value(
    string $value,
    string $prefix
): int {

    $normalized = strtolower(trim($value));

    $normalized = preg_replace(
        '/[^a-z0-9]/',
        '',
        $normalized
    ) ?? '';

    if (str_starts_with($normalized, $prefix)) {
        $normalized = substr(
            $normalized,
            strlen($prefix)
        );
    }

    if (!ctype_digit($normalized) || (int)$normalized < 1) {
        throw new RuntimeException(
            'Invalid ' . $prefix . ' index'
        );
    }

    return (int)$normalized;
}

function fw_normalize_calendar_day_index(string $value): int
{
    $normalized = strtolower(trim($value));

    $normalized = preg_replace(
        '/[^a-z]/',
        '',
        $normalized
    ) ?? '';

    $map = [
        'sunday' => 1,
        'monday' => 2,
        'tuesday' => 3,
        'wednesday' => 4,
        'thursday' => 5,
        'friday' => 6,
        'saturday' => 7,
    ];

    if (isset($map[$normalized])) {
        return $map[$normalized];
    }

    $numeric = preg_replace(
        '/[^0-9]/',
        '',
        strtolower(trim($value))
    ) ?? '';

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
