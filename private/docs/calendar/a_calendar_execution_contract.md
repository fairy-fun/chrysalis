# Calendar Execution Contract (Week → Subevent)

## Hierarchy (fixed)

Week (calendar_layer_week)
→ Day (calendar_layer_day)
→ Time (calendar_layer_time)
→ Event (calendar_layer_event)
→ Subevent (calendar_layer_subevent)

---

## Attachment Rule (critical)

* All batch-created subevents MUST use a concrete runtime row whose:

```text
layer_id = calendar_layer_event
```

* The following are INVALID parent targets:

```text
calendar_layer_week
calendar_layer_day
calendar_layer_time
```

even if their entity_id format is:

```text
calendar_event:<id>
```
* Never attach subevents directly to week, day, or time layers

---

## Parent Selection Procedure

* Locate target Week
* Select intended Day
* Select intended Time block
* Select intended Event
* Use that **event-layer entity_id** as the batch parent

## Canonical Runtime Event Resolution Query

Runtime hierarchy traversal MUST resolve executable event instances directly from:

```text
calendar_events
```

using deterministic hierarchy predicates.

The canonical executable hierarchy fields are:

- `week_index`
- `day_index`
- `time_index`
- `event_index`
- `layer_id`

The authoritative executable runtime table is:

```text
calendar_events
```

Runtime traversal MUST NOT depend on:

- `calendar_event_projections.projection_sequence`
- `calendar_event_projections.render_label`
- nullable projection ordering metadata
- inferred chronology reconstruction
- optional joins for execution ordering

Projection tables are projection/materialization surfaces, NOT the authoritative traversal root for executable event lookup.

---

## Canonical Event Lookup Query

Executable event resolution MUST use:

```sql
SELECT
    id,
    entity_id,
    layer_id,
    week_index,
    day_index,
    time_index,
    event_index,
    chronology_address,
    summary
FROM calendar_events
WHERE layer_id = 'calendar_layer_event'
  AND week_index = <week_index>
  AND day_index = <day_index>
  AND time_index = <time_index>
ORDER BY chronology_address ASC, id ASC;
```

Canonical example:

```sql
SELECT
    id,
    entity_id,
    layer_id,
    week_index,
    day_index,
    time_index,
    event_index,
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

## Canonical Time Layer Lookup Query

Time traversal MUST resolve from:

```text
calendar_events
```

with canonical label resolution through:

```text
calendar_time_label_classvals
```

Canonical query:

```sql
SELECT
    ce.id,
    ce.entity_id,
    ce.layer_id,
    ce.week_index,
    ce.day_index,
    ce.time_index,
    ce.chronology_address,
    ce.time_label_id,
    tl.code AS time_label_code,
    tl.label AS time_label,
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

## Runtime Ordering Contract

Executable traversal ordering MUST prefer:

```sql
ORDER BY chronology_address ASC, id ASC
```

If chronology ordering is unavailable, fallback ordering MUST be:

```sql
ORDER BY id ASC
```

The runtime MUST NOT depend on:

- nullable projection ordering fields
- projection_sequence
- render_label
- inferred chronology reconstruction
- implicit insertion order

---

## Runtime Query Safety Rules

Traversal queries MUST:

- query `calendar_events` directly
- constrain by concrete hierarchy indices
- constrain by explicit `layer_id`
- use deterministic ordering

Traversal queries MUST NOT:

- dynamically infer hierarchy depth
- join unnecessary projection metadata
- sort by nullable projection fields
- assume projection completeness for runtime lookup
- reconstruct chronology outside canonical fields

The executable runtime hierarchy is authoritative in:

```text
calendar_events
```
---

## Batch Invariants

* One non-empty prose segment → exactly one subevent

## Subevent Text Structure Contract

A `calendar_layer_subevent` contains distinct textual surfaces that MUST NOT be collapsed into a single field.

Each subevent execution unit may contain:

```text
- beat line
- prose body
```

These are different concepts.

---

### Beat Line

The beat line is:

- concise
- deterministic
- execution-oriented
- summary-like

The beat line exists to support:

- traversal
- ordering
- execution visibility
- runtime identification
- planning coherence

A beat line is NOT the full prose payload.

Example beat line:

```text
Shay decides to return to the Gilded Lily
```

---

### Prose Body

The prose body is the rendered narrative content associated with the subevent.

A prose body may contain:

- multiple sentences
- dialogue
- interiority
- sensory detail
- action
- transitions
- descriptive narration

Example prose body:

```text
Shay paces the apartment while the remnants of the dream continue to vibrate through her nervous system. 
By the time the sun starts to rise she has already decided she needs to get back to the Gilded Lily before the feeling disappears.
```

This entire prose body still belongs to:

```text
one calendar_layer_subevent
```

---

## Runtime Mapping Rules

The runtime MUST distinguish between:

```text
subevent summary surface
```

and:

```text
subevent prose surface
```

The runtime MUST NOT assume:

```text
one sentence = one subevent
```

The runtime MUST NOT assume:

```text
one prose block = one subevent
```

A prose draft may materialize into:

```text
multiple ordered subevents
```

Each subevent may contain:

```text
- one beat line
- one prose body
```

---

## Forbidden Semantic Collapses

The runtime/GPT MUST NOT collapse:

```text
beat line
```

into:

```text
prose body
```

The runtime/GPT MUST NOT collapse:

```text
summary semantics
```

into:

```text
narrative prose semantics
```

Execution structure and rendered prose are related but distinct runtime surfaces.

* Order is preserved via `order_index`
* Idempotency enforced via **stable client_id (slot-based identity)**
* Replays must not create duplicates

---

## Idempotency Model (v2)

