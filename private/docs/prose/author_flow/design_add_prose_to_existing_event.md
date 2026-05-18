# Add Prose to Existing Event (Tier 2 State Machine)

## STATE 0 — WAITING_FOR_ENTITY_ID

INPUT REQUIRED:
calendar_event.entity_id

QUESTION:
What is the calendar_event.entity_id?

TRANSITION:
→ STATE 1 only after valid input

#### Canonical SQL Lookup

```sql
SELECT
    ce.id,
    ce.entity_id,
    ce.layer_id,
    ce.week_index,
    ce.day_index,
    ce.time_index,
    ce.event_index,
    ce.projection_id,
    ce.summary,
    ce.chronology_address
FROM calendar_events ce
WHERE ce.entity_id = :entity_id
LIMIT 1;
```

##### Binding Rule
If no row is returned → stop workflow and report invalid entity_id
If multiple rows are returned → treat as runtime integrity violation
If exactly one row is returned → bind it as the canonical event context
##### Required Runtime Output

The runtime MUST store:

event.id
event.entity_id
event.projection_id
event.layer_id (MUST equal `calendar_layer_event`)
event chronology fields
##### Constraint

No further questions may proceed until this validation succeeds.

##### Event Layer Enforcement Rule

After resolving the calendar_events row via entity_id lookup, the returned row MUST satisfy:

ce.layer_id = 'calendar_layer_event'

The canonical calendar_events row returned from entity_id lookup MUST satisfy:

- layer_id = 'calendar_layer_event'

If this condition fails:

- the entity_id does not represent an executable event node
- Tell the author

### Required Second Question (Projection Binding)

After the calendar_event.entity_id is provided, the runtime MUST ask:

Which projection should this event belong to?


