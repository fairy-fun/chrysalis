# How To Delete Calendar Events Safely

## Doctrine

`event_index` is NOT the database row id.

- `calendar_events.id`
    - persistence identity only
    - auto-increment storage key

- `event_index`
    - canonical Book chronology ordering
    - scoped within:
        - `projection_id`
        - `book_time_id`

Example:

- `id = 354`
- `event_index = 7`

means:

- the DB row is storage row 354
- the event occupies chronology slot 7 inside its Book time container

These are intentionally orthogonal.

---

# Safe Event Deletion Procedure

## 1. Verify the event exists

```sql
SELECT
    id,
    entity_id,
    projection_id,
    book_time_id,
    event_index,
    summary
FROM calendar_events
WHERE id = 354
   OR entity_id = 'calendar_event:354';
```

2. Check dependent references
   Prose projections
   SELECT *
   FROM prose_projections
   WHERE target_entity_id = 'calendar_event:354';
   Recursive subevent references
   SELECT *
   FROM calendar_layer_subevent
   WHERE parent_event_entity_id = 'calendar_event:354'
   OR child_event_entity_id = 'calendar_event:354';
   Linked fact references
   SELECT *
   FROM entity_linked_facts_global
   WHERE subject_entity_id = 'calendar_event:354'
   OR object_entity_id = 'calendar_event:354';
   Important Tier Doctrine

Tier 1 event workflows MUST NOT create topology containers.

Therefore normal event cleanup does NOT require touching:

calendar_book_weeks
calendar_book_days
calendar_book_times
calendar_projections

unless the workflow explicitly modified topology.

Safe Event Delete
DELETE FROM calendar_events
WHERE id = 354
OR entity_id = 'calendar_event:354'
LIMIT 1;
Post-Delete Verification
SELECT
id,
entity_id,
projection_id,
book_time_id,
event_index,
summary
FROM calendar_events
WHERE id = 354
OR entity_id = 'calendar_event:354';

Expected result:

0 rows
Notes

Do NOT:

reconstruct chronology
infer topology
use chronology_address as authority
traverse recursive ancestry to determine locality

Canonical Book event locality is strictly:

projection_id
book_time_id
event_index

Recursive ancestry belongs ONLY to:

calendar_layer_subevent