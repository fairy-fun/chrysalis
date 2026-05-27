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


---

# Phase 4A — Replace Accidental Auto-Increment Insert with Canonical Physical ID

Sometimes MySQL will auto-generate a high physical `id` (example: `356`) even when the intended semantic identity is:

- `id = 7`
- `event_id = 7`
- `entity_id = calendar_event:7`

For temporary manual cleanup workflows, it is acceptable to:

1. delete the accidental auto-increment row
2. explicitly reinsert using the canonical physical ID

This is NOT recommended as permanent migration doctrine, but is acceptable during controlled repair operations.

---

## Remove accidental auto-increment insert

```sql
DELETE FROM calendar_events
WHERE id = 356
  AND entity_id = 'calendar_event:7';
```

---

## Verify physical ID is free

```sql
SELECT *
FROM calendar_events
WHERE id = 7;
```

Expected result:

- zero rows returned

---

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

## Resulting topology

| field | value |
|---|---|
| id | 7 |
| event_id | 7 |
| entity_id | calendar_event:7 |

This restores alignment between:

- physical identity
- semantic identity
- canonical entity authority

during temporary manual chronology cleanup.

---

# Calendar Chronology Repair — Canonical Insertion + Temporal Resequencing

## Repair Context

Temporary live chronology cleanup was performed under the following controlled doctrine:

- `entity_id` is canonical semantic authority
- `event_id` is semantic chronology identity
- `calendar_events.id` is physical relational identity

During repair, canonical alignment was intentionally enforced:

```text
id == event_id
entity_id == calendar_event:<id>
```

This is temporary cleanup doctrine, not final migration doctrine.

---

# Important Schema Doctrine

## calendar_events

Critical fields:

```sql
id bigint auto_increment PRIMARY KEY
entity_id varchar(64)
event_id bigint NOT NULL
sequence_index int NOT NULL
event_index tinyint unsigned
event_unique_index STORED GENERATED
week_index tinyint unsigned
day_index tinyint unsigned
time_index tinyint unsigned
chronology_address varchar(32)
```

## Generated Column Doctrine

Never directly mutate:

```text
event_unique_index
```

Only mutate:

```text
sequence_index
event_index
```

Generated values recalculate automatically.

---

# Chronology Doctrine

`chronology_address` is no longer authoritative.

Chronology is now derived from:

```text
week_index
day_index
time_index
event_index
sequence_index
```

Do not use `chronology_address` for active chronology semantics.

Legacy values may remain during repair cleanup.

---

# Initial Chronology State

Before resequencing:

| id | entity_id | sequence_index | event_index |
|---|---|---:|---:|
| 2 | calendar_event:2 | 2 | 2 |
| 3 | calendar_event:3 | 3 | 3 |
| 4 | calendar_event:4 | 4 | 4 |
| 5 | calendar_event:5 | 5 | 5 |
| 6 | calendar_event:6 | 6 | 6 |

---

# Chronology Resequence

Existing events `3–6` were shifted upward to create insertion space.

Result:

| id | sequence_index | event_index |
|---|---:|---:|
| 3 | 4 | 4 |
| 4 | 5 | 5 |
| 5 | 6 | 6 |
| 6 | 7 | 7 |

This created a clean insertion slot at:

```text
sequence_index = 3
event_index = 3
```

---

# Canonical Event Reinsertion

An accidental insert created:

```text
id = 356
```

The row was deleted.

The event was then reinserted canonically as:

| field | value |
|---|---|
| id | 7 |
| entity_id | calendar_event:7 |
| event_id | 7 |
| sequence_index | 3 |
| event_index | 3 |

---

# Temporal Attachment Repair

The neighboring chronology established the intended temporal scope:

```text
week_index = 1
day_index = 2
time_index = 1
```

The inserted event was attached via:

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

# Prose Family Attachment

A new prose-family attachment row was created:

| calendar_event_id | prose_family_id | reference_label |
|---|---:|---|
| 7 | 16 | 20250120-ab |

Important doctrine:

```text
calendar_event_reference_labels.calendar_event_id
references calendar_events.id
NOT event_id
```

---

# Canonical event_id Repair

Rows `4–6` contained drifted semantic identifiers:

| id | event_id |
|---|---:|
| 4 | 346 |
| 5 | 347 |
| 6 | 348 |

They were repaired to canonical alignment:

```sql
UPDATE calendar_events
SET event_id = 4
WHERE id = 4
  AND entity_id = 'calendar_event:4';

UPDATE calendar_events
SET event_id = 5
WHERE id = 5
  AND entity_id = 'calendar_event:5';

UPDATE calendar_events
SET event_id = 6
WHERE id = 6
  AND entity_id = 'calendar_event:6';
```

---

# Event Index Recalibration

The active chronology slot originally lacked:

```text
1.2.1.1
```

Repair work determined that stale chronology artifacts were occupying the slot.

Legacy artifact rows:

| id | entity_id |
|---|---|
| 345 | calendar_event:345 |
| 346 | calendar_event:346 |

Characteristics:

- invalid semantic alignment
- stale chronology addresses
- sequence collisions
- obsolete temporal occupation

They were removed:

```sql
DELETE FROM calendar_events
WHERE id IN (345,346);
```

---

# Temporal Reattachment of calendar_event:2

`calendar_event:2` had become detached from the repaired temporal scope.

It was reattached:

```sql
UPDATE calendar_events
SET
    week_index = 1,
    day_index = 2,
    time_index = 1,
    event_index = 1
WHERE id = 2
  AND entity_id = 'calendar_event:2';
```

