# Getting Started Writing Prose

Book 1 prose insertion is runtime execution traversal,
not narrative attachment.

## Book 1 Runtime Onboarding Flow

A new prose-writing session MUST resolve the calendar hierarchy in order.

The GPT MUST NOT jump directly to event targeting.

Required resolution order:
```text
Projection
→ Week
→ Day
→ Time
→ Event
→ Subevent prose
```

### Step 1 — Determine Whether This Is Book 1 Projection Prose

Ask the user:

```text
Does this prose belong in the Book 1 projection?
```
If the answer is:
```text
No
```
then standard non-calendar prose rules may apply.

If the answer is NO:

* non-calendar prose rules may apply
* calendar execution hierarchy does not activate

If the answer is:
```text
YES
```
the prose becomes executable projection-backed calendar state.

The GPT MUST immediately read:
```text
private/docs/calendar/a_calendar_execution_contract.md
```
before continuing.

At this stage, the GPT MUST NOT:

* request a week
* request a day
* request a time
* request an event
* generate prose
* infer hierarchy placement
* create subevents


The only valid next operation is runtime hierarchy resolution beginning at the week layer.
Book 1 prose requires hierarchical runtime resolution first.

### Step 2 — Resolve the Target Week

After reading the execution contract, the GPT MUST ask:
```text
What week does this prose belong to?
```

The week is the root execution anchor for Book 1 prose traversal.

The GPT MUST validate the requested week against the live runtime before any deeper hierarchy resolution occurs.

The GPT MUST NOT:

* assume the week exists
* infer missing structure
* fabricate hierarchy
* continue to day resolution
* continue to time resolution
* continue to event targeting
* generate prose
* create subevents

until valid week resolution succeeds.

Only after valid week resolution may the GPT continue to:

* day selection
* time selection
* event targeting
* prose batching
* subevent execution

#### Canonical Runtime Validation Query

Book 1 week validation MUST query:

calendar_events

using:

- layer_id = 'calendar_layer_week'
- week_index = <requested week>

Canonical validation query:

```text
SELECT
    id,
    entity_id,
    layer_id,
    week_index,
    summary,
    chronology_address
FROM calendar_events
WHERE layer_id = 'calendar_layer_week'
  AND week_index = <requested_week>
LIMIT 10;
```
A valid result confirms the runtime week exists.

Example valid result:
```text
id: 299
entity_id: calendar_event:299
layer_id: calendar_layer_week
week_index: 3
summary: Week 3 — Serve-More Cycle Begins
chronology_address: 3
```

#### Required Behavior

If the week exists:

* acknowledge the resolved week
* continue to day selection

If the week does not exist:

* stop execution
* do not infer hierarchy
* do not create prose attachments
* do not fabricate runtime structure
#### Important Constraint

Week validation occurs before:

day lookup
time lookup
event lookup
prose batching
subevent generation

The GPT MUST NOT jump directly to event targeting before week resolution succeeds.

This is important because you’re effectively training the prose system into:

* deterministic hierarchy traversal
* runtime-first grounding
* explicit execution gating

instead of “narrative guessing.”

### Step 3 — Resolve the Target Day

Only after successful week validation may the GPT continue to day resolution.

Ask the user:

```text
What day within the resolved week does this prose belong to?
```
The GPT MUST validate the day exists beneath the resolved week before continuing.

The GPT MUST NOT:

* infer day placement
* skip directly to time targeting
* skip directly to event targeting
* generate prose
* create subevents

until valid day resolution succeeds.


This is important because it operationalizes:

```text
Week
→ Day
→ Time
→ Event
→ Subevent
```

instead of treating week validation as a one-off special case.

## Deterministic Runtime Traversal

Book 1 prose insertion is hierarchical runtime traversal.

The GPT MUST resolve each layer in order:

Week
→ Day
→ Time
→ Event
→ Subevent prose

The GPT MUST NOT skip layers.

Each hierarchy layer must be validated before traversal continues.

This prevents:

- orphaned prose
- invalid chronology propagation
- projection corruption
- detached subevent execution
- inferred narrative placement

## Runtime Traversal Failure Rules

If any hierarchy layer cannot be validated:

- traversal stops
- prose generation stops
- subevent generation stops
- hierarchy inference is forbidden
- synthetic structure creation is forbidden

The GPT MUST wait for valid runtime structure before continuing execution.

Book 1 prose insertion is runtime execution traversal,
not narrative attachment.

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
