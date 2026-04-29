# Calendar System — Week Layer
> “See *Calendar Invariants* for system-wide rules.”
## Overview
```text
Week-level calendar events are the **root layer** of the calendar hierarchy:
Week → Day → Time → Event → Subevent
```

A Week has **no parent** and anchors all downstream calendar structure.

---

## Core Invariants

### 1. Entity-first rule

Every calendar event must have a corresponding entity created first.

```php
$eventId = next_calendar_event_id($pdo);
$entityId = 'calendar_event:' . $eventId;

create_entity($pdo, $entityId, 'entity_type_calendar_event');
```
---

### 2. Dual ID model
```text
   calendar_events.id        → primary key (used for joins)
   calendar_events.event_id  → sequential public ID
```
All relationships use:
```text
parent_event_id → calendar_events.id
```
For Week layer:
```text
parent_event_id = NULL
```
---

### 3. Projection scoping

Weeks are always created within a projection.
```text
calendar_event_projection_membership:
calendar_event_id → calendar_events.id
projection_entity_id → entities.id
```
All queries must scope by projection.

---

## Week Layer Specification
### Required fields
```text
layer_id            = 'calendar_layer_week'
parent_event_id     = NULL
week_index          = integer (e.g. 1, 2, 3…)
event_id            = allocated via sequence
entity_id           = 'calendar_event:<event_id>'
chronology_address  = '<week>' (e.g. "3")
```
---

### Chronology Address

Week is the root of the address system:
```text
Week: 3
Day:  3.1
Time: 3.1.1
Event: 3.1.1.1
```
---

## Uniqueness Constraint (Week Layer)

Weeks must be unique per projection:
```text
(projection_entity_id, week_index)
```
Since projection lives in a separate table, this is enforced via application logic.

## Canonical check

```sql
SELECT ce.id
FROM calendar_events ce
JOIN calendar_event_projection_membership cepm
ON cepm.calendar_event_id = ce.id
WHERE ce.layer_id = 'calendar_layer_week'
AND ce.parent_event_id IS NULL
AND ce.week_index = :week_index
AND cepm.projection_entity_id = :projection_id
LIMIT 1;
```
---
## Idempotent Creation Pattern

Week creation must be safe under retries and concurrency.

### Algorithm
1. Check for existing week (by projection + week_index)

2. If found → return it

3. Else:
- allocate event_id
- create entity
- insert calendar event
- insert projection membership

4. On race condition:
- re-select and return existing row
---
## Insert Pattern
### 1. Allocate ID
```php
   $eventId = next_calendar_event_id($pdo);
```

### 2. Create entity
```php
   $entityId = create_calendar_event_entity($pdo, $eventId);
```
### 3. Insert week
```php
   INSERT INTO calendar_events (
   entity_id,
   layer_id,
   week_index,
   parent_event_id,
   chronology_address,
   event_id
   ) VALUES (
   :entity_id,
   'calendar_layer_week',
   :week_index,
   NULL,
   :chronology_address,
   :event_id
   );
```
### 4. Insert projection membership
```sql
   INSERT INTO calendar_event_projection_membership (
   calendar_event_id,
   projection_entity_id
   ) VALUES (
   :calendar_event_id,
   :projection_id
   );
```
---
   ## Querying Weeks
   ### By projection
```sql
   SELECT ce.*
   FROM calendar_events ce
   JOIN calendar_event_projection_membership cepm
   ON cepm.calendar_event_id = ce.id
   WHERE ce.layer_id = 'calendar_layer_week'
   AND cepm.projection_entity_id = :projection_id
   ORDER BY ce.week_index ASC;
 ```
   ### By chronology address
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
 ##  Design Principle

A Week is the root node of a projection-specific calendar tree.

- No parent
- Defines top-level ordering via week_index
- Anchors all child layers
---

Status
- ✅ Entity-first pattern enforced
- ✅ Projection membership enforced
- ✅ Chronology address established
- ✅ Idempotent creation pattern defined
### Next Layer

Day layer:
```text
(parent_event_id, day_index)
 ```
With database-enforced uniqueness via generated columns.

---

## Gotchas (Hard-Learned Lessons)

### 1. Duplicate Week Creation (Race Condition)

**Symptom:**
- Multiple rows with same `week_index` under the same projection

**Cause:**
- No DB-level uniqueness (projection is in a separate table)
- Concurrent requests both pass the “exists” check

**Resolution:**
- Enforce idempotent creation pattern:
    - check → insert → on conflict → re-select

**Rule:**
```text id="y9c3k2"
Never assume the pre-insert check is sufficient under concurrency.
 ```
---

### 2. Using the Wrong ID in Relationships

**Symptom:**
- Broken parent-child links
- FK inconsistencies or missing joins

**Cause:**
Confusing:
```text 
event_id (public sequence)
vs
id (primary key)
 ```
Correct usage:
```text 
parent_event_id → calendar_events.id
membership.calendar_event_id → calendar_events.id
 ```
**Rule:**
```text 
All joins and relationships use `id`, never `event_id`.
 ```
---
### 3. Creating Event Before Entity

Symptom:

FK constraint failure (1216)
orphaned or invalid rows

Cause:

inserting into calendar_events before entities

Correct order:

1. allocate event_id
2. create entity
3. insert calendar_events row
4. insert projection membership
4. Placeholder / Fake Entity IDs

Symptom:

broken joins later
“ghost” calendar events

Cause:

using temporary values like:

'__pending_calendar_event__'

Rule:

Entity IDs must always be final and valid at insert time.
### 5. Missing Chronology Address

Symptom:

NL queries fail ("Show me 3")
duplicate logical rows appear

Cause:

inserting rows without chronology_address

Rule:

chronology_address is required for all calendar layers.
### 6. Projection Membership Omission

Symptom:

week exists but is invisible in queries

Cause:

forgetting to insert into:

calendar_event_projection_membership

Rule:

A calendar event is not “real” until it has projection membership.
### 7. Misinterpreting Uniqueness Scope

Symptom:

attempts to add invalid DB constraints
collisions across unrelated layers

Cause:

assuming global uniqueness like:

week_index must be globally unique

Correct model:

Week uniqueness = (projection, week_index)
### 8. Silent Duplicate Writes

Symptom:

identical rows with same timestamp
missing fields (e.g. NULL chronology_address)

Cause:

retries without idempotency handling

Example:

two inserts within same second → both succeed

Fix:

enforce:
uniqueness where possible
retry-safe logic where not
Final Principle
If the database does not enforce it,
the application must assume it can be violated.

---