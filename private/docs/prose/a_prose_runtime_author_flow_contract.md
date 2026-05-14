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

Canonical Workflow Shape
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