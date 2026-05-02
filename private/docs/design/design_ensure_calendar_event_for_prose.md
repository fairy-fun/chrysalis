# ensureCalendarEventForProse Design

## Purpose

Resolve or create a valid `calendar_layer_event` target before prose is attached.

This helper exists because prose must attach only to stable event-layer rows, while calendar hierarchy must remain owned by the calendar system.

---

## Core Rule

```text
chronology_address is a read/query address only.

It must never be used as:
- write identity
- lookup key
- uniqueness authority

Canonical identity is structural:
projection_entity_id + parent_event_id + layer_id + sequence_index
```

---

## Boundary

`ensureCalendarEventForProse` may create or resolve:

- Week
- Day
- Time
- Event

It must return:

```php
[
  'target_entity_id' => 'calendar_event:325',
  'calendar_event_id' => 325,
  'calendar_row_id' => 123,
  'chronology_address' => '3.1.2.1', // derived
]
```

It must not create prose, annotations, limbic facts, suggestions, or projections.

---

## Data Model (Required)

```text
calendar_events
- id (PK)
- entity_id (string)
- event_id (int or auto-increment-safe alternative)
- projection_entity_id (string)
- layer_id (string)
- parent_event_id (nullable FK to calendar_events.id)
- sequence_index (int)  <-- CANONICAL STRUCTURAL INDEX
- time_label_id (nullable string)
- summary (text)
```

### Canonical Index Rule

```text
sequence_index is the canonical structural index.

Legacy columns:
- week_index
- day_index
- time_index
- event_index

are deprecated for identity and must not be used for lookup or uniqueness.

For event-layer rows:
event_index must be kept in sync with sequence_index
until legacy constraints are removed.
```

---

## Required Constraint

Because root week rows use `parent_event_id = NULL`, do not use a plain composite unique constraint.

MySQL allows multiple `NULL` values in UNIQUE constraints, so root identity must be enforced with a functional index.

```sql
CREATE UNIQUE INDEX uniq_calendar_node_structural
ON sxnzlfun_chrysalis.calendar_events (
  projection_entity_id,
  layer_id,
  (COALESCE(parent_event_id, 0)),
  sequence_index
);
```

```text
projection_entity_id + layer_id + parent_scope + sequence_index
where parent_scope = COALESCE(parent_event_id, 0)
```

Note: requires MySQL 8+ (functional indexes).

---

## Input Contract

```json
{
  "projection_entity_id": "book_projection_BOOK-001",
  "week_index": 3,
  "day_index": 1,
  "time_index": 2,
  "time_label_id": "CLASSVAL-TIME-002",
  "event_index": 1,
  "summary": "Shay wakes from a nightmare",
  "event_purpose": "Establish direct physiological evidence for Shay's internal state."
}
```

---

## Output Contract

```json
{
  "status": "ok",
  "calendar_event": {
    "entity_id": "calendar_event:325",
    "event_id": 325,
    "id": 123,
    "layer_id": "calendar_layer_event",
    "chronology_address": "3.1.2.1"
  },
  "created": {
    "week": false,
    "day": false,
    "time": true,
    "event": true
  }
}
```

---

## Core Algorithm

```text
BEGIN TRANSACTION

1. Validate input.
2. Resolve or create week node.
3. Resolve or create day node under week.
4. Resolve or create time node under day.
5. Resolve or create event node under time.
6. Ensure final node is calendar_layer_event.
7. Derive chronology_address from parent chain.
8. Return event-layer entity_id.

COMMIT
```

---

## Lookup Pattern (Canonical)

```php
function find_calendar_node(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentRowId,
    int $sequenceIndex
): ?array {
    $stmt = $pdo->prepare("
        SELECT *
        FROM sxnzlfun_chrysalis.calendar_events
        WHERE projection_entity_id = :projection_entity_id
          AND layer_id = :layer_id
          AND COALESCE(parent_event_id, 0) = COALESCE(:parent_event_id, 0)
          AND sequence_index = :sequence_index
        LIMIT 1
    ");

    $stmt->execute([
        ':projection_entity_id' => $projectionEntityId,
        ':layer_id' => $layerId,
        ':parent_event_id' => $parentRowId,
        ':sequence_index' => $sequenceIndex,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}
```

---

## Insert Pattern (Race-Safe)

```php
function insert_calendar_node(
    PDO $pdo,
    string $projectionEntityId,
    string $layerId,
    ?int $parentRowId,
    int $sequenceIndex,
    string $summary,
    ?string $timeLabelId = null
): array {
    try {
        $eventId = next_calendar_event_id($pdo); // or auto-increment alternative
        $entityId = create_calendar_entity($pdo, $eventId);

        $stmt = $pdo->prepare("
            INSERT INTO sxnzlfun_chrysalis.calendar_events (
                entity_id,
                event_id,
                projection_entity_id,
                layer_id,
                summary,
                parent_event_id,
                sequence_index,
                time_label_id
            ) VALUES (
                :entity_id,
                :event_id,
                :projection_entity_id,
                :layer_id,
                :summary,
                :parent_event_id,
                :sequence_index,
                :time_label_id
            )
        ");

        $stmt->execute([
            ':entity_id' => $entityId,
            ':event_id' => $eventId,
            ':projection_entity_id' => $projectionEntityId,
            ':layer_id' => $layerId,
            ':summary' => $summary,
            ':parent_event_id' => $parentRowId,
            ':sequence_index' => $sequenceIndex,
            ':time_label_id' => $timeLabelId,
        ]);

        return find_calendar_node(
            $pdo,
            $projectionEntityId,
            $layerId,
            $parentRowId,
            $sequenceIndex
        );
    } catch (PDOException $e) {
        // On duplicate key, re-select (idempotent)
        return find_calendar_node(
            $pdo,
            $projectionEntityId,
            $layerId,
            $parentRowId,
            $sequenceIndex
        );
    }
}
```

