# Proposed Top-Level Structure

`private/docs/prose/a_prose_runtime_author_flow_contract.md
`
## Prose Runtime Author Flow Contract
### Purpose

Defines the operator-facing workflow layer that sits above the canonical runtime resolution system.

This contract governs:

* workflow phase orchestration
* runtime-driven routing
* interview minimization
* bounded author interaction
* phase stopping rules
* next-chat handoff packaging
* operational escalation doctrine

This contract does NOT redefine:

* calendar runtime ontology
* projection ontology
* prose publication ontology
* chronology semantics
* latest-event resolution semantics

Those are governed separately by:

a_latest_event_prose_resolution_contract.md
a_calendar_execution_contract.md
create_prose_draft_json_contract.md
### Core Runtime Doctrine

The runtime owns:

projection discovery
latest-event discovery
publication resolution
missing-state diagnosis
chronology traversal
runtime state inspection

The operator owns:

intent
prose authorship
editorial decisions
ambiguity resolution when escalation is required

The runtime MUST resolve as much state as possible before interviewing the operator.

The operator MUST NOT manually traverse hierarchy unless runtime resolution fails.


## Document-Routed Workflow Doctrine

After author intent is classified, the runtime MUST either:

1. continue directly to the next correct workflow section when enough runtime state is already resolved, or
2. emit a bounded NEXT CHAT START PACK that tells the operator exactly which document/section to feed the next chat.

The operator should never need to decide which contract, guide, or runtime file applies next.

# Intent → Document Routing Map

## inspect latest prose state

Route first to:

- `a_latest_event_prose_resolution_contract.md`

If runtime state resolves to `prose_found`, continue directly to inspection output.

If state resolves to missing or ambiguous state, route to the matching repair/interview section.

---

## create missing event

Route first to:

- `a_calendar_execution_contract.md`
- event creation protocol document, if event slot is inferable

Do not ask week/day/time/event if the resolver already produced an inferable slot.

---

## create prose draft

Route first to:

- `create_prose_draft.md`
- `create_prose_draft_json_contract.md`

Only route here after a concrete `calendar_layer_event` instance is resolved.

---

## continue prose / revise prose

Route first to latest prose resolution.

If prose exists, continue directly to authoring action.

If no prose exists, route to create prose draft.

---

## import / paste prose

Route first to:

- `a_guide_to_getting_started_writing_prose.md`

But skip hierarchy interview if runtime resolution has already resolved the target event.

Then route to segmentation doctrine if prose must become subevents.

### add prose via Postman to existing event

Intent tier: second-tier operational intent.

Parent intent family:

- create prose draft
- import / paste prose

### Entry Condition

The operator wants to attach prose to an already-existing concrete calendar event using the API/Postman flow.

### Required Runtime State

Before routing here, the runtime MUST resolve:

- projection
- concrete `calendar_layer_event` instance
- target event `entity_id`
- publication/prose state for that event

The runtime MUST NOT ask for week/day/time/event if the existing event has already been resolved.

### Route First To

- `private/docs/prose/a_prose_runtime_author_flow_contract.md`
- `private/docs/prose/create_prose_draft.md`
- `private/docs/prose/create_prose_draft_json_contract.md`
- `private/framework/prose/prose_draft_creator.php`
- API route surface for prose draft creation

### Skip Rules

If the concrete event is already resolved, skip hierarchy traversal.

If prose body is already supplied, skip prose drafting interview.

If publication target is already resolved, skip publication-target interview.

### Correct Action Path