---

# Legacy chronology_address Cleanup

Legacy chronology strings were normalized out:

```sql
UPDATE calendar_events
SET chronology_address = NULL
WHERE id IN (2,3,7);
```

---

# Temporal Attachment Repair for Events 4–6

Rows `4–6` were chronology-valid but temporally unattached.

They were attached into the repaired chronology slot:

```sql
UPDATE calendar_events
SET
    week_index = 1,
    day_index = 2,
    time_index = 1,
    event_index = 4
WHERE id = 4
  AND entity_id = 'calendar_event:4';

UPDATE calendar_events
SET
    week_index = 1,
    day_index = 2,
    time_index = 1,
    event_index = 5
WHERE id = 5
  AND entity_id = 'calendar_event:5';

UPDATE calendar_events
SET
    week_index = 1,
    day_index = 2,
    time_index = 1,
    event_index = 6
WHERE id = 6
  AND entity_id = 'calendar_event:6';
```

---

# Final Repaired Chronology

Final repaired temporal ordering:

| entity_id | chronology |
|---|---|
| calendar_event:2 | 1.2.1.1 |
| calendar_event:7 | 1.2.1.2 |
| calendar_event:3 | 1.2.1.3 |
| calendar_event:4 | 1.2.1.4 |
| calendar_event:5 | 1.2.1.5 |
| calendar_event:6 | 1.2.1.6 |

Chronology semantics are now fully derived from:

```text
week_index
day_index
time_index
event_index
sequence_index
```

with no remaining reliance on `chronology_address`.

## Projection Membership Repair During Event Fixup

### Canonical Projection Topology

Surviving semantic events must belong to the canonical publication projections.

Current canonical projections:

| Projection ID | Projection Code              | Purpose                    |
|---|---|---|
| 1 | `book_projection_BOOK-001` | Canonical Book 1 projection |
| 5 | `realtime_projection_main` | Real-time continuity projection |

---

## Repair Doctrine

When repairing or normalizing a `calendar_event`, the repair process must ensure:

- the event belongs to the canonical book projection
- the event belongs to the canonical real-time projection
- the event is not stranded in temporary/non-export-only timeline projections

This normalization restores:

- export capability
- canonical prose elevation workflows
- semantic publication continuity
- stable projection discoverability

---

## Required Membership Repair SQL

Example for `calendar_event.id = 7`:

```sql
INSERT INTO calendar_event_projection_membership
    (calendar_event_id, projection_id)
SELECT 7, 1
WHERE NOT EXISTS (
    SELECT 1
    FROM calendar_event_projection_membership
    WHERE calendar_event_id = 7
      AND projection_id = 1
);

INSERT INTO calendar_event_projection_membership
    (calendar_event_id, projection_id)
SELECT 7, 5
WHERE NOT EXISTS (
    SELECT 1
    FROM calendar_event_projection_membership
    WHERE calendar_event_id = 7
      AND projection_id = 5
);
```

---

## Concrete Projection Realization Repair

### Important Operational Reality

`calendar_event_projection_membership` alone is currently NOT sufficient for export workflows.

The live workflow resolver still depends on concrete rows in:

```text
calendar_event_projections
```

A repaired event may therefore:

- appear attached to projections semantically
- but still fail export elevation workflows

if no concrete projection realization rows exist.

Typical runtime symptom:

```text
Projection is not export-capable
```

even when canonical membership rows already exist.

---

## Live Canonical Pattern

Observed live projection topology:

| calendar_event_id | calendar_projection_id | projection_address | chronology_address |
|---|---:|---|---|
| 2 | 1 | NULL | 1.2.1.2 |
| 3 | 1 | 1.2.1.3 | 1.2.1.3 |
| 3 | 5 | 1.2.1.3 | 1.2.1.3 |

This demonstrates that active workflows still resolve through:

```text
calendar_event_projections
```

rather than solely through:

```text
calendar_event_projection_membership
```

---

## Required Concrete Projection Repair

After membership normalization, create concrete projection realization rows.

Example for `calendar_event.id = 7`:

```sql
INSERT INTO calendar_event_projections
(
    calendar_event_id,
    calendar_projection_id,
    projection_address,
    chronology_address,
    projection_sequence,
    created_at,
    updated_at
)
SELECT
    7,
    1,
    '3.1.1.1',
    '3.1.1.1',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM calendar_event_projections
    WHERE calendar_event_id = 7
      AND calendar_projection_id = 1
);

INSERT INTO calendar_event_projections
(
    calendar_event_id,
    calendar_projection_id,
    projection_address,
    chronology_address,
    projection_sequence,
    created_at,
    updated_at
)
SELECT
    7,
    5,
    '3.1.1.1',
    '3.1.1.1',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM calendar_event_projections
    WHERE calendar_event_id = 7
      AND calendar_projection_id = 5
);
```

---

## Current Ontology Reality

At present, the live system still contains two distinct projection layers:

```text
calendar_event_projection_membership
    = semantic projection membership topology

calendar_event_projections
    = operational projection realization layer
```

Repair workflows must currently normalize BOTH layers.

---

## Export Workflow Dependency

Canonical prose export elevation currently depends on:

```text
calendar_event
    -> calendar_event_projections
        -> calendar_projection_id
            -> export-capable projection
```

Therefore repaired events lacking concrete projection rows may fail:

- prose export elevation
- canonical export draft assignment
- projection export workflows

even when semantic membership already exists.