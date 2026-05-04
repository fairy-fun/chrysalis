# Calendar Write Surface — Weeks, Days, Times
1. Core Invariant

All calendar writes must follow:

API → ensure_calendar_* → ensure_calendar_node → DB
Allowed entry points
ensure_calendar_week(...)
ensure_calendar_day(...)
ensure_calendar_time(...)
ensure_calendar_event(...)
Forbidden
Direct INSERT INTO calendar_events
Any legacy create_calendar_event*
Any structural writes outside ensure_calendar_node
Any use of chronology_address for writes
2. Identity Model (Non-Negotiable)
   Concept	Field	Purpose
   Structure	calendar_events.id	Internal tree linkage
   Identity	calendar_events.event_id	Stable business ID
   External	calendar_events.entity_id	API + projections
   Critical rule
   parent_event_id ALWAYS references calendar_events.id
   NEVER event_id
3. Structural Model

Tree is defined by:

parent_event_id + sequence_index
Layers
calendar_layer_week
calendar_layer_day
calendar_layer_time
calendar_layer_event

Each layer is enforced via:

assert_calendar_parent_transition
assert_calendar_semantic_parent_child (events only)
4. API Operations
   4.1 Create Week

Operation

createCalendarWeek

POST body

{
"operation": "createCalendarWeek",
"book_code": "BOOK-001",
"week_index": 4,
"week_label": "Week 4",
"real_date_start_id": "DATE-001"
}
Mapping → ensurer
ensure_calendar_week(
$pdo,
$bookCode,
$weekIndex,
[
'summary' => $weekLabel,
'real_date_start_id' => $realDateStartId,
]
);
Notes
week_label is mapped to summary
Weeks have no parent
week_index = sequence_index
4.2 Create Day

Operation

createCalendarDay

POST body

{
"operation": "createCalendarDay",
"parent_week_entity_id": "calendar_event:123",

summary         = display

# Calendar Time Nodes: Structure vs Semantics

## Overview

Calendar time nodes exist at the intersection of three independent concerns:

* **Structure** — where the node sits in the calendar hierarchy
* **Identity** — how the node is referenced externally
* **Semantics** — what the node *means* (e.g. “Morning”, “Evening”)

These concerns must remain **strictly separated**.

---

## 1. Structure (Chronology)

Time nodes are positioned using:

```text
parent_event_id + sequence_index
```

Example:

```text
Week 3 (299)
  └── Day (314)
        ├── 1 → Time node A
        ├── 2 → Time node B
        └── 3 → Time node C
```

Key rules:

* `sequence_index` defines **order only**
* It has **no semantic meaning**
* It is **local to the parent**

### Important

> `sequence_index = 1` does NOT mean “Morning”

Different days may have completely different structures.

---

## 2. Identity

Each node has:

```text
calendar_events.entity_id
```

Example:

```text
calendar_event:314
```

Public APIs must expose:

```text
entity_id
```

Never:

```text
id (internal row id)
```

---

## 3. Semantics (Time Labels)

Time meaning is defined via:

```text
calendar_events.time_label_id
```

This references:

```text
calendar_time_label_classvals
```

Example:

```text
time_label_id = CLASSVAL-TIME-002 → "Morning"
```

### Classval Table

```text
calendar_time_label_classvals
-----------------------------------------
id                 code           label
CLASSVAL-TIME-002  morning        Morning
CLASSVAL-TIME-004  afternoon      Afternoon
...
```

### Rules

* `time_label_id` is **optional but recommended**
* When present, it must:

    * exist in `calendar_time_label_classvals`
* It provides:

    * canonical meaning
    * consistent labeling
    * queryable semantics

---

## 4. Summary (Display Layer)

Each node also stores:

```text
summary
```

This is:

* human-readable text
* used for display
* required by `ensure_calendar_node()`

### Behavior

* If `time_label_id` is provided:

    * `summary` SHOULD be derived from classval label
* If not:

    * `summary` acts as fallback free text

---

## 5. API Contract

### Request

```json
{
  "operation": "createCalendarTime",
  "parent_day_entity_id": "calendar_event:123",
  "time_index": 1,
  "time_label_id": "CLASSVAL-TIME-002",
  "time_label": "Morning"
}
```

### Rules

* `time_index` → required (structure)
* `time_label_id` → optional but preferred (semantics)
* `time_label` → optional (display fallback)

### Resolution behavior

| Input         | Result                           |
| ------------- | -------------------------------- |
| label_id only | summary derived from classval    |
| label only    | summary = label                  |
| both          | label_id stored, summary = label |
| neither       | summary = "Time" (fallback)      |

---

## 6. Non-Goals (Important Constraints)

The system does **NOT** enforce:

* fixed mappings like:

  ```text
  sequence_index = 1 → Morning
  ```
* uniform time structures across days
* required presence of all time slots

### This is intentional

Different days may have:

```text
Day A:
  1 → Morning
  2 → Afternoon

Day B:
  1 → Evening
  2 → Night
  3 → Late Night
```

Structure is flexible. Semantics are descriptive.

---

## 7. Design Principles

### Separation of Concerns

| Concern   | Mechanism                        |
| --------- | -------------------------------- |
| Structure | parent_event_id + sequence_index |
| Identity  | entity_id                        |
| Semantics | time_label_id (classvals)        |
| Display   | summary                          |

---

### Derived, Not Stored

* Chronology paths (e.g. `3.1.2`) are **computed**
* Labels are **referenced**, not embedded
* Structure drives traversal—not semantics

---

### Flexibility First

* Days can define arbitrary time structures
* Labels do not constrain structure
* Structure does not imply meaning

---

## 8. Future Direction

The system can evolve toward:

* requiring `time_label_id` for all nodes
* deriving `summary` exclusively from classvals
* enabling semantic queries:

  ```sql
  WHERE time_label_id = 'CLASSVAL-TIME-002'
  ```

Without changing:

* traversal logic
* structural guarantees
* public identity model

---

## Summary

> Time nodes are **ordered structurally**, **identified canonically**, and **labeled semantically**—but these layers do not constrain each other.

This separation is what allows the calendar to remain both:

* structurally rigorous
* semantically expressive
