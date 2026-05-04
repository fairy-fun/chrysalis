=========================================

## private/docs/prose/create_prose_draft.md

=========================================
# Prose Draft → Calendar Subevent Workflow (Canonical)
Overview

This is a two-step system:

1. createProseDraft
2. executeCalendarBatchFromProse

No calendar subevents are created during draft creation.

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
No calendar writes occur
Step 2 — Execute Batch (PRIMARY)

POST /pecherie/chill-api/index.php

{
"operation": "executeCalendarBatchFromProse",
"parent_event_entity_id": "calendar_event:322",
"prose": "Arrive\nSetup\nBegin session"
}
What Happens Internally
Prose
→ Planner (deterministic)
→ operations[]
→ plan_id

→ Orchestrator
→ assigns client_id = <plan_id>:<index>
→ executes in order

→ Subevent Service (SSOT)
→ idempotency check
→ validation
→ inheritance
→ payload shaping

→ Ensurer
→ structural write

→ DB
→ UNIQUE(client_id)
Identity Model
1. Structural Identity
   projection_entity_id
+ layer_id
+ parent_event_id
+ sequence_index

Prevents duplicate nodes.

2. Execution Identity
   client_id (UNIQUE)

Prevents duplicate writes.

3. Plan Identity
   plan_id = hash(prose → operations)

Ensures deterministic replay.

Replay Guarantees

Running the same request twice:

First run
executed_count = N
idempotent_count = 0
Second run
executed_count = 0
idempotent_count = N
Same entity_ids returned
No duplicates created
Rules
❌ Do NOT write directly to calendar_events
❌ Do NOT generate your own client_id outside orchestration
❌ Do NOT bypass batch endpoint for bulk creation
❌ Do NOT persist inference
✅ Use executeCalendarBatchFromProse for all prose-driven subevents
Low-Level Endpoint (Advanced Only)
{
"operation": "createCalendarSubevent",
"parent_event_entity_id": "calendar_event:322",
"event_label": "Beat",
"client_id": "plan_id:0"
}
Requires correct client_id
Used by orchestrator
Not intended for manual batch usage