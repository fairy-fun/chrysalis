# Add Prose Via Postman To Existing Event

## Purpose

Defines the bounded workflow for attaching prose
to an already-existing calendar_layer_event
through the API/Postman flow.

---

## Entry Conditions

The operator intends to:

- attach prose to an existing event
- use Postman/API flow
- avoid manual hierarchy traversal

Required runtime state:

- projection resolved
- event resolved
- concrete calendar_layer_event identity resolved

## Interview Entry Point (Hard Anchor)

This workflow does NOT begin with intent exploration or hierarchical navigation.

It begins with a required identity anchor.

### First Required Question

The FIRST question the runtime MUST ask is:

What is the calendar_event entity_id?

#### Runtime Validation (After Entity ID)

Once the operator provides:

calendar_event.entity_id

the runtime MUST immediately validate the event exists in the database.

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

### Constraint Rules

- No prose creation is allowed before projection is confirmed
- No event traversal is allowed before projection is confirmed
- No assumptions about default or “current” projection are allowed
- The projection MUST be explicitly provided or selected by the operator

### Reason

Tier 2 workflows operate on existing events that may exist across multiple projections.

Projection binding is required to determine:

- publication context
- export behavior
- runtime visibility scope
- downstream prose resolution rules

## Tier 2 Progressive Binding Model

Tier 2 workflows MUST follow a strict progressive binding order:

1. calendar_event.entity_id
2. projection selection
3. publication / prose resolution state
4. payload construction (Postman/API)
5. verification

No step may be skipped or inferred.

### Constraint Rules

- No week/day/time/event questions are allowed before entity_id is provided
- No projection discovery is allowed before entity_id is provided
- No runtime traversal is allowed before entity_id is provided
- No prose drafting is allowed before entity_id is provided

### Reason

Tier 2 workflows operate on a pre-resolved or externally supplied event identity.

The system MUST NOT reconstruct what the operator is explicitly asserting already exists.

## Identity-First Execution Rule

All Tier 2 workflows begin from a concrete runtime identity:

calendar_event.entity_id

Everything else (projection, chronology, event context, publication state) is derived AFTER this anchor is supplied.

---

## Runtime Resolution Rules

The runtime MUST first attempt:

- latest event resolution
- publication resolution
- prose existence resolution

The runtime MUST NOT ask:

- what week
- what day
- what event

if canonical event resolution already succeeded.

---

## Required Documents

- create_prose_draft.md
- create_prose_draft_json_contract.md

---

## Canonical Workflow

resolve target event
→ inspect publication state
→ prepare prose draft payload
→ submit API request
→ verify publication state
→ bounded stop
→ NEXT CHAT START PACK

---

## Required Runtime Outputs

...

---

## Interview Rules

...

---

## Postman Payload Rules

...

---

## Verification Rules

...

---

## Stopping Rules

...

---

## NEXT CHAT START PACK Template

...