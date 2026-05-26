# Manual Calendar Event Resequencing + Canonical Insert Procedure

This procedure documents the temporary manual cleanup workflow for inserting a new canonical calendar event into an existing chronology without disturbing attachment lineage or prose-family linkage.

---

## Core Doctrine

- Preserve existing `calendar_events.id` rows whenever possible.
- Resequence ordering indices instead of cloning rows.
- Update highest sequence values first to avoid collisions.
- `calendar_event_reference_labels.reference_label` must be resequenced alongside chronology.
- Generated columns (example: `event_unique_index`) MUST NOT be directly updated.
- `calendar_event_reference_labels.calendar_event_id` references the physical `calendar_events.id` row.
- `entity_id` remains the canonical semantic authority.
- Temporary manual cleanup may explicitly insert physical `id` values if required.

---

# Phase 1 — Audit Existing Topology

```sql
SELECT
    id,
    entity_id,
    sequence_index,
    event_index,
    event_unique_index,
    summary
FROM calendar_events
WHERE id BETWEEN 2 AND 6
ORDER BY sequence_index;
```

```sql
SELECT
    calendar_event_id,
    prose_family_id,
    reference_label
FROM calendar_event_reference_labels
WHERE calendar_event_id BETWEEN 2 AND 6
ORDER BY calendar_event_id;
```

---

# Phase 2 — Resequence Existing Events

```sql
START TRANSACTION;

UPDATE calendar_events
SET
    sequence_index = 7,
    event_index = 7
WHERE id = 6;

UPDATE calendar_events
SET
    sequence_index = 6,
    event_index = 6
WHERE id = 5;

UPDATE calendar_events
SET
    sequence_index = 5,
    event_index = 5
WHERE id = 4;

UPDATE calendar_events
SET
    sequence_index = 4,
    event_index = 4
WHERE id = 3;

COMMIT;
```

---

# Phase 3 — Shift Prose Family Reference Labels

```sql
START TRANSACTION;

UPDATE calendar_event_reference_labels
SET reference_label = '20250120-f'
WHERE calendar_event_id = 6
  AND prose_family_id = 15
  AND reference_label = '20250120-e';

UPDATE calendar_event_reference_labels
SET reference_label = '20250120-e'
WHERE calendar_event_id = 5
  AND prose_family_id = 14
  AND reference_label = '20250120-d';

UPDATE calendar_event_reference_labels
SET reference_label = '20250120-d'
WHERE calendar_event_id = 4
  AND prose_family_id = 13
  AND reference_label = '20250120-c';

UPDATE calendar_event_reference_labels
SET reference_label = '20250120-c'
WHERE calendar_event_id = 3
  AND prose_family_id = 12
  AND reference_label = '20250120-b';

COMMIT;
```

---

# Phase 4 — Temporary Cleanup Insert with Canonical Physical ID

## Remove accidental auto-increment insert

```sql
DELETE FROM calendar_events
WHERE id = 356
  AND entity_id = 'calendar_event:7';
```

## Verify physical ID is free

```sql
SELECT *
FROM calendar_events
WHERE id = 7;
```

## Insert canonical row

```sql
INSERT INTO calendar_events (
    id,
    entity_id,
    projection_entity_id,
    layer_id,
    summary,
    notes,
    event_id,
    event_index,
    sequence_index,
    projection_id,
    book_time_id
)
VALUES (
    7,
    'calendar_event:7',
    'book_projection_BOOK-001',
    'calendar_layer_event',
    'NEW EVENT SUMMARY HERE',
    'NEW EVENT NOTES HERE',
    7,
    3,
    3,
    1,
    2
);
```

---

# Phase 5 — Attach Event to Temporal Layer

The system now derives chronology from:

- `week_index`
- `day_index`
- `time_index`
- `event_index`
- `sequence_index`

NOT from `chronology_address`.

## Attach inserted event to existing temporal scope

```sql
UPDATE calendar_events
SET
    week_index = 1,
    day_index = 2,
    time_index = 1
WHERE id = 7
  AND entity_id = 'calendar_event:7';
```

---

# Verification Queries

## Verify chronology ordering

```sql
SELECT
    id,
    entity_id,
    sequence_index,
    event_index,
    week_index,
    day_index,
    time_index
FROM calendar_events
WHERE id BETWEEN 2 AND 7
ORDER BY sequence_index;
```

## Verify prose-family labels

```sql
SELECT
    calendar_event_id,
    prose_family_id,
    reference_label
FROM calendar_event_reference_labels
ORDER BY reference_label;
```

---

# Important Notes

- `id` is the physical relational identity.
- `event_id` is semantic event identity.
- `entity_id` is canonical entity authority.
- Manual cleanup may temporarily force all three to align.
- Do not mutate generated columns directly.
- Do not mutate deprecated `chronology_address` if chronology is now derived.
