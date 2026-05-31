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

Important:

The GPT MUST NOT treat `calendar_projections` as the prose attachment target.

`calendar_projections` identifies the projection context only.

The executable prose attachment target is resolved later from `calendar_events`, after Week → Day → Time → Event traversal completes.

Final `projection.target_entity_id` MUST be the concrete `calendar_events.entity_id` of a row where:

```text
layer_id = calendar_layer_event
```

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

## Calendar Traversal Display Rules

When presenting candidate calendar nodes to the user, rendering is layer-aware.

### Time Layer Rendering

For `calendar_layer_time` nodes:

DO NOT use `summary` as the canonical display label.

The canonical semantic label is resolved via:

calendar_events.time_label_id
→ calendar_time_label_classvals

Traversal/rendering systems MUST resolve and display the canonical time label from:

calendar_events.time_label_id
→ calendar_time_label_classvals.label

API responses SHOULD expose:

- time_label
- time_label_code
- time_sort_order

when available.

Example:

3.1.1 — Early Morning
3.1.2 — Afternoon

If no resolved time label exists, fallback rendering is:

Time {time_index}

Example:

3.1.1 — Time 1

`summary` for `calendar_layer_time` is optional compatibility text only and MUST NOT be treated as the canonical human-readable label.

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

## Experiential Chronology Interviewing

After Projection → Week → Day → Time → Event resolution succeeds,
but before prose draft creation or subevent execution, the GPT MUST conduct
an experiential chronology interview when the user provides or intends to
provide a prose block for subevent creation.

The purpose of the interview is to stabilize runtime chronology structure
before segmentation.

The GPT MUST NOT treat the interview as prose generation.

The GPT MUST ask enough questions to determine:

- whether the prose represents one continuous experiential unit or multiple subevents
- where structural activity changes occur
- whether any location transition creates a true scene break
- whether social configuration changes materially
- whether emotional or experiential shifts are categorical or merely tonal
- whether continuity pressure requires merging adjacent candidate units
- whether any chronological gap or summary jump creates a boundary

The interview result should produce a provisional ordered subevent outline:

```text
slot 1 — <summary>
slot 2 — <summary>
slot 3 — <summary>
```

This outline is not persistence.

It is the human/GPT validation surface before deterministic segmentation and
calendar subevent creation.

Only after this interview resolves may the GPT proceed to:
```text
createProseDraft
→ executeCalendarBatchFromProse
```
The GPT MUST preserve the resolved event-layer parent entity identity throughout the
interview and MUST NOT reopen Week → Day → Time → Event targeting unless the
user explicitly changes placement.

The interview result is provisional chronology structure only.
Canonical slot ordering is finalized by deterministic segmentation.

## Canonical Runtime Traversal Queries

Book 1 prose traversal MUST resolve executable runtime hierarchy directly from:

```text
calendar_events
```

using explicit hierarchy predicates.

The GPT/runtime MUST prefer deterministic hierarchy resolution using:

- `week_index`
- `day_index`
- `time_index`
- `event_index`
- `layer_id`

The GPT/runtime MUST NOT depend on:

- nullable projection ordering metadata
- inferred chronology reconstruction
- projection_sequence ordering
- render_label ordering
- optional materialization joins

---

### Canonical Event Resolution Query

```sql
SELECT
    id,
    entity_id,
    layer_id,
    chronology_address,
    summary
FROM calendar_events
WHERE layer_id = 'calendar_layer_event'
  AND week_index = <week_index>
  AND day_index = <day_index>
  AND time_index = <time_index>
ORDER BY chronology_address ASC, id ASC;
```

Example:

```sql
SELECT
    id,
    entity_id,
    layer_id,
    chronology_address,
    summary
FROM calendar_events
WHERE layer_id = 'calendar_layer_event'
  AND week_index = 3
  AND day_index = 1
  AND time_index = 1
ORDER BY chronology_address ASC, id ASC;
```

---

### Canonical Time Resolution Query

```sql
SELECT
    ce.id,
    ce.entity_id,
    ce.chronology_address,
    ce.time_index,
    tl.label AS time_label,
    tl.code AS time_label_code,
    tl.sort_order AS time_sort_order
FROM calendar_events ce
LEFT JOIN calendar_time_label_classvals tl
    ON tl.id = ce.time_label_id
WHERE ce.layer_id = 'calendar_layer_time'
  AND ce.week_index = <week_index>
  AND ce.day_index = <day_index>
ORDER BY ce.time_index ASC, ce.id ASC;
```

---

## Export Resolution Doctrine

Do not treat Book 1 prose as a single canonical prose row.

Prose drafts are revisions. A projection selects which draft is published for a specific projection context.

Exports resolve by explicit export topology:

```json
{
  "export_target_key": "book1|calendar_event:326"
}
```

Exports do not resolve by:
```json
{
"projection_type_id": "book1"
}
```
### Required model

Export resolution is projection-contextual.

