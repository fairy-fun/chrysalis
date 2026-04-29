# Calendar System — Core Invariants

## Overview

This document defines the **non-negotiable rules** governing the calendar system.

All layer-specific implementations (Week, Day, Time, Event, Subevent) must conform to these invariants.

---

## 1. Entity-First Rule

Every calendar event **must have a corresponding entity created before insertion**.

### Required sequence

```php
$eventId = next_calendar_event_id($pdo);
$entityId = 'calendar_event:' . $eventId;

create_entity($pdo, $entityId, 'entity_type_calendar_event');
```

## 2. Dual ID Model

Each calendar event has two identifiers:
```text
calendar_events.id        → primary key (auto_increment)
calendar_events.event_id  → sequential public ID (allocated)
Usage
ALL relationships use calendar_events.id
Never use:
event_id for joins or foreign keys
```
## 3. Hierarchical Model

The calendar is a strict tree:

Week → Day → Time → Event → Subevent
Parent rule
child.parent_event_id → parent.id
Root rule
Week:
parent_event_id = NULL
##  4. Layer Ownership of Indexes

Each layer owns exactly one index:
```text
Week       → week_index
Day        → day_index
Time       → time_index
Event      → event_index
Subevent   → subevent_index
```

Critical rule
Only the active layer defines uniqueness using its index.

Other index fields may be present but are inherited context only.

##  5. Uniqueness Scope

Uniqueness is always defined at the immediate parent level.

**Examples**
```text
Day:
(parent_event_id, day_index)

Time:
(parent_event_id, time_index)

Event:
(parent_event_id, time_index, event_index)
```
**Principle** 
Uniqueness is never global.
Uniqueness is never defined by index column alone.
##  6. Projection Membership

Calendar events are scoped to projections via:
```text
calendar_event_projection_membership
```
**Rule**
```text
A calendar event is not considered valid until it has projection membership.
```
**Inheritance rule**
```text
Child events inherit projection membership from their parent.
```
---

##  7. Chronology Address

Each event may have a human-readable hierarchical address:
```text
3
3.1
3.1.1
3.1.1.1
```
**Usage**
- Natural language lookup
- Debugging
- UI addressing
  **Rule**
```text
chronology_address must match the hierarchical position of the node.
```
** Important **
```text
chronology_address is queryable, but not the source of truth.
Indexes define structure.
```
##  8. Idempotent Creation

All creation operations must be safe under:

- retries
- concurrent requests
### Required pattern
```text
1. Check if exists
2. If exists → return
3. Else insert
4. On duplicate → re-select and return
```
   **Rule**
```text
   Never assume pre-insert checks are sufficient under concurrency.
```
##  9. No Placeholder Entities
   Forbidden
```text
   __pending_calendar_event__
   temporary IDs
   null entity references
```
**Rule**
```text
   Entity IDs must always be final and valid at insert time.
```
##  10. Insert Order (Strict)

All inserts must follow:
```text
1. Allocate event_id
2. Create entity
3. Insert calendar_events row
4. Insert projection membership
```

##  11. Database vs Application Responsibility

   ### Database enforces:

   - structural uniqueness (where possible)
   - referential integrity
  ###  Application enforces:
 - projection-scoped uniqueness (cross-table)
 - idempotency
 - hierarchy correctness
  ###  Principle
    If the database does not enforce it,
    the application must assume it can be violated.
##  12. Failure Modes to Avoid
###    Common issues
- duplicate rows from retries
 -  missing chronology_address
  -  incorrect parent_event_id usage
   - mixing index semantics across layers
   - orphaned events without projection membership

    ## Final Principle
```text
    The calendar is a tree of entities,
    not a flat table with indexes.
```
Everything—queries, inserts, constraints—must respect that model.