# =========================================
# private/docs/prose/create_prose_draft_json_contract.md
# =========================================

## Postman Request — Create Prose Draft

**Method**  
POST

**URL**  
https://antheapeche.com/pecherie/chill-api/index.php

**Headers**  
Content-Type: application/json  
X-API-Key: <your key>

**Body (raw JSON)**

## Body (raw JSON)

```json
{
  "operation": "createProseDraft",

  "entity_id": "prose_draft:BOOK-001-W3-D1-T1-v1",

  "title": "Book 1 — Week 3 Sunday Early Morning",

  "summary": "Shay decides to return to the Gilded Lily",
  
  "prose_body": "Line 1\nLine 2\nLine 3",

  "draft_status_id": "prose_status_draft",

  "author_entity_id": null,

  "projection": {
    "projection_id": 1,
    "projection_type_id": "projection_type_book",

    "target_entity_id": "calendar_event:322",

    "role_id": "primary",

    "projection_order": 1,

    "is_export_target": 1
  },

  "annotations": []
}
```
## Canonical Projection Identity

target_entity_id anchors validated event placement
projection_id defines canonical runtime projection identity

Canonical runtime identity is:

```text
projection_id
```

Book 1 prose execution MUST internally resolve and propagate:

```text
projection_id
```

through all runtime operations.

The following are compatibility ingress identities only:

```text
target_entity_id
calendar_event:<id>
projection_entity_id
```

Compatibility identities may be accepted as request ingress surfaces, but runtime systems MUST immediately resolve canonical:

```text
projection_id
```

before downstream execution.

## Hierarchy Resolution Requirement

Book 1 prose insertion is deterministic calendar traversal.

The hierarchy MUST resolve in order:

```text
Projection
→ Week
→ Day
→ Time
→ Event
→ Subevent prose
```

Time selection UIs should use resolved time_label fields from `get_calendar_times_for_day`.

The GPT MUST NOT:

- skip hierarchy layers
- infer missing runtime structure
- jump directly to event targeting
- create prose before traversal resolution

The target event MUST already exist and MUST be validated before prose insertion begins.

## Purpose

Creates a prose draft used as input for deterministic calendar subevent generation.

This endpoint does not create calendar events or subevents.

## Behavior
Stores prose as canonical input
Associates prose with a validated event-layer runtime parent inside a resolved projection hierarchy.
Does NOT:
call planner
generate plan_id
assign client_id
write to calendar_events
## Critical Rules
1. JSON Only
Use raw JSON
Do not use multipart unless uploading files
2. prose_body
Use \n for line breaks


### summary

Concise narrative execution label.

This is NOT the full prose payload.

Purpose:

- runtime labeling
- traversal visibility
- execution coherence
- export summaries
- subevent planning

Example:

```text
Shay decides to return to the Gilded Lily
```

`summary` and `prose_body` are distinct runtime surfaces and MUST NOT be collapsed together.

## Subevent Structure Contract

Book 1 prose execution distinguishes between:

```text
subevent beat line
```

and:

```text
subevent prose body
```

These are separate runtime surfaces.

---

### Beat Line

The beat line is:

- concise
- deterministic
- execution-oriented
- summary-like

Example:

```text
Shay decides to return to the Gilded Lily
```

---

### Prose Body

The prose body is the rendered narrative prose associated with the subevent.

A prose body may contain:

- multiple sentences
- dialogue
- interiority
- sensory detail
- action
- descriptive narration

Example:

```text
Shay paces the apartment while the dream continues to vibrate through her nervous system. By the time the morning light begins to thin the room, she has already decided she needs to return to the Gilded Lily before the feeling disappears.
```

---

## Runtime Mapping Rules

A prose draft may materialize into:

```text
multiple ordered subevents
```

Each subevent may contain:

```text
- one beat line
- one prose body
- one order position
```

The runtime MUST NOT assume:

```text
one sentence = one subevent
```

