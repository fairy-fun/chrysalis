# Ask User For Target Event

## Purpose

Collect explicit target-event intent from the user for workflows that require a calendar event reference.

This is a bounded operator primitive.

This procedure maps directly onto the runtime workflow input state:

```text
await_calendar_event_entity_id
```
This procedure exists ONLY to satisfy the workflow requirement that a target event identifier be explicitly supplied.

Runtime State Mapping

This operator procedure is responsible for fulfilling:

workflow: calendar_event_add_prose
state: await_calendar_event_entity_id
expected_input: entity_id

Source workflow:

private/framework/procedures/workflow_calendar_event_add_prose_definition.php

Canonical runtime transition:

await_calendar_event_entity_id
    -> validate_calendar_event_entity

This operator procedure STOPS before validation.

Validation belongs to the next runtime state.

Entry Condition

Run this procedure when:

a prose workflow requires a target calendar event
no authoritative event identifier has yet been collected
runtime state is:
await_calendar_event_entity_id
Operator Responsibilities

The operator MUST:

ask the user which event they want to attach prose to
obtain explicit target-event intent
continue clarification until intent is unambiguous
emit workflow-compatible input
stop immediately
## Canonical User Prompt

Canonical prompt:

Which event would you like to attach prose to?

The operator MAY accept:

calendar_event.entity_id
chronology address
stable event reference already used by the system
explicit user-provided event naming sufficient for later resolution

The operator MUST preserve the original user-supplied identifier.

Ambiguity Resolution Loop

If the supplied event reference is ambiguous:

ask a narrowing question
do not guess
do not inspect latest-event state
do not auto-select candidates

Canonical clarification shape:

I found multiple possible events matching that description.
Which exact event do you mean?

Remain inside this procedure until:

exactly one explicit target reference is identified
or the user abandons the workflow
Forbidden Actions

This procedure MUST NOT:

infer the latest event
inspect recent calendar state
auto-resolve likely targets
validate entity existence
inspect projections
collect prose text
execute workflow transitions
mutate user intent into inferred canonical state

This procedure is input collection ONLY.

Required Runtime-Compatible Output

When explicit target-event intent is obtained, emit:

input:
  entity_id: <user_supplied_identifier>

Example:

input:
  entity_id: cal_evt_01JV...

If the user did not supply a literal entity_id but instead supplied another stable reference form:

target_event:
  identification_method: chronology_address
  user_supplied_value: 2026/W20/D1/T03/E02

The downstream resolution procedure is responsible for converting alternate identifiers into canonical entity state.

Deterministic Stop Condition

Stop immediately after one of the following:

Success Stop

Stable workflow-compatible target-event input has been emitted.

OR

Abort Stop

The user abandons the operation before supplying target-event intent.

Explicit Non-Responsibilities

The following runtime states belong to OTHER procedures:

validate_calendar_event_entity
route_calendar_event_layer
await_projection_binding
validate_projection_binding
await_prose_text
persist_prose_draft

This procedure terminates before those states execute.

## Incoming User Input Handling

When the user supplies a value, it MUST be treated as a *target-event reference*, not necessarily a canonical entity_id.

Examples of valid non-canonical inputs:
- calendar_event:7
- "last meeting with design team"
- 2026/W20/D1/T03/E02
- cal_evt_01JV...

The operator MUST NOT assume all inputs are already normalized.

---

## Resolution Boundary

This procedure does NOT resolve event references.

If the user provides ANY target-event reference:
- pass it forward unchanged
- do not validate existence
- do not interpret layer or projection state

Normalization is handled by:
private/docs/prose/author_flow/resolve_target_event.md

---

## Updated Success Output Contract

If the user supplies a reference, emit:

input:
  raw_target_event_reference: <user_supplied_value>

If and only if the user supplies a known canonical entity_id format:
input:
  entity_id: <canonical_id>
  source: user_supplied