# Runtime Author Flow Overview

## Purpose

This folder defines bounded operator procedures for prose-authoring workflows.

Each document represents:

- one bounded operational action
- one resumable continuity window
- one deterministic stop condition
- one durable handoff boundary

Operator memory is NOT authoritative.

Authoritative continuity exists in:

- workflow runtime state
- repository documents
- database state
- projections
- emitted handoff outputs

The operator must reconstruct execution state from durable artifacts.

---

## Startup Behavior

Begin every new operator session with:

```text
What are we trying to do right now?

The purpose of this overview layer is ONLY to classify intent and route into the correct bounded operator procedure.

The overview layer MUST NOT:

execute workflow actions
infer unresolved runtime state
inspect latest events automatically
continue into downstream workflow execution
merge multiple procedures into one session
improvise orchestration behavior outside documented procedures
Routing Execution Rule

After selecting a procedure:

stop operating from this overview document
execute ONLY the selected operator procedure
obey that procedure’s operational boundary
obey that procedure’s deterministic stop condition
emit required handoff output
terminate cleanly

Do not automatically continue into downstream procedures.

A new procedure requires a new routing decision.

Routing Table

Classify the user request into one operational intent.

Then route into exactly one bounded operator procedure.

User wants to attach prose to an event

Route to:

ask_user_for_target_event.md

Purpose:

obtain explicit target-event intent
emit stable event-identification handoff
stop cleanly

This procedure exists ONLY for target-event input collection.

It does NOT:

infer latest event
validate event existence
inspect projections
collect prose text

Runtime alignment:

await_calendar_event_entity_id
User wants latest-event inference

Route to:

resolve_latest_target_event.md

Purpose:

inspect durable calendar state
identify latest valid target event
emit resolved event handoff
stop cleanly

This procedure is separate from asking the user for a target event.

Do not combine these procedures.

User already has a target event reference that must be validated

Route to:

resolve_target_event.md

Purpose:

validate event existence
hydrate canonical runtime event state
emit validated event context
stop cleanly

Runtime alignment:

validate_calendar_event_entity
User wants to continue prose authoring after event validation

Route to the operator procedure matching the current runtime workflow state.

Examples:

await_projection_binding
await_prose_text
persist_prose_draft

Only one runtime continuity window may execute per operator session.

Runtime Alignment Rule

Operator procedures should map directly onto runtime workflow states whenever possible.

The operator layer is not an alternative orchestration system.

The runtime workflow definition remains authoritative.

Operator procedures exist ONLY to:

collect missing runtime inputs
inspect durable runtime state
emit resumable handoff outputs
terminate deterministically
Bounded Operation Rule

Each operator session should complete exactly one bounded operational procedure.

After procedure completion:

emit durable handoff output
identify the next valid procedure if necessary
stop cleanly

Do not continue indefinitely across multiple procedures.

Canonical Handoff Examples
Target Event Input
input:
  entity_id: cal_evt_01JV...
Validated Event Context
calendar_event:
  entity_id: cal_evt_01JV...
  projection_id: projection_...
  layer_id: calendar_layer_event
Workflow Runtime State
workflow:
  workflow_id: calendar_event_add_prose
  current_state: await_prose_text
Memory Exhaustion Doctrine

Operator memory is disposable.

Continuity must survive:

chat exhaustion
operator replacement
context truncation
session termination

Therefore:

durable runtime state is authoritative
emitted handoffs are mandatory
every procedure must terminate cleanly
no procedure may rely on conversational continuity