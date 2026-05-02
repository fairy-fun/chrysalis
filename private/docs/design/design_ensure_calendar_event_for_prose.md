# ensureCalendarEventForProse Design

## Purpose

Resolve or create a valid `calendar_layer_event` target before prose is attached.

This helper exists because prose must attach only to stable event-layer rows, while calendar hierarchy must remain owned by the calendar system.

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
  'chronology_address' => '3.1.2.1',
]
```
It must not create prose, annotations, limbic facts, suggestions, or projections.

### Input Contract
```json
{
"projection_entity_id": "book_projection_BOOK-001",
"week_index": 3,
"day_index": 1,
"time_label_id": "CLASSVAL-TIME-002",
"event_index": 1,
"summary": "Shay wakes from a nightmare",
"event_purpose": "Establish direct physiological evidence for Shay's internal state."
}
```

#### For subevents:
```json
{
"subevent_index": 1,
"parent_event_address": "3.1.2.1"
}
```

#### Output Contract
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

#### Core Algorithm
```text
BEGIN TRANSACTION

1. Validate input.
2. Resolve or create week node.
3. Resolve or create day node under week.
4. Resolve or create time node under day.
5. Resolve or create event node under time.
6. Ensure final node is calendar_layer_event.
7. Ensure final node has stable chronology_address.
8. Return event-layer entity_id.

