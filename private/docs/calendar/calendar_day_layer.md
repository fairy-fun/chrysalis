# Calendar System — Day Layer
> “See *Calendar Invariants* for system-wide rules.”
## Overview

Day-level calendar events represent the second layer in the hierarchy:
```text
Week → Day → Time → Event → Subevent
```

Each layer owns its own index and is uniquely constrained within its immediate parent.

---

## Core Invariants

### 1. Entity-first rule

Every calendar event **must have a corresponding entity** created first.

```php
$eventId = next_calendar_event_id($pdo);
$entityId = 'calendar_event:' . $eventId;

create_entity($pdo, $entityId, 'entity_type_calendar_event');
```

### 2. Dual ID model
```text
calendar_events.id        → primary key (used for joins)
calendar_events.event_id  → sequential public ID
```
All relationships use:
```text
parent_event_id → calendar_events.id
```

### 3. Hierarchical structure

```text
   Week
   └── Day (day_index)
   └── Time (time_index)
   └── Event (event_index)
   └── Subevent (subevent_index)
```

Each layer:

* inherits context from parent
* defines uniqueness using its own index

## Day Layer Specification
### Required fields

```text
layer_id            = 'calendar_layer_day'
parent_event_id     = <week.id>
week_index          = inherited from parent
day_index           = 1..7
event_id            = allocated via sequence
entity_id           = 'calendar_event:<event_id>'
chronology_address  = '<week>.<day>'  (e.g. "3.1")
```
### Chronology Address

Used for natural-language lookup:
```text
Week: 3
Day:  3.1
Time: 3.1.1
Event: 3.1.1.1
```

Query:
```sql
SELECT ce.*
FROM calendar_events ce
JOIN calendar_event_projection_membership cepm
ON cepm.calendar_event_id = ce.id
WHERE ce.chronology_address = :address
AND cepm.projection_entity_id = :projection_id
LIMIT 1;
```
---
## Uniqueness Constraint (Day Layer)

Enforced via generated columns:
```sql
ALTER TABLE calendar_events
ADD COLUMN day_unique_parent_id BIGINT
GENERATED ALWAYS AS (
CASE
WHEN layer_id = 'calendar_layer_day' THEN parent_event_id
ELSE NULL
END
) STORED,
ADD COLUMN day_unique_index TINYINT UNSIGNED
GENERATED ALWAYS AS (
CASE
WHEN layer_id = 'calendar_layer_day' THEN day_index
ELSE NULL
END
) STORED;
ALTER TABLE calendar_events
ADD UNIQUE KEY uniq_day_per_week (
day_unique_parent_id,
day_unique_index
);
```
### Behavior
* Enforces: one day per (week, day_index)
* Allows: reuse of day_index in other layers
* Prevents: duplicate day creation under concurrency

## Idempotent Creation Pattern

Day creation must be safe under retries and race conditions.

### Algorithm
1. Check for existing day:
```sql
SELECT id
FROM calendar_events
WHERE parent_event_id = :week_id
AND layer_id = 'calendar_layer_day'
AND day_index = :day_index
LIMIT 1;
```
2. If found → return it
3. Else:
- allocate ID
- create entity
- insert row

4. On duplicate key error:
- re-select and return existing row
---
## Projection Membership

Day events inherit projection membership from their parent week:
```sql
INSERT INTO calendar_event_projection_membership (calendar_event_id, projection_entity_id)
SELECT :new_event_id, projection_entity_id
FROM calendar_event_projection_membership
WHERE calendar_event_id = :week_id;
```
---
## Known Failure Mode (Resolved)
### Duplicate day rows

Example:

parent_event_id = 1
day_index = 3
→ 2 rows existed

Cause:

* no uniqueness constraint
* repeated insert

Fix:

* remove duplicate
* enforce unique index
---
## Design Principle

Uniqueness is always defined at the level of the immediate parent.

Not globally, not by index column alone.

## Status
- ✅ Entity-first pattern enforced
- ✅ Day-level uniqueness enforced at DB level
- ✅ Chronology address queryable
- ✅ Idempotent creation pattern defined
---

## Next Layers

Apply the same pattern to:

- Time layer → (parent_event_id, time_index)
- Event layer → (parent_event_id, time_index, event_index)
- Subevent layer → (parent_event_id, subevent_index)

(with appropriate scoping)

## Time Layer (Depth 3) — Day Subdivision

### Overview

The time layer subdivides a calendar day into ordered narrative containers.

Each node at depth 3 represents a **time segment within a day**, providing structure for grouping events.

Time nodes do not carry narrative meaning themselves. They exist to organize events temporally and support human-readable views.

---

### Address Structure

```
W.D.T
```

* `W` → week_index
* `D` → day_index
* `T` → time_index

Examples:

```
3.1.1 → Week 3, Day 1, Time 1
3.1.2 → Week 3, Day 1, Time 2
```

---

### Time Labels

Each time node MUST map to a canonical time label via:

```
calendar_events.time_label_id
```

Time labels are defined in:

```
calendar_time_label_classvals
```

---

### Canonical Time Label Set (Ordered)

Time labels are globally standardized and MUST follow this exact order:

```
1 → Pre-dawn
2 → Early Morning
3 → Morning
4 → Lunch
5 → Afternoon
6 → Evening
7 → Night
8 → Late Night
```

Ordering is enforced via:

```
calendar_time_label_classvals.sort_order
```

This ordering is authoritative and MUST be used for all time-based rendering and grouping.

---

### Structural Rules

* Time nodes exist at **depth 3 only**

* Time nodes MUST belong to a valid day (`W.D`)

* Time nodes MUST have:

    * a valid `time_index`
    * a valid `time_label_id`

* Time nodes MUST NOT:

    * contain narrative summaries
    * be used as events
    * carry narrative meaning

---

### Relationship to Events

* Events (depth 4) MUST belong to a time node
* Subevents (depth 5) MUST belong to events, not time nodes

Hierarchy:

```
Day (W.D)
  → Time (W.D.T)
      → Event (W.D.T.E)
          → Subevent (W.D.T.E.S)
```

---

### Usage Rules

* Time nodes SHOULD only be created when needed
* Do NOT create all possible time slots by default
* Empty time nodes are allowed but SHOULD be intentional

---

### Rendering Contract (Human Readable)

When displaying a day, time nodes define grouping:

Example:

```
Sunday (Week 3)

Pre-dawn
  - —

Early Morning
  - Event A

Morning
  - Event B

Afternoon
  - Event C
```

* Events MUST be grouped by `time_index`
* Time groups MUST be ordered by `sort_order`
* Empty groups MAY be omitted unless explicitly required

---

### Anti-Patterns (Disallowed)

* Using time nodes as narrative containers
* Skipping time labels or leaving them NULL
* Inconsistent or custom time label sets
* Encoding time meaning in event summaries instead of labels
* Creating unnecessary empty time nodes

Violations MUST be rejected at the API layer.
