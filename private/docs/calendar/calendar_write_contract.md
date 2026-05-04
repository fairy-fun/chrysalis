=========================================
private/docs/calendar/calendar_write_contract.md
=========================================
Calendar System — Write Contract (Replay-Safe Architecture)
Core Write Path
Planner → Orchestrator → Subevent Service → Ensurer → DB

All writes terminate in:

private/framework/calendar/calendar_node_ensurer.php
Identity Model
Structural Identity
projection_entity_id
+ layer_id
+ parent_event_id
+ sequence_index

Ensures no duplicate nodes.

Execution Identity
client_id (UNIQUE)
Enforced at DB level
Prevents duplicate writes
Plan Identity
plan_id = hash(parent_event_entity_id + operations)
Deterministic
Stable across retries
Subevent Definition
layer_id = calendar_layer_subevent
parent_event_id IS NOT NULL

Parent must be:

calendar_layer_event
Primary Write Endpoint

POST /pecherie/chill-api/index.php

{
"operation": "executeCalendarBatchFromProse",
"parent_event_entity_id": "calendar_event:322",
"prose": "Line 1\nLine 2\nLine 3"
}
Deterministic Execution Rule
Each non-empty line → exactly one operation → exactly one subevent
Order is preserved and stable
Execution Flow
1. Planner
   Deterministic
   Produces ordered operations
   Generates plan_id
2. Orchestrator

Assigns:

client_id = <plan_id>:<index>

Executes in strict order.

3. Subevent Service (SINGLE SOURCE OF TRUTH)

Responsibilities:

Early lookup by client_id
Parent validation
Field inheritance
Payload shaping
Duplicate-key recovery

No domain logic exists outside this layer.

4. Ensurer
   calendar_node_ensurer.php

Responsibilities:

Structural identity
sequence_index allocation
DB persistence

No semantic logic.

Idempotency Model
1. Early Lookup
   SELECT * FROM calendar_events WHERE client_id = ?
2. DB Constraint
   UNIQUE(client_id)
3. Recovery

On duplicate key:

fetch existing row
return existing entity_id
Guarantees
Safe retries
Safe parallel execution
Partial completion safe
Deterministic outputs
Response Shape
{
"success": true,
"data": {
"executed_count": N,
"idempotent_count": M,
"entity_ids": []
}
}
Low-Level Endpoint (Restricted)
{
"operation": "createCalendarSubevent",
"parent_event_entity_id": "calendar_event:322",
"event_label": "Beat",
"client_id": "plan_id:0"
}
MUST NOT be used for batch creation.
Used only by orchestrator or controlled single writes.
Structural Rules
❌ client_id is NOT part of structural identity
❌ No direct INSERT into calendar_events
❌ No DB triggers
❌ No inference persistence
❌ No alternate write paths
Layer Model
week
day
time
event
subevent (calendar_layer_subevent)
Final Principle
Planner defines intent
Orchestrator defines execution
Service defines domain logic
Ensurer defines structure
DB enforces uniqueness

## Parent Validation (Database)

Before creating subevents, the parent node MUST be verified at the database level.

### Rule

```text
parent_event_entity_id MUST resolve to a node where:

layer_id = calendar_layer_event
```

#### Verification Query
```sql
SELECT layer_id
FROM calendar_events
WHERE entity_id = 'calendar_event:<id>';
```
#### Constraint

Subevents may only be created under:

calendar_layer_event → calendar_layer_subevent

Any other parent layer is invalid and MUST NOT be used.