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
Each non-empty line represents exactly one subevent (beat)
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

- MUST resolve to an existing calendar_layer_event
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
- hierarchy inference is forbidden

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