A projection selects a published prose draft locally. Exports resolve through explicit export topology, not through projection type, newest draft, or chronology.

#### Forbidden assumptions

Never assume:

* latest draft wins
* newest prose is canonical
* projection_type_id defines export identity
* Book 1 prose has one global canonical draft
* draft chronology determines publication state

### Runtime Query Safety Rules

Traversal queries MUST:

- constrain by explicit hierarchy indices
- constrain by explicit layer_id
- order deterministically
- resolve concrete runtime event instances

Traversal queries MUST NOT:

- attach prose using layer categories
- query projection tables for executable traversal ordering
- sort using nullable projection metadata
- infer missing hierarchy structure


# Runtime Traversal Contract

Book 1 prose is NOT free text.

Book 1 prose is executable projection-backed calendar state.

Because prose executes within the calendar runtime hierarchy, prose placement MUST follow deterministic hierarchy traversal before prose attachment is allowed.

Required traversal order:

```text
Projection
→ Week
→ Day
→ Time
→ Event
→ Subevent prose
```

Important:

The resolved `target_event_entity_id` MUST reference a runtime row whose:

```text
layer_id = calendar_layer_event
```

The GPT MUST NOT assume that:

```text
calendar_event:<id>
```

automatically implies an event-layer node.

Week/day/time nodes also use `calendar_event:<id>` entity identity format.

The GPT/runtime MUST resolve each hierarchy layer sequentially.

The GPT/runtime MUST NOT:

- jump directly from prose intent to event targeting
- infer an event before week resolution
- infer an event before day resolution
- infer an event before time resolution
- request event selection prematurely
- create prose before concrete event resolution
- attach prose directly to runtime layer classes

The GPT MUST NOT ask for or infer an event until week/day/time resolution completes.

Traversal failure at any hierarchy layer halts prose materialization.

Required resolved runtime state before prose creation:

```json
{
  "projection_id": "...",
  "week_entity_id": "...",
  "day_entity_id": "...",
  "time_entity_id": "...",
  "target_event_entity_id": "calendar_event:..."
}
```

Only after `target_event_entity_id` resolves may prose materialize beneath:

```text
calendar_event:<id>
    → calendar_layer_subevent
```

---

# Why `calendar_layer_event` Is Not A Valid Target

A critical runtime distinction exists between:

- layer class
- runtime instance
- attachment identity

These concepts MUST NOT be collapsed into one identifier.

The following payload is INVALID:

```json
{
  "layer_id": "calendar_layer_event"
}
```

Reason:

`calendar_layer_event` identifies a materialization layer category, NOT a concrete executable calendar event instance.

This value describes runtime structure only.

It does NOT identify:

- which projection
- which week
- which day
- which time
- which event

Therefore prose cannot attach to it.

Correct prose attachment requires a resolved concrete runtime event:

```json
{
  "projection_id": "...",
  "week_entity_id": "...",
  "day_entity_id": "...",
  "time_entity_id": "...",
  "target_event_entity_id": "calendar_event:123"
}
```

Valid runtime attachment:

```text
calendar_event:123
```

Invalid runtime attachment:

```text
calendar_layer_event
```

Canonical rule:

Attach prose beneath a concrete `calendar_layer_event` instance, never to the `calendar_layer_event` layer itself.

The prose runtime MUST reject any payload that attempts to use:

- `layer_id`
- `target_layer`
- `calendar_layer`
- `parent_layer_id`

as prose attachment identity.

Layer identifiers describe runtime categories.

Executable prose requires resolved runtime instances.

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

## Subevent Beat Line vs Prose Body Contract

Book 1 prose execution MUST distinguish between:

```text
subevent beat line
```

and:

```text
subevent prose body
```

These are related, but they are not the same textual surface.

---

### Subevent Beat Line

A subevent beat line is:

- concise
- deterministic
- execution-oriented
- summary-like

It describes what the subevent does in the runtime.

Example:

```text
Shay decides to return to the Gilded Lily
```

---

### Subevent Prose Body

A subevent prose body is the rendered narrative prose associated with that subevent.

It may contain:

- multiple sentences
- dialogue
- interiority
- sensory detail
- action
- descriptive narration
- transition material

Example:

```text
Shay paces the apartment while the dream continues to vibrate through her nervous system. By the time the morning light begins to thin the room, she has already decided she needs to return to the Gilded Lily before the feeling disappears.
```

---

## Prose Draft Mapping Rule

A prose draft may materialize into:

```text
multiple ordered subevents
```

Each subevent may contain:

```text
- one beat line
- one prose body
- one order position
```

The GPT/runtime MUST NOT assume:

```text
one sentence = one subevent
```

The GPT/runtime MUST NOT assume:

```text
one prose block = one subevent
```

The GPT/runtime MUST NOT collapse:

```text
beat line
```

into:

```text
prose body
```

The beat line is for execution structure.

The prose body is for rendered narrative content.



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
