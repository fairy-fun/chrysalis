=========================================
private/docs/calendar/calendar_write_contract.md
=========================================

# Calendar System — Write Contract (Replay-Safe Architecture)

## Core Write Path
Planner → Orchestrator → Subevent Service → Ensurer → DB

All calendar writes must terminate in:

private/framework/calendar/calendar_node_ensurer.php

### Identity Model
#### Structural Identity (Node Uniqueness)

projection_entity_id
+ layer_id
+ parent_event_id
+ sequence_index
  
### Execution Identity (Write Idempotency)
  client_id (UNIQUE)
  Enforced by DB constraint
  Prevents duplicate writes 
  
### Plan Identity (Determinism)
  plan_id = hash(parent_event_entity_id + operations)
  Same input → same plan_id
  Stable across retries
  Subevent Definition

A subevent is:

layer_id = calendar_layer_subevent
parent_event_id IS NOT NULL
Batch Execution (PRIMARY ENTRYPOINT)

POST /pecherie/chill-api/index.php

{
"operation": "executeCalendarBatchFromProse",
"parent_event_entity_id": "calendar_event:322",
"prose": "Line 1\nLine 2\nLine 3"
}
Execution Flow
1. Planner
   Deterministic
   Produces ordered operations[]
   Generates plan_id
2. Orchestrator
   Assigns:
   client_id = <plan_id>:<index>
   Executes sequentially
3. Subevent Service (SINGLE SOURCE OF TRUTH)

Responsibilities:

Early idempotency lookup by client_id
Parent validation
Field inheritance
Payload shaping
Duplicate-key recovery

All domain logic lives here.

4. Ensurer
   calendar_node_ensurer.php

Responsibilities:

Structural identity only
sequence_index allocation
DB writes

No domain logic.

Idempotency Model
Mechanisms
Early lookup:
SELECT by client_id
DB enforcement:
UNIQUE(client_id)
Recovery:
On duplicate key → fetch existing row
Guarantees
Safe retries
Safe parallel execution
Partial completion recovery
Deterministic outputs
Direct Subevent Creation (LOW-LEVEL)
{
"operation": "createCalendarSubevent",
"parent_event_entity_id": "calendar_event:322",
"event_label": "Beat",
"client_id": "plan_id:0"
}

Rules:

client_id strongly required for correctness
Must be globally unique
Used by orchestrator
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
Service defines meaning
Ensurer defines structure
DB enforces uniqueness