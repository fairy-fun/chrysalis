# Calendar System — Write Contract

## Overview

This document defines the **required behavior for all write operations** to the calendar system.

All create/update flows MUST follow this contract.

This applies to:
- Week creation
- Day creation
- Time/Event/Subevent creation
- Any future calendar mutations

---

## 1. Core Principle

```text
All writes must be:
- deterministic
- idempotent
- race-safe
- invariant-preserving
```

## 2. Required Write Sequence

Every calendar event creation MUST follow this exact order:
```text
1. Resolve parent (if applicable)
2. Check for existing record (idempotency check)
3. Allocate event_id
4. Create entity
5. Insert calendar_events row
6. Insert projection membership
7. Handle duplicate-key race (if triggered)
```
## 3. Idempotency Rule
### Definition

A write operation is idempotent if:
```text
Repeated calls with the same inputs return the same result
without creating duplicate rows
```
### Required Pattern
```sql
-- Step 1: Check existing
SELECT id
FROM calendar_events
WHERE parent_event_id = :parent_id
  AND layer_id = :layer_id
  AND <layer_index> = :index
LIMIT 1;
```
```text
IF found:
  return existing row
ELSE:
  attempt insert
```
---

## 4. Race Condition Handling

Even with a pre-check, concurrent requests may collide.

Required behavior
```text
IF insert fails with duplicate key:
→ re-select existing row
→ return it
```
Never:
fail the request due to duplicate key

## 5. Entity Creation (Non-Negotiable)

   Required
```php
    $eventId = next_calendar_event_id($pdo);
    $entityId = 'calendar_event:' . $eventId;

    create_entity($pdo, $entityId, 'entity_type_calendar_event');
```
** Rule **
```text
Entity must exist BEFORE inserting calendar_events row
```

## 6. Insert Requirements

   calendar_events insert must include:
   entity_id
   layer_id
   parent_event_id
   <layer-specific index>
   event_id
   chronology_address
   parent_event_id rule
   Always references calendar_events.id (NOT event_id)
## 7. Projection Membership (Required)

Every event must be linked to a projection.

** Rule**
A calendar event is not valid until membership is inserted
Inheritance pattern
INSERT INTO calendar_event_projection_membership (calendar_event_id, projection_entity_id)
SELECT :new_event_id, projection_entity_id
FROM calendar_event_projection_membership
WHERE calendar_event_id = :parent_event_id;

## 8. Chronology Address Construction

   Rule
   chronology_address must reflect hierarchical position
   Examples
   Week: "3"
   Day: "3.1"
   Time: "3.1.1"
   Event: "3.1.1.1"
   Construction
   child_address = parent_address + '.' + index

## 9. Error Handling Rules

   Allowed outcomes
- success (new row created)
- success (existing row returned)
  Disallowed outcomes
- partial writes
- orphaned entities
- duplicate logical rows
- missing projection membership

## 10. Transaction Boundaries

Recommended:

Wrap the full write flow in a transaction

Especially for:

entity creation
event insert
membership insert

## 11. Validation Requirements

Before insert:

- parent exists (if applicable)
- correct layer_id
- index within valid range (e.g. day 1..7)

## 12. Layer-Specific Index Rules

Each layer must validate its own index:

Week       → week_index > 0
Day        → 1 ≤ day_index ≤ 7
Time       → time_index ≥ 1
Event      → event_index ≥ 1
Subevent   → subevent_index ≥ 1

## 13. No Placeholder Values

    Forbidden
    NULL chronology_address
    temporary entity IDs
    fake parent references
## 14. Write Contract Violation Symptoms

If this contract is broken, you will see:

- duplicate rows
- missing days/weeks in queries
- broken hierarchy traversal
- inconsistent chronology addresses
- FK constraint failures
## 15. Final Principle
    Every write must either:
- fully succeed
- or behave as if it already succeeded

There is no third state.