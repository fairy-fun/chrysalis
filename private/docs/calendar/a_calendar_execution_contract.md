# Calendar Execution Contract (Week → Subevent)

## Hierarchy (fixed)

Week (calendar_layer_week)
→ Day (calendar_layer_day)
→ Time (calendar_layer_time)
→ Event (calendar_layer_event)
→ Subevent (calendar_layer_subevent)

---

## Attachment Rule (critical)

* All batch-created subevents MUST use an **event-layer node** as `parent_event_entity_id`
* Never attach subevents directly to week, day, or time layers

---

## Parent Selection Procedure

* Locate target Week
* Select intended Day
* Select intended Time block
* Select intended Event
* Use that **event-layer entity_id** as the batch parent

---

## Batch Invariants

* One non-empty prose segment → exactly one subevent
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

