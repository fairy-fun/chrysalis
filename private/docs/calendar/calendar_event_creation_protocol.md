# Calendar Event Creation Protocol

## Purpose

Ensure that all calendar events are created in a structurally valid, enforceable way that supports:

* Hierarchical calendar integrity (week → day → time → event)
* Narrative anchoring (prose attaches only to event-layer rows)
* Entity system consistency
* Database-level constraint compliance

---

## Core Principles

### 1. Events are Entities

Every calendar event **must first exist in `entities`** before being inserted into `calendar_events`.

```
entities.id = calendar_events.entity_id
```

---

### 2. Layer Semantics

| Layer                  | Purpose                            | Prose Allowed |
| ---------------------- | ---------------------------------- | ------------- |
| `calendar_layer_week`  | Structural grouping                | ❌             |
| `calendar_layer_day`   | Calendar date                      | ❌             |
| `calendar_layer_time`  | Time bucket (e.g. "Early morning") | ❌             |
| `calendar_layer_event` | Narrative event                    | ✅             |

> Prose **must only attach to `calendar_layer_event`**

---

### 3. Identity Model

Each event has:

* `id` → auto-increment row ID
* `event_id` → **globally unique business identifier**
* `entity_id` → foreign key to `entities.id`

All must be consistent.

---

## Step-by-Step: Creating a New Event-Layer Row

### Step 1 — Generate IDs

``` text
event_id  = MAX(calendar_events.event_id) + 1
entity_id = calendar_event:EVENT_ID
```

Example:

``` sql
SELECT MAX(event_id) + 1 AS next_event_id
FROM calendar_events;
```
---

### Step 2 — Insert into `entities`

```text
INSERT INTO entities (id, entity_type_id)
VALUES ('calendar_event:EVENT_ID', 'entity_type_calendar_event');
```
Example:
```sql
INSERT INTO entities (id, entity_type_id)
VALUES ('calendar_event:327', 'entity_type_calendar_event');
```

---

### Step 3 — Insert into `calendar_events`

Template:

```text
INSERT INTO calendar_events (
  entity_id,
  event_id,
  layer_id,
  summary,
  parent_event_id,
  week_index,
  day_index,
  time_index,
  event_index
)
SELECT
  'calendar_event:EVENT_ID',
  EVENT_ID,
  'calendar_layer_event',
  'DESCRIPTIVE SUMMARY',
  ce.id,
  ce.week_index,
  ce.day_index,
  ce.time_index,
  EVENT_INDEX
FROM calendar_events ce
WHERE ce.entity_id = 'PARENT_TIME_LAYER_ENTITY_ID';
```

Example:

```sql
INSERT INTO calendar_events (
  entity_id,
  event_id,
  layer_id,
  summary,
  parent_event_id,
  week_index,
  day_index,
  time_index,
  event_index
)
SELECT
  'calendar_event:327',
  327,
  'calendar_layer_event',
  'Narrative anchor for Early morning',
  ce.id,
  ce.week_index,
  ce.day_index,
  ce.time_index,
  1
FROM calendar_events ce
WHERE ce.entity_id = 'calendar_event:322';
```
---

## Attaching Prose

Prose is linked via:

```
prose_projections.target_entity_id → calendar_events.entity_id
```

### Rules

* ✅ Must reference an **event-layer entity**
* ❌ Must NOT reference day/time/week layers

---
### Repoint Prose
Template:

```text
UPDATE prose_projections
SET target_entity_id = 'calendar_event:EVENT_ID'
WHERE id = PROJECTION_ID;
```

Example:
```sql
UPDATE prose_projections
SET target_entity_id = 'calendar_event:327'
WHERE target_entity_id = 'calendar_event:322';
```

---

## Enforced Constraints

### Day-layer uniqueness

```
UNIQUE(day_layer_unique_key)
```

Guarantees:

> Only one day per (week_index, day_index)

---

### Event-layer anchoring

```
FK(prose_projections.target_entity_id)
→ calendar_events(event_layer_entity_key)
```

Guarantees:

> Prose can only attach to event-layer rows

---

## Migration Rule (Important)

If prose is attached to a time-layer row:

### Required fix:

1. Create a new event-layer child
2. Repoint prose to the new event
3. Never attach prose to time-layer again

---

## Anti-Patterns (Do Not Do)

* ❌ Insert into `calendar_events` without creating `entities` row
* ❌ Reuse `event_id`
* ❌ Attach prose to:

    * `calendar_layer_day`
    * `calendar_layer_time`
* ❌ Create duplicate day rows (now prevented by constraint)

---

## Summary

A valid narrative event requires:

* A registered entity
* A unique `event_id`
* A `calendar_layer_event` row
* A valid parent (time-layer)
* Prose attached only at this level

---

## Outcome

Following this protocol guarantees:

* No duplicate calendar structure
* No ambiguous narrative anchors
* Full compatibility with projections, annotations, and exports
* Database-enforced correctness (not just convention)

---

**This is the canonical method for event creation.**