* `client_id` MUST be stable across runs
* `client_id` MUST NOT be derived from plan_id or content
* Recommended format:

```
calendar_event:{parent_event_entity_id}:slot:{index}
```

* `beat_hash` is diagnostic only (not identity)

---

## Coexistence Rule

* Pre-existing events (no client_id) are allowed
* Do not delete or “clean” the parent before batching

---

## Anti-Patterns (forbidden)

* Attaching subevents to week/day/time layers
* Deriving identity from plan_id or prose content
* Treating batch execution as a destructive sync
* Writing directly to DB

### Projection Completeness Requirement

All execution paths that operate on calendar data (including but not limited to week resolution, beat classification, and planner traversal) MUST operate on projection-scoped datasets.

Accordingly, the following invariant is REQUIRED:

> For any event eligible for a projection, a corresponding row MUST exist in `calendar_event_projections`.

Failure to satisfy this invariant results in:

* silent exclusion from execution queries
* incorrect temporal resolution (e.g. latest week misidentification)
* incomplete rule application and classification

### Chronology Consistency Rule

`calendar_event_projections.chronology_address` MUST be a direct copy of the source event’s `calendar_events.chronology_address`, unless an explicit projection-level transformation is defined.

No execution logic may assume:

* implicit ordering
* reconstruction of chronology
* or fallback to event-level chronology

Projection rows are the authoritative execution surface.

### Ingestion Contract

Any process that inserts into `calendar_events` MUST also ensure:

1. Projection membership is created
2. Chronology is propagated to `calendar_event_projections`
3. The insertion is atomic or recoverable (no orphaned events)

Partial writes (events without projection rows) are considered contract violations.

### Projection Derivation Rule

Projection membership MUST be derived from `calendar_event_books`.

For any `(calendar_event_id, book_id)` pair:

* a corresponding row MUST exist in `calendar_event_projections`
* mapped via `calendar_projections.book_id`

Direct insertion into `calendar_event_projections` is not authoritative and MUST NOT be relied upon.

### Dependency Chain

Execution correctness depends on the following invariant:

calendar_events
→ calendar_event_books (required)
→ calendar_event_projections (derived)

If `calendar_event_books` is incomplete:

* projections will be incomplete
* execution queries will silently exclude events

### Ingestion Requirement

Any ingestion process MUST:

1. Assign event → book membership (`calendar_event_books`)
2. Ensure projection rows are created (via trigger or service)
3. Maintain consistent `chronology_address` across both tables

Partial ingestion (events without book assignment) is a contract violation.

### Projection Structural Integrity

For any row in `calendar_event_projections`:

* If `calendar_events.parent_event_id` is NOT NULL
  → the parent event **MUST** also exist in `calendar_event_projections`
  for the same `calendar_projection_id`.

Formally:

For all `ep`:

```
ep.calendar_event_id = e.id
AND e.parent_event_id = p.id
```

Then there MUST exist:

```
parent_ep.calendar_event_id = p.id
AND parent_ep.calendar_projection_id = ep.calendar_projection_id
```

Violations of this rule are considered **invalid projection state** and must be corrected at materialization time.

Execution engines MAY surface orphaned nodes at root level
but MUST NOT synthesize missing parents from calendar_events.


## Projection-Contextual Prose Resolution

Calendar projections define runtime topology only.

Canonical prose publication is projection-contextual.

A projection may locally select a published prose draft through:

```text
prose_projections.published_prose_draft_id
```

Export resolution MUST occur through explicit export topology:

`export_target_key`

and MUST NOT infer canonical prose through:

* chronology
* newest draft
* projection_type_id alone

# Runtime Attachment Identity Invariants

## Layer Type vs Runtime Instance

The calendar runtime distinguishes between:

- materialization layer classes
- concrete runtime instances
- executable attachment identities

These concepts MUST NOT be treated as interchangeable.

Example:

```text
calendar_layer_event
```

identifies a materialization layer category only.

It does NOT identify:

- a specific projection
- a specific week
- a specific day
- a specific time
- a specific executable calendar event

Therefore:

```text
calendar_layer_event
```

is NOT a valid runtime attachment target.

Correct executable attachment requires a resolved concrete runtime event instance:

```text
calendar_layer_event:<event_id>
```

---

## Canonical Runtime Traversal

Executable calendar prose MUST resolve hierarchy deterministically.

Required runtime traversal order:

```text
Projection
→ Week
→ Day
→ Time
→ Event
→ Subevent prose
```

The runtime MUST NOT:

- skip hierarchy layers
- infer events before week/day/time resolution
- attach prose directly to runtime layer categories
- use layer identifiers as executable parent identity

Prose attachment requires a resolved:

```text
event_id
```

before prose materialization may occur.

---

## Runtime Attachment Requirements

Valid prose attachment requires resolved runtime hierarchy state:

```json
{
  "projection_id": "...",
  "week_id": "...",
  "day_id": "...",
  "time_id": "...",
  "event_id": "..."
}
```

Only after `event_id` resolves may prose materialize beneath:

```text
calendar_layer_event:<event_id>
    → calendar_layer_subevent
```

---

## Forbidden Attachment Identity Forms

The runtime MUST reject any payload attempting to use layer categories as executable parent identities.

Invalid examples:

```json
{
  "layer_id": "calendar_layer_event"
}
```

```json
{
  "target_layer": "calendar_layer_event"
}
```

```json
{
  "calendar_layer": "calendar_layer_event"
}
```

Reason:

Layer identifiers describe runtime structure categories only.

They do NOT identify executable runtime parent instances.

Layer identifiers MUST NEVER be accepted as executable parent identities.

