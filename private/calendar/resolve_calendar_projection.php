<?php


require_once __DIR__ . '/build_calendar_tree.php';

/**
 * Resolve a calendar projection into a validated tree.
 *
 * Enforces:
 * - projection exists
 * - projection is non-empty
 * - coverage: every booked event has a projection row
 * - no duplicate projection rows
 * - no missing required fields
 *
 * @param PDO $pdo
 * @param int|string $projectionId
 * @return array
 * @throws Exception
 */
function resolve_calendar_projection(PDO $pdo, $projectionId): array
{
    // --- 1. Fetch projection rows --------------------------------------------

    $stmt = $pdo->prepare("
        SELECT
            cep.event_id AS id,
            ce.parent_event_id AS parent_id,
            cep.chronology_address
        FROM calendar_event_projections cep
        INNER JOIN calendar_events ce
            ON ce.id = cep.event_id
        WHERE cep.calendar_projection_id = :projection_id
    ");

    $stmt->execute([
        'projection_id' => $projectionId
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 2. Projection must exist & not be empty ------------------------------

    if (!$rows) {
        throw new Exception("Projection {$projectionId} is empty or does not exist");
    }

    // --- 3. Validate coverage against books ----------------------------------

    $stmt = $pdo->prepare("
        SELECT ceb.event_id
        FROM calendar_event_books ceb
        WHERE ceb.calendar_projection_id = :projection_id
    ");

    $stmt->execute([
        'projection_id' => $projectionId
    ]);

    $bookedEventIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($bookedEventIds) {
        $projectedIds = array_column($rows, 'id');

        $missing = array_diff($bookedEventIds, $projectedIds);

        if (!empty($missing)) {
            throw new Exception(
                "Projection {$projectionId} missing rows for booked events: "
                . implode(',', $missing)
            );
        }
    }

    // --- 4. Detect duplicate projection rows ---------------------------------

    $seen = [];

    foreach ($rows as $row) {
        $id = (string)$row['id'];

        if (isset($seen[$id])) {
            throw new Exception("Duplicate projection row for event {$id}");
        }

        $seen[$id] = true;
    }

    // --- 5. Normalize input for tree builder ---------------------------------

    $normalized = [];

    foreach ($rows as $row) {
        if (!isset($row['id'], $row['chronology_address'])) {
            throw new Exception("Malformed projection row");
        }

        $normalized[] = [
            'id' => (string)$row['id'],
            'parent_id' => isset($row['parent_id']) ? (string)$row['parent_id'] : null,
            'chronology_address' => (string)$row['chronology_address'],
        ];
    }

    // --- 6. Delegate to strict tree builder ----------------------------------

    return build_calendar_tree($normalized);
}