# Getting Started Writing Prose

## First Question (CRITICAL)

Before writing prose, ask the user:

```text
Does this prose belong in the Book 1 projection?
```

If the answer is:
```text
YES
```

then the prose is not just free text.

It participates in the canonical calendar execution pipeline and MUST follow the Book 1 projection execution contract.

## Required Reading
If the prose belongs to the Book 1 projection, read:
```text
private/docs/calendar/a_calendar_execution_contract.md
```

before:

* drafting prose
* attaching prose to calendar events
* generating subevents
* creating projections
* materializing timeline structure

This document defines the authoritative execution and projection rules.

### Why This Matters

Book 1 prose is projection-backed runtime content.

That means prose is connected to:

* calendar hierarchy
* projection execution
* chronology
* deterministic subevent generation
* narrative materialization

Incorrect handling can corrupt:

* projection ordering
* event hierarchy
* chronology integrity
* execution identity
* projection materialization
## Runtime Model

For Book 1 projection prose:
``` text
createProseDraft
→ prose persistence
→ projection persistence
→ annotation persistence
→ deterministic prose → calendar execution
→ calendar_layer_subevent generation
```

This is NOT “just writing text into a table.”

## Identity Model

Canonical runtime identity is:
``` text
projection_id
```
Compatibility ingress identities include:
``` text
projection_entity_id
calendar_event:<id>
```
Runtime systems must:

* resolve projection identity immediately
* propagate projection_id internally
* avoid runtime joins keyed by projection_entity_id
## Parent Event Rule

Calendar-backed prose must attach only to:

calendar_layer_event

Subevents are generated beneath:

calendar_layer_subevent

Never attach prose-driven execution to:

* week nodes
* day nodes
* time nodes
* subevent nodes

Valid transition only:

calendar_layer_event
→ calendar_layer_subevent
Before Writing

A new chat/session should first ask:

Does this prose belong in the Book 1 projection?

If yes:

Read:
private/docs/calendar/a_calendar_execution_contract.md
Verify the target calendar event exists
Confirm the target is:
calendar_layer_event
Then proceed with prose drafting/execution
Non-Projection Prose

If the prose does NOT belong to the Book 1 projection, then the calendar execution contract may not apply.

Examples:

dream journals
notes
detached experiments
non-calendar prose systems

Those may use prose infrastructure without calendar execution semantics.

For Book 1 projection prose insertion, the important operational model is:

- prose materializes as calendar_layer_subevent nodes
- subevents must attach to an event-layer parent only
- one deterministic prose operation = one subevent write
- stable replay identity is enforced via client_id
- projection execution is projection-scoped
- projection rows are authoritative projection runtime state
- chronology must propagate intact into projection space
- hierarchy integrity matters at every layer

The important narrative implication is that prose is not “free text attached to a story.”

It is executable projection-backed calendar state.

So when we test prose mechanics, we are effectively testing:

- projection materialization
- chronology propagation
- subevent ordering
- replay stability
- parent continuity
- Book 1 projection visibility
- deterministic execution integrity

That gives us a clean drafting model:

Week → Day → Time → Event establish runtime scaffolding

Prose becomes ordered experiential slices beneath event nodes.

Book 1 projection visibility depends on correct projection propagation and projection materialization, not merely event existence.

## Step 2 — Determine the Target Week (Book 1 Only)

If the prose is being written for the Book 1 projection, the GPT MUST ask the user which narrative week the prose belongs to before any prose generation or insertion occurs.

Example:

“What week does this prose belong to?”

The response establishes the calendar execution target.

### Required Validation

After the user specifies a week, the GPT MUST verify that the week already exists in the calendar runtime.

This verification MUST occur against the live database.

The GPT MUST NOT:

assume the week exists
invent missing weeks
attach prose to an inferred week
continue insertion against unresolved calendar structure
### Validation Goal

The system must confirm the existence of a valid:

calendar_layer_week

node for the requested week inside the Book 1 projection execution surface.

The week is the root anchor for all downstream prose placement:

Week
→ Day
→ Time
→ Event
→ Subevent (prose)

If the target week does not exist, prose execution must stop until the calendar structure is created.

### Reason

Book 1 prose is executable calendar state.

All prose insertion depends on:

valid projection membership
valid chronology propagation
valid event hierarchy
valid parent continuity

A missing week means the execution chain cannot be resolved safely.

Operational Requirement

### The GPT should:

1. ask the user for the target week
2. query the database
3. confirm the week exists
4. only then proceed to:
- day selection
- event selection
- prose batching
- subevent creation

And because we inspected the live schema, you can later tighten this into actual runtime lookup language using:

calendar_events
calendar_event_projections
week_index
layer_id
projection_id
chronology_address

without hand-waving the mechanics.