```text
resolve existing event
→ confirm target event identity
→ prepare createProseDraft JSON payload
→ submit via Postman
→ verify prose draft / projection publication state
→ emit NEXT CHAT START PACK

---

## segment prose

Route first to:

- `prose_subevent_segmentation_doctrine.md`
- `a_calendar_execution_contract.md`

Only proceed after resolved `calendar_layer_event:<id>` parent exists.

---

## verify runtime state

Route first to the relevant runtime resolver contract.

Do not ask authoring questions.

---

## export prose

Route first to export resolution contract / prose export resolver documentation.

Do not infer export identity from newest draft or projection type.

### Canonical Workflow Shape
author intent
→ runtime resolution
→ interview routing
→ targeted action phase
→ bounded stop
→ next-chat handoff

The workflow is:

state-driven
bounded
phase-oriented
operational

NOT:

exploratory brainstorming
architecture redesign
giant multi-hour authoring sessions
uncontrolled hierarchy traversal
Workflow Phase Map
Phase 0 — Intent Intake

Purpose:

Resolve the operator’s immediate narrative or operational intent.

Examples:

continue prose
inspect latest prose
revise scene
import prose
segment prose
verify runtime state
export prose
Entry Condition

New operator request.

Output

Canonical workflow intent classification.

Transition

Route immediately into runtime resolution.

Phase 1 — Runtime Resolution

Purpose:

Resolve all canonical runtime state before interviewing.

The runtime attempts to resolve:

active projection
latest executable event
publication state
latest prose state
missing runtime hierarchy
ambiguity state
Runtime Sources

Canonical runtime chain:

calendar_projections
→ calendar_events(sequence_index)
→ prose_projections(target_entity_id)
→ prose_drafts(published_prose_draft_id)
Output

One of:

prose_found
latest_event_exists_no_prose
latest_event_slot_inferable
projection_missing
publication_missing
publication_ambiguous
Transition

Route into:

direct action
bounded interview
escalation
Phase 2 — Interview Routing

Purpose:

Ask only the minimum unresolved question required to continue.

Core Doctrine

The runtime MUST NOT ask upstream questions once downstream state is already resolved.

Example:

If projection and latest event are canonical,
the runtime MUST NOT ask:

what week
what day
what event

unless canonical resolution fails.

Allowed Interview Types
Missing-State Interview

Example:

No executable event exists.
Create the next event now?
Ambiguity Resolution Interview

Example:

Multiple published prose bindings exist.
Which prose projection role is canonical?
Intent Clarification Interview

Only allowed when operator intent itself is ambiguous.

Phase 3 — Targeted Action Phase

Purpose:

Execute one bounded operational action.

Examples:

create missing event
create prose draft
continue prose
revise prose
segment prose
inspect prose state
export prose
Core Doctrine

One workflow window per chat.

The runtime MUST prevent uncontrolled context expansion.

Allowed Outputs
created runtime state
revised prose
generated export
segmented prose
verification result
Forbidden Expansion

The runtime MUST NOT spontaneously branch into:

ontology redesign
architecture redesign
unrelated hierarchy repair
broad future planning

unless explicitly escalated.

Phase 4 — Verification + Runtime Reconciliation

Purpose:

Verify the runtime state after action execution.

Examples:

prose attached successfully
publication canonicalized
event now resolvable
segmentation persisted
export generated
Output

Verified runtime state summary.

Transition

Proceed immediately into bounded stop.

Phase 5 — Bounded Stop

Purpose:

Terminate the workflow before context overload.

Mandatory Stop Conditions

A workflow phase MUST terminate when:

the intended bounded action is complete
the next action belongs to a distinct workflow phase
runtime state is now stable
context accumulation begins expanding laterally
The runtime MUST NOT:
continue indefinitely
chain multiple major authoring operations
expand into unrelated planning
Phase 6 — Next-Chat Handoff

Purpose:

Emit a deterministic continuation package.

Every workflow phase terminates with:

current resolved runtime state
completed action
unresolved next action
exact next documents
exact first instruction for next chat
Routing Doctrine
Runtime-First Routing

The runtime MUST always attempt automatic resolution before interviewing.

Priority order:

runtime resolution
→ direct continuation
→ minimal interview
→ escalation
Escalation Routing

Escalation is allowed only when canonical runtime resolution cannot continue safely.

Examples:

projection ambiguity
publication ambiguity
unresolved chronology contradiction
missing canonical runtime identity

Escalation MUST remain narrowly scoped.

Interview Doctrine
Minimum Necessary Question Rule

The runtime asks only the next unresolved question.

Never re-ask resolved hierarchy.

Never force manual traversal when runtime state already exists.

Downstream Priority Rule

Resolved downstream state suppresses upstream interviews.

Example:

If latest executable event is canonical,
do not ask hierarchy-navigation questions.

Interview Stop Rule

Interviews terminate immediately once enough state exists to proceed.

Context Boundary Doctrine

Each chat contains:

one bounded workflow window
one operational phase cluster
one primary intent

The runtime MUST aggressively prevent:

runaway context accumulation
mixed operational phases
architecture brainstorming during author workflows
Stopping Rules

The runtime MUST stop when:

bounded action completed
runtime stable
next phase identified
next action requires fresh context

The runtime MUST emit a structured handoff immediately at stop boundary.

Handoff Template
NEXT CHAT START PACK
Current Runtime State
projection:
latest_event:
publication_state:
latest_prose_state:
Completed Action
[action summary]
Remaining Next Action
[next bounded operational task]
Required Documents
exact docs
exact runtime files
First Instruction For Next Chat
[first operational instruction]
Initial Supported Workflow Families
Runtime Inspection
inspect latest prose state
inspect publication state
inspect runtime hierarchy state
Authoring
continue prose
revise prose
create prose draft
Runtime Construction
create missing event
create missing publication binding
Ingestion
import prose
paste prose
segment prose
Export
export prose
export narrative state
Architectural Doctrine

The workflow layer is:

a guided operational console

NOT:

a freeform conversational brainstorming environment.

The runtime mediates operational flow.

The operator supplies narrative intent.