The runtime MUST NOT assume:

```text
one prose block = one subevent
```

The runtime MUST NOT collapse:

```text
beat line
```

into:

```text
prose body
```

Beat lines define execution structure.

Prose bodies define rendered narrative content.

Line order is preserved and used for deterministic execution
3. draft_status_id

Must exist in:

entities.id WHERE entity_type_id = 'entity_type_status'
4. ## Projection Rules

### projection.projection_id

Canonical projection runtime identity.

Example:

```text
1
```

Runtime systems MUST internally propagate:

```text
projection_id
```

for all execution and projection operations.

### projection.target_entity_id

Compatibility ingress event identity.

Format:

```text
calendar_event:<id>
```

Rules:

Rules:

- MUST resolve to an existing row in `calendar_events`
- the resolved row MUST have:

```text
layer_id = calendar_layer_event
```

- week/day/time rows are INVALID targets even if their entity_id format is:
  
```text
calendar_event:<id>
```

- prose-generated subevents attach beneath this event only
- MUST belong to the resolved projection_id
- MUST represent the validated event-layer parent
- prose-generated subevents attach beneath this event only

Book 1 prose MUST NEVER attach directly to:

- week nodes
- day nodes
- time nodes
- subevent nodes

Valid runtime transition only:

```text
calendar_layer_event
→ calendar_layer_subevent
```

INVALID:

```json
{
  "target_entity_id": "calendar_event:311"
}
```

when the resolved row has:

```text
layer_id = calendar_layer_week
```

or:

```text
calendar_layer_day
```

or:

```text
calendar_layer_time
```

`target_entity_id` MUST resolve specifically to:

```text
calendar_layer_event
```

5. annotations

Must always be an array:

"annotations": []
Next Step (REQUIRED)

Execute batch to create subevents:

POST /pecherie/chill-api/index.php
```text
{
  "operation": "executeCalendarBatchFromProse",
  "parent_event_entity_id": "calendar_event:322",
  "prose": "Line 1\nLine 2\nLine 3"
}
```
## Execution Input Rule
executeCalendarBatchFromProse uses the provided "prose" string.
It does NOT read from stored prose_draft records.
### Execution Guarantees
* Deterministic: same prose → same plan_id
* Idempotent: safe retries
* Replay-safe: no duplicate subevents
* Parallel-safe: enforced by DB uniqueness

## Runtime Validation Requirement

Before createProseDraft execution:

the runtime hierarchy MUST already be validated.

Minimum required validation order:

```text
Projection
→ Week
→ Day
→ Time
→ Event
```

The GPT MUST NOT create prose payloads before successful hierarchy validation.

If any hierarchy layer fails validation:

- prose generation stops
- insertion stops
- traversal stops
- hierarchy inference is 

## Canonical Runtime Event Validation Query

Before `createProseDraft` execution, the runtime MUST resolve the concrete executable event instance from:

```text
calendar_events
```

using deterministic hierarchy predicates.

Canonical event validation query:

```sql
SELECT
    id,
    entity_id,
    layer_id,
    chronology_address,
    summary
FROM calendar_events
WHERE layer_id = 'calendar_layer_event'
  AND week_index = <week_index>
  AND day_index = <day_index>
  AND time_index = <time_index>
ORDER BY chronology_address ASC, id ASC;
```

Example:

```sql
SELECT
    id,
    entity_id,
    layer_id,
    chronology_address,
    summary
FROM calendar_events
WHERE layer_id = 'calendar_layer_event'
  AND week_index = 3
  AND day_index = 1
  AND time_index = 1
ORDER BY chronology_address ASC, id ASC;
```

The resolved runtime attachment identity MUST be:

```text
calendar_event:<id>
```

Example:

```text
calendar_event:311
```

This value becomes:

```json
{
  "target_entity_id": "calendar_event:311"
}
```

---

## Runtime Query Constraints

Runtime traversal queries MUST:

- query `calendar_events` directly
- constrain by hierarchy indices
- constrain by explicit `layer_id`
- use deterministic ordering

Runtime traversal queries MUST NOT:

- depend on nullable projection ordering metadata
- sort using projection_sequence
- infer chronology reconstruction
- use layer categories as attachment identities
- dynamically synthesize runtime hierarchy

Projection tables are materialization/projection surfaces only.

Executable runtime hierarchy resolution is authoritative in:

```text
calendar_events
```

## Forbidden Attachment Fields

The prose draft contract MUST NOT accept layer-class identifiers as runtime attachment targets.

The following fields are forbidden for prose attachment resolution:

```json
{
  "layer_id": "calendar_layer_event"
}
```

```json
{
  "target_layer": "calendar_layer_event"
}
```

```json
{
  "calendar_layer": "calendar_layer_event"
}
```

```json
{
  "parent_layer_id": "calendar_layer_event"
}
```

These values identify materialization layer classes, NOT concrete runtime parent instances.

The runtime requires a resolved executable calendar event instance.

Correct attachment requires:

```json
{
  "projection_id": "...",
  "week_id": "...",
  "day_id": "...",
  "time_id": "...",
  "event_id": "..."
}
```

Subevent prose may only attach beneath a resolved:

```text
calendar_layer_event:<event_id>
```

The prose runtime MUST reject any payload attempting to attach prose directly to:

- `calendar_layer_week`
- `calendar_layer_day`
- `calendar_layer_time`
- `calendar_layer_event`
- `calendar_layer_subevent`

Reason:

Layer identifiers describe runtime structure categories, not executable parent identities.

## Layer Type vs Runtime Instance Identity

Book 1 prose execution depends on concrete runtime hierarchy resolution.

The following distinction is mandatory:

| Concept | Meaning | Valid Attachment Target |
|---|---|---|
| `calendar_layer_event` | A materialization layer class | NO |
| `calendar_layer_event:<event_id>` | A concrete runtime event instance | YES |

Incorrect:

```json
{
  "layer_id": "calendar_layer_event"
}
```

Why this fails:

- `calendar_layer_event` identifies a runtime layer category
- it does NOT identify a specific executable calendar event
- prose cannot attach to an abstract layer type

Correct runtime attachment requires a resolved concrete parent event:

```json
{
  "event_id": "evt_123"
}
```

The runtime hierarchy is:

```text
Projection
→ Week
→ Day
→ Time
→ Event
→ Subevent prose
```

Prose attachment is only valid AFTER the runtime resolves a concrete `event_id`.
## Deterministic Hierarchy Resolution Requirements

Book 1 prose is executable projection-backed calendar state.

Therefore prose placement MUST follow deterministic calendar hierarchy traversal.

Required execution order:

```text
Projection
→ Week
→ Day
→ Time
→ Event
→ Subevent prose
```

The GPT/runtime MUST NOT:

- jump directly from prose intent to event targeting
- infer events before week/day/time resolution
- request event selection prematurely
- attach prose using layer-class identifiers

The GPT/runtime MUST resolve:

```json
{
  "projection_id": "...",
  "week_id": "...",
  "day_id": "...",
  "time_id": "...",
  "event_id": "..."
}
```

before prose draft creation may proceed.

The following payload is INVALID:

```json
{
  "layer_id": "calendar_layer_event"
}
```

Reason:

The payload specifies a layer category but fails to resolve a concrete runtime event instance.

The runtime must instead resolve:

```text
calendar_layer_event:<event_id>
```

Only after event resolution succeeds may prose materialize beneath:

```text
calendar_layer_subevent
```


## Runtime Semantics

Book 1 prose is executable projection-backed calendar state.

Prose drafts are not freeform narrative attachments.

During execution:

```text
prose_body
→ deterministic beat ordering
→ calendar_layer_subevent generation
→ chronology propagation
→ projection materialization
```

Hierarchy integrity is required for deterministic replay safety.