COMMIT
```

Calendar docs already establish that prose must attach only to event-layer rows and that week/day/time/event structure belongs to calendar hierarchy, not prose.

#### Required Helper Functions
```php
function ensure_calendar_event_for_prose(PDO $pdo, array $body): array
```
Internally call:
```php
function ensure_calendar_week(PDO $pdo, int $weekIndex, string $projectionEntityId): array
function ensure_calendar_day(PDO $pdo, array $week, int $dayIndex): array
function ensure_calendar_time(PDO $pdo, array $day, string $timeLabelId): array
function ensure_calendar_event(PDO $pdo, array $time, int $eventIndex, string $summary): array
```

#### Idempotent SQL Pattern

Each layer should follow the same shape:
```php
function find_calendar_node(
PDO $pdo,
string $layerId,
?int $parentRowId,
string $chronologyAddress
): ?array {
$stmt = $pdo->prepare("
SELECT *
FROM sxnzlfun_chrysalis.calendar_events
WHERE layer_id = :layer_id
AND chronology_address = :chronology_address
AND (
(:parent_event_id IS NULL AND parent_event_id IS NULL)
OR parent_event_id = :parent_event_id
)
LIMIT 1
");

    $stmt->execute([
        ':layer_id' => $layerId,
        ':chronology_address' => $chronologyAddress,
        ':parent_event_id' => $parentRowId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}
```

#### ID Allocation

Use the existing current convention:
```sql
SELECT COALESCE(MAX(event_id), 0) + 1 AS next_event_id
FROM sxnzlfun_chrysalis.calendar_events;
```
In PHP:
```php
function next_calendar_event_id(PDO $pdo): int
{
$stmt = $pdo->query("
SELECT COALESCE(MAX(event_id), 0) + 1
FROM sxnzlfun_chrysalis.calendar_events
FOR UPDATE
");

    return (int) $stmt->fetchColumn();
}
```
Because this runs inside a transaction, `FOR UPDATE` helps prevent two requests from claiming the same next ID.

### Entity Creation
```php
function create_calendar_entity(PDO $pdo, int $eventId): string
{
$entityId = 'calendar_event:' . $eventId;

    $stmt = $pdo->prepare("
        INSERT INTO sxnzlfun_chrysalis.entities (
            id,
            entity_type_id
        ) VALUES (
            :id,
            'entity_type_calendar_event'
        )
    ");

    $stmt->execute([':id' => $entityId]);

    return $entityId;
}
```
### Generic Insert Helper
```php
function insert_calendar_node(
PDO $pdo,
string $layerId,
?int $parentRowId,
string $chronologyAddress,
string $summary,
?string $timeLabelId = null
): array {
$eventId = next_calendar_event_id($pdo);
$entityId = create_calendar_entity($pdo, $eventId);

    $stmt = $pdo->prepare("
        INSERT INTO sxnzlfun_chrysalis.calendar_events (
            entity_id,
            event_id,
            layer_id,
            summary,
            chronology_address,
            parent_event_id,
            time_label_id
        ) VALUES (
            :entity_id,
            :event_id,
            :layer_id,
            :summary,
            :chronology_address,
            :parent_event_id,
            :time_label_id
        )
    ");

    $stmt->execute([
        ':entity_id' => $entityId,
        ':event_id' => $eventId,
        ':layer_id' => $layerId,
        ':summary' => $summary,
        ':chronology_address' => $chronologyAddress,
        ':parent_event_id' => $parentRowId,
        ':time_label_id' => $timeLabelId,
    ]);

    $rowId = (int) $pdo->lastInsertId();

    return [
        'id' => $rowId,
        'event_id' => $eventId,
        'entity_id' => $entityId,
        'layer_id' => $layerId,
        'chronology_address' => $chronologyAddress,
    ];
}
```

#### Event Ensurer
```php
function ensure_calendar_event_for_prose(PDO $pdo, array $body): array
{
$weekIndex = (int) $body['week_index'];
$dayIndex = (int) $body['day_index'];
$timeIndex = (int) $body['time_index'];
$eventIndex = (int) $body['event_index'];
$timeLabelId = trim($body['time_label_id']);
$summary = trim($body['summary']);
$eventPurpose = trim($body['event_purpose'] ?? '');

    if ($weekIndex < 1) {
        throw new InvalidArgumentException('week_index must be positive');
    }

    if ($dayIndex < 1 || $dayIndex > 7) {
        throw new InvalidArgumentException('day_index must be 1..7');
    }

    if ($timeIndex < 1) {
        throw new InvalidArgumentException('time_index must be positive');
    }

    if ($eventIndex < 1) {
        throw new InvalidArgumentException('event_index must be positive');
    }

    if ($summary === '') {
        throw new InvalidArgumentException('summary is required');
    }

    if ($eventPurpose === '') {
        throw new InvalidArgumentException('event_purpose is required');
    }

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

        $weekAddress = (string) $weekIndex;

        $week = find_calendar_node(
            $pdo,
            'calendar_layer_week',
            null,
            $weekAddress
        );

        if ($week === null) {
            $week = insert_calendar_node(
                $pdo,
                'calendar_layer_week',
                null,
                $weekAddress,
                'Week ' . $weekIndex
            );
            $created['week'] = true;
        }

        $dayAddress = $weekIndex . '.' . $dayIndex;

        $day = find_calendar_node(
            $pdo,
            'calendar_layer_day',
            (int) $week['id'],
            $dayAddress
        );

        if ($day === null) {
            $day = insert_calendar_node(
                $pdo,
                'calendar_layer_day',
                (int) $week['id'],
                $dayAddress,
                ''
            );
            $created['day'] = true;
        }

        $timeAddress = $weekIndex . '.' . $dayIndex . '.' . $timeIndex;

        $time = find_calendar_node(
            $pdo,
            'calendar_layer_time',
            (int) $day['id'],
            $timeAddress
        );

        if ($time === null) {
            $time = insert_calendar_node(
                $pdo,
                'calendar_layer_time',
                (int) $day['id'],
                $timeAddress,
                '',
                $timeLabelId
            );
            $created['time'] = true;
        }

        $eventAddress = $weekIndex . '.' . $dayIndex . '.' . $timeIndex . '.' . $eventIndex;

        $event = find_calendar_node(
            $pdo,
            'calendar_layer_event',
            (int) $time['id'],
            $eventAddress
        );

        if ($event === null) {
            $event = insert_calendar_node(
                $pdo,
                'calendar_layer_event',
                (int) $time['id'],
                $eventAddress,
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
                'chronology_address' => $event['chronology_address'],
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

#### Important Correction

This helper assumes `time_index` is supplied directly. That is cleaner than deriving it from `time_label_id`, because our calendar docs distinguish:
``` text
time_index = structural order
time_label_id = canonical label
```

So Postman should send both:
``` json
"time_index": 2,
"time_label_id": "CLASSVAL-TIME-002"
```
### Later Wrapper Flow

Once this helper exists, our prose wrapper becomes:
``` php
$calendar = ensure_calendar_event_for_prose($pdo, $body['calendar_context']);

$body['prose']['projection']['target_entity_id']
= $calendar['calendar_event']['entity_id'];

return create_prose_draft($pdo, $body['prose']);
```
### Do Not Do This

Do not put this logic inside raw `create_prose_draft()`.

That function should stay dumb and safe:
``` text
validate existing target
insert prose
insert projection
insert annotations
```

`ensureCalendarEventForProse` is the missing orchestration layer.