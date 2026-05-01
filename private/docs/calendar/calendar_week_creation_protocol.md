# Calendar Event Creation Protocol — Week Construction

## Purpose

Define the **strict, repeatable procedure** for constructing a complete calendar week in the `calendar_events` table, ensuring:

* Hierarchical integrity (week → day → time → event)
* Compatibility with generated columns
* Compliance with foreign key constraints
* Valid targets for prose attachment

This protocol is **mandatory** for all manual inserts and migration scripts.

---

## Core Principles

### 1. Hierarchy is not optional

All calendar data must follow:

```text
calendar_layer_week
└── calendar_layer_day
    └── calendar_layer_time
        └── calendar_layer_event
```

No layer may be skipped.

---

### 2. Parent must exist before child

You **must not** insert a row unless its parent already exists.

| Layer | Required Parent |
| ----- | --------------- |
| week  | none            |
| day   | week            |
| time  | day             |
| event | time            |

---

### 3. Generated columns are read-only

The following fields are **automatically computed** and must NEVER be inserted manually:

* `day_unique_parent_id`
* `day_unique_index`
* `week_unique_key`
* `time_unique_parent_id`
* `time_unique_index`
* `event_unique_parent_id`
* `event_unique_index`
* `day_layer_unique_key`
* `event_layer_entity_key`

---

### 4. Entities must be created first

Every calendar row requires a corresponding entity:

```sql
INSERT INTO entities (id, entity_type_id)
VALUES ('calendar_event:<id>', 'entity_type_calendar_event');
```

Failure to do this will trigger FK errors.

---

### 5. Chronology address is authoritative

Each row must include a valid `chronology_address`:

| Layer | Format    |
| ----- | --------- |
| week  | `3`       |
| day   | `3.1`     |
| time  | `3.1.2`   |
| event | `3.1.2.1` |

This encodes hierarchy and is used for recovery.

---

## Week Creation Procedure

---

### Step 1 — Create week-layer row

```sql
INSERT INTO entities (id, entity_type_id)
VALUES ('calendar_event:319', 'entity_type_calendar_event');

INSERT INTO calendar_events (
    entity_id,
    projection_entity_id,
    layer_id,
    summary,
    week_index,
    event_id,
    chronology_address
)
VALUES (
    'calendar_event:319',
    'book_projection_BOOK-001',
    'calendar_layer_week',
    'Week 3 — Serve-More Cycle Begins',
    3,
    319,
    '3'
);
```

---

### Step 2 — Create all day-layer rows

For each day (1–7):

```sql
-- Replace values manually before running.
-- Example: Sunday, Week 3

INSERT INTO entities (id, entity_type_id)
VALUES ('calendar_event:300', 'entity_type_calendar_event');

INSERT INTO calendar_events (
    entity_id,
    projection_entity_id,
    layer_id,
    summary,
    week_index,
    day_index,
    parent_event_id,
    event_id,
    chronology_address
)
VALUES (
           'calendar_event:300',
           'book_projection_BOOK-001',
           'calendar_layer_day',
           'Sunday',
           3,
           1,
           319,
           300,
           '3.1'
       );
```

Example:

* calendar_event:<NEW_ID>
* <DAY_NAME>
* <DAY_INDEX>

---

### Step 3 — Create time-layer rows

Standard time blocks:

| time_index | Label              |
| ---------- | ------------------ |
| 1          | Early morning      |
| 2          | Morning            |
| 3          | Afternoon / Lunch  |
| 4          | Evening            |
| 5          | Night / Late night |

Example:

```sql
INSERT INTO entities (id, entity_type_id)
VALUES ('calendar_event:322', 'entity_type_calendar_event');

INSERT INTO calendar_events (
    entity_id,
    projection_entity_id,
    layer_id,
    summary,
    week_index,
    day_index,
    time_index,
    parent_event_id,
    event_id,
    chronology_address
)
VALUES (
    'calendar_event:322',
    'book_projection_BOOK-001',
    'calendar_layer_time',
    'Early morning',
    3,
    1,
    1,
    300,
    322,
    '3.1.1'
);
```

---

### Step 4 — Create event-layer rows

Event-layer rows are **the only valid prose targets**.

```sql
INSERT INTO entities (id, entity_type_id)
VALUES ('calendar_event:327', 'entity_type_calendar_event');

INSERT INTO calendar_events (
    entity_id,
    layer_id,
    summary,
    week_index,
    day_index,
    time_index,
    event_index,
    parent_event_id,
    event_id,
    chronology_address
)
VALUES (
    'calendar_event:327',
    'calendar_layer_event',
    'Narrative anchor for Early morning',
    3,
    1,
    1,
    1,
    322,
    327,
    '3.1.1.1'
);
```

---

## Prose Attachment Rules

* ✅ Allowed:

    * `calendar_layer_event`

* ❌ Forbidden:

    * week
    * day
    * time

---

## Validation Queries

### 1. Detect orphaned nodes

```sql
SELECT child.event_id, child.parent_event_id
FROM calendar_events child
LEFT JOIN calendar_events parent
  ON child.parent_event_id = parent.event_id
WHERE child.parent_event_id IS NOT NULL
  AND parent.event_id IS NULL;
```

---

### 2. Detect invalid prose targets

```sql
SELECT p.*
FROM prose_projections p
JOIN calendar_events e
  ON p.target_entity_id = e.entity_id
WHERE e.layer_id <> 'calendar_layer_event';
```

---

### 3. Verify day uniqueness

```sql
SELECT week_index, day_index, COUNT(*)
FROM calendar_events
WHERE layer_id = 'calendar_layer_day'
GROUP BY week_index, day_index
HAVING COUNT(*) > 1;
```

---

## Common Failure Modes

### Missing parent rows

Symptom:

* orphaned time/event rows

Cause:

* inserting children before parents

---

### FK constraint failure (`entities`)

Symptom:

```
Cannot add or update a child row
```

Cause:

* missing entity row

---

### Generated column errors

Symptom:

```
value specified for generated column is not allowed
```

Cause:

* attempting to insert computed fields

---

## Final Guarantee

If this protocol is followed:

* The hierarchy remains intact
* Generated constraints enforce uniqueness
* Prose anchoring is always valid
* FK constraints can be safely enabled

---

## Status

This protocol is **authoritative** for all future calendar construction and repair work.
