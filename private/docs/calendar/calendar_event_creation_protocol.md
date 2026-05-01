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

#### 2.1. Event vs Subevent Structure

The event layer contains **two hierarchical depths**:

| Depth | Meaning   | Example Address |
|------|----------|----------------|
| 4    | Event    | `1.3.5.1`      |
| 5    | Subevent | `1.3.5.1.1`    |

Rules:

- Depth 4 = **top-level event within a time block**
- Depth 5 = **child event (subevent) of a depth-4 event**
- Subevents must reference a **parent event**, not a time layer

---

#### 2.2 Index Derivation (Critical)

The following fields are **derived from `chronology_address`** and must NOT be manually assigned:

- `week_index`
- `day_index`
- `time_index`
- `event_index`
- `subevent_index`

Derivation:

chronology_address = W.D.T.E(.S)

```text 
| Segment | Meaning |
|--------|--------|

| W      | week_index |
| D      | day_index |
| T      | time_index |
| E      | event_index |
| S      | subevent_index (optional) |
```

Examples:
```text 
1.3.5.1 → event_index = 1
1.3.5.1.2 → event_index = 1, subevent_index = 2
```
> `chronology_address` is the **only source of truth** for structural position.


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

You must construct a valid `chronology_address`.

#### For a top-level event:
```text
chronology_address = parent_time_address + '.' + EVENT_INDEX
```

#### For a subevent:
```text
chronology_address = parent_event_address + '.' + SUBEVENT_INDEX
```


---

### Insert Template (Minimal Safe Form)

```text
INSERT INTO calendar_events (
  entity_id,
  event_id,
  layer_id,
  summary,
  chronology_address,
  parent_event_id
)
VALUES (
  'calendar_event:EVENT_ID',
  EVENT_ID,
  'calendar_layer_event',
  'DESCRIPTIVE SUMMARY',
  'CHRONOLOGY_ADDRESS',
  PARENT_EVENT_ID
);
```
#### Important
* week_index, day_index, time_index, event_index, subevent_index
→ must NOT be manually set
* These are derived fields
* Manual assignment can corrupt constraints

### Parent Linkage Rules

- Depth 4 events:
    - parent must be a **time-layer row**
- Depth 5 events (subevents):
    - parent must be a **depth-4 event**

Parent must always match:

parent.chronology_address =
SUBSTRING_INDEX(child.chronology_address, '.', depth - 1)


Any mismatch creates:
- orphaned rows
- constraint violations
- invalid projections

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
### Event Uniqueness Constraint

UNIQUE(event_unique_parent_id, event_unique_index)


This enforces:

> Only one event index per parent

Important:

- This constraint is enforced via **generated columns**
- It cannot be bypassed with partial updates
- All writes must be **structurally valid at commit time**

---

### Implication for Writes

You cannot:

- change `parent_event_id` independently
- change `event_index` independently

Because both affect generated uniqueness keys.

All mutations must be **coherent and complete**.


## Repair Doctrine (Critical Operational Rule)

In case of corruption:

1. Treat `chronology_address` as ground truth
2. Derive all index fields from it
3. Rebuild parent linkage from it
4. Rebuild constraints last

Never:

- trust stored index columns
- trust parent linkage
- mutate partial structure under active constraints

---

### Safe Repair Pattern

1. Disable or drop conflicting indexes
2. Rebuild structure from `chronology_address`
3. Validate uniqueness
4. Re-enable constraints

---

This is the only safe recovery method.

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
