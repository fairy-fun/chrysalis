=========================================
private/docs/prose/create_prose_draft.md
=========================================
Prose Draft → Calendar Subevent Workflow (Canonical)
Overview

This system is strictly two-step:

1. createProseDraft
2. executeCalendarBatchFromProse

No calendar writes occur in step 1.

Step 1 — Create Prose Draft

POST /pecherie/chill-api/index.php

{
"operation": "createProseDraft",
"entity_id": "prose_draft:example-1",
"title": "Example",
"prose_body": "Arrive\nSetup\nBegin session",
"draft_status_id": "prose_status_draft",
"author_entity_id": null,
"projection": {
"projection_type_id": "projection_type_book",
"target_entity_id": "calendar_event:322",
"role_id": "primary",
"projection_order": 1,
"is_export_target": 1
},
"annotations": []
}
Result
Prose is stored
No calendar structure is modified
Step 2 — Execute Batch (PRIMARY ENTRYPOINT)

POST /pecherie/chill-api/index.php

{
"operation": "executeCalendarBatchFromProse",
"parent_event_entity_id": "calendar_event:322",
"prose": "Arrive\nSetup\nBegin session"
}
Parent Event Rules (CRITICAL)
parent_event_entity_id MUST:
- refer to a calendar_layer_event node
- match projection.target_entity_id from the prose draft

Subevents are never attached to:

week
day
time

Only:

calendar_layer_event → calendar_layer_subevent
Deterministic Mapping
Each non-empty line in prose produces exactly one subevent,
in order, with stable indexing.
Execution Uses Provided Prose
Batch execution uses the prose string in the request.
It does NOT load or depend on stored prose_draft records.
Internal Architecture
Prose
→ Planner (deterministic)
→ operations[]
→ plan_id

→ Orchestrator
→ assigns client_id = <plan_id>:<index>
→ executes in order

→ Subevent Service (SSOT)
→ early idempotency lookup
→ validation
→ inheritance
→ payload shaping
→ duplicate-key recovery

→ Ensurer
→ structural write only

→ DB
→ UNIQUE(client_id)
Identity Model
Structural Identity (Node Uniqueness)
projection_entity_id
+ layer_id
+ parent_event_id
+ sequence_index
  Execution Identity (Write Idempotency)
  client_id (UNIQUE)
  Plan Identity (Determinism)
  plan_id = hash(parent_event_entity_id + operations)
  Replay Behavior
  First Run
  executed_count = N
  idempotent_count = 0
  Second Identical Run
  executed_count = 0
  idempotent_count = N
  Same entity_ids returned
  No duplicates created
  Response Shape
  {
  "success": true,
  "data": {
  "executed_count": 3,
  "idempotent_count": 0,
  "entity_ids": [
  "calendar_event:901",
  "calendar_event:902",
  "calendar_event:903"
  ]
  }
  }
  Rules
  ❌ Do NOT write directly to calendar_events
  ❌ Do NOT attach subevents to non-event nodes
  ❌ Do NOT generate client_id manually for batch use
  ❌ Do NOT assume drafts are used during execution
  ❌ Do NOT persist inference
  ✅ Always use executeCalendarBatchFromProse for prose-driven subevents
  Low-Level Endpoint (Restricted Use)
  {
  "operation": "createCalendarSubevent",
  "parent_event_entity_id": "calendar_event:322",
  "event_label": "Beat",
  "client_id": "plan_id:0"
  }
  This endpoint MUST NOT be used for batch creation.
  It exists only for controlled or orchestrated single writes.