---

## Chronology Address (Derived)

```php
function build_chronology_address(PDO $pdo, array $node): string
{
    $segments = [];
    $current = $node;

    while ($current !== null) {
        $segments[] = $current['sequence_index'];

        if ($current['parent_event_id'] === null) {
            break;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM sxnzlfun_chrysalis.calendar_events
            WHERE id = :id
        ");

        $stmt->execute([':id' => $current['parent_event_id']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    return implode('.', array_reverse($segments));
}
```

---

## Resolver (Query Layer)

```php
function resolve_calendar_node_by_address(
    PDO $pdo,
    string $projectionEntityId,
    string $address
): ?array {
    $parts = array_map('intval', explode('.', $address));

    $parentId = null;
    $layerMap = [
        0 => 'calendar_layer_week',
        1 => 'calendar_layer_day',
        2 => 'calendar_layer_time',
        3 => 'calendar_layer_event',
    ];

    $current = null;

    foreach ($parts as $depth => $sequenceIndex) {
        $layerId = $layerMap[$depth] ?? null;

        if ($layerId === null) {
            return null;
        }

        $current = find_calendar_node(
            $pdo,
            $projectionEntityId,
            $layerId,
            $parentId,
            $sequenceIndex
        );

        if ($current === null) {
            return null;
        }

        $parentId = (int) $current['id'];
    }

    return $current;
}
```

---

## Event Ensurer

```php
function ensure_calendar_event_for_prose(PDO $pdo, array $body): array
{
    $projectionId = $body['projection_entity_id'];

    $weekIndex = (int) $body['week_index'];
    $dayIndex = (int) $body['day_index'];
    $timeIndex = (int) $body['time_index'];
    $eventIndex = (int) $body['event_index'];

    $timeLabelId = trim($body['time_label_id']);
    $summary = trim($body['summary']);
    $eventPurpose = trim($body['event_purpose'] ?? '');

    if ($weekIndex < 1) throw new InvalidArgumentException('week_index must be positive');
    if ($dayIndex < 1 || $dayIndex > 7) throw new InvalidArgumentException('day_index must be 1..7');
    if ($timeIndex < 1) throw new InvalidArgumentException('time_index must be positive');
    if ($eventIndex < 1) throw new InvalidArgumentException('event_index must be positive');
    if ($summary === '') throw new InvalidArgumentException('summary is required');
    if ($eventPurpose === '') throw new InvalidArgumentException('event_purpose is required');

    $created = [
        'week' => false,
        'day' => false,
        'time' => false,
        'event' => false,
    ];

    $started = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $started = true;
        }

        // WEEK
        $week = find_calendar_node($pdo, $projectionId, 'calendar_layer_week', null, $weekIndex);
        if ($week === null) {
            $week = insert_calendar_node(
                $pdo,
                $projectionId,
                'calendar_layer_week',
                null,
                $weekIndex,
                'Week ' . $weekIndex
            );
            $created['week'] = true;
        }

        // DAY
        $day = find_calendar_node($pdo, $projectionId, 'calendar_layer_day', $week['id'], $dayIndex);
        if ($day === null) {
            $day = insert_calendar_node(
                $pdo,
                $projectionId,
                'calendar_layer_day',
                $week['id'],
                $dayIndex,
                ''
            );
            $created['day'] = true;
        }

        // TIME
        $time = find_calendar_node($pdo, $projectionId, 'calendar_layer_time', $day['id'], $timeIndex);
        if ($time === null) {
            $time = insert_calendar_node(
                $pdo,
                $projectionId,
                'calendar_layer_time',
                $day['id'],
                $timeIndex,
                '',
                $timeLabelId
            );
            $created['time'] = true;
        }

        // EVENT
        $event = find_calendar_node($pdo, $projectionId, 'calendar_layer_event', $time['id'], $eventIndex);
        if ($event === null) {
            $event = insert_calendar_node(
                $pdo,
                $projectionId,
                'calendar_layer_event',
                $time['id'],
                $eventIndex,
                $summary
            );
            $created['event'] = true;
        }

        if ($event['layer_id'] !== 'calendar_layer_event') {
            throw new RuntimeException('Resolved target is not an event-layer row');
        }

        if ($started) {
            $pdo->commit();
        }

        return [
            'status' => 'ok',
            'calendar_event' => [
                'id' => (int) $event['id'],
                'event_id' => (int) $event['event_id'],
                'entity_id' => $event['entity_id'],
                'layer_id' => $event['layer_id'],
                'chronology_address' => build_chronology_address($pdo, $event),
            ],
            'created' => $created,
        ];
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
```

---

## Final Rule

```text
Write path:
- uses structural identity only

Read/query path:
- may accept chronology_address
- must resolve structurally

Chronology strings are never authoritative.
```