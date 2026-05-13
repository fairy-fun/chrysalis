# Latest Event Prose Resolution Contract

`private/docs/prose/latest_event_prose_resolution_contract.md`

---

## Purpose

This document governs canonical runtime resolution for narrative-facing prose requests such as:

```text
"What’s the prose for the latest event?"
```

This is a machine-facing orchestration contract.

It defines:

* canonical latest-event resolution
* canonical prose publication resolution
* runtime traversal order
* machine response requirements
* missing-state handling
* projection-scoped chronology lookup
* publication-authoritative prose retrieval

This document does NOT govern:

* prose segmentation
* prose drafting semantics
* calendar hierarchy ontology
* prose export assembly
* deterministic subevent generation

Those are governed separately by:

* a_calendar_execution_contract.md
* create_prose_draft_json_contract.md
* prose_subevent_segmentation_doctrine.md

### Runtime Doctrine

Book 1 prose interaction is:

state-resolution-driven

NOT:

hierarchy-interview-driven

The runtime MUST resolve canonical projection, chronology, event, and publication state before requesting additional user input.

The user is NOT responsible for manually discovering:

* projection state
* week/day/time traversal state
* latest executable event
* prose publication state
* missing runtime hierarchy nodes

The machine performs runtime traversal.

The user asks narrative questions.

### Canonical Runtime Resolution Chain

The canonical runtime truth surface for latest-event prose resolution is:
```text
calendar_projections
→
calendar_events(sequence_index)
→
prose_projections(target_entity_id)
→
prose_drafts(published_prose_draft_id)
```
This is the authoritative runtime resolution path.

The runtime MUST NOT create parallel prose-state abstractions.

### Canonical Projection Resolution

The active Book 1 projection resolves from:

`calendar_projections`

Canonical example:
```sql
SELECT
id,
entity_id,
projection_code,
projection_title
FROM calendar_projections
WHERE projection_code = 'book_projection_BOOK-001'
LIMIT 1;
```
The canonical runtime identity is:

`projection_id`

### Canonical Latest Event Resolution

The latest executable event is:

the highest `sequence_index`

within:
```text
projection_id
AND layer_id = 'calendar_layer_event'
```
Canonical query:
```sql
SELECT
ce.id,
ce.entity_id,
ce.summary,
ce.sequence_index,
ce.event_index,
ce.chronology_address,
ce.parent_event_id,
ce.projection_id
FROM calendar_events ce
WHERE ce.projection_id = :projection_id
AND ce.layer_id = 'calendar_layer_event'
ORDER BY ce.sequence_index DESC
LIMIT 1;
```

### Canonical Latest Ordering Semantics

The runtime MUST define:

latest

using:

`calendar_events.sequence_index
`

The runtime MUST NOT define latest ordering using:

* chronology_address
* id
* event_index
* projection_sequence
* insertion order
* inferred chronology reconstruction

#### Reason:

`sequence_index` is the current executable chronology-authoritative runtime ordering field.

### Canonical Prose Publication Resolution
```text
The canonical prose publication relationship is:

calendar_events.entity_id
→
prose_projections.target_entity_id
→
prose_projections.published_prose_draft_id
→
prose_drafts.id
```

Canonical publication query:
```sql
SELECT
pd.id AS prose_draft_id,
pd.entity_id AS prose_entity_id,
pd.title,
pd.summary,
pd.prose_body
FROM prose_projections pp
JOIN prose_drafts pd
ON pd.id = pp.published_prose_draft_id
WHERE pp.target_entity_id = :calendar_event_entity_id
ORDER BY pp.projection_order ASC
LIMIT 1;
```

### Canonical Meaning of "Latest Event Prose"

The runtime definition of:

"What’s the prose for the latest event?"

is:
```text
Resolve the latest executable calendar_layer_event
by sequence_index within the active projection,
then resolve the published prose_draft attached
through prose_projections.
```

The runtime MUST return:

published prose draft prose_body

NOT:

* inline calendar_events.prose_body
* newest draft by chronology
* unpublished drafts
* generated subevent prose bodies
* export-assembled prose
* inferred prose surfaces

### Runtime Response States

The resolver MUST support the following canonical states.

#### prose_found

The latest executable event exists and has published prose attached.

##### Required response:

* latest event identity
* latest event summary
* published prose draft identity
* prose_body

#### latest_event_exists_no_prose

The latest executable event exists but has no published prose attachment.

##### Required response:

* latest event identity
* latest event summary
* publication missing status
* next valid prose action

#### latest_event_slot_inferable

The runtime hierarchy implies a valid next executable event slot,
but no concrete event node exists yet.

##### Required response:

* resolved hierarchy state
* inferred next slot
* optional inferred event concept
* event creation requirement

#### projection_missing

The requested projection context cannot be resolved.

Traversal halts.

The runtime MUST NOT infer chronology without projection resolution.

#### publication_missing

A prose publication relationship is missing or invalid.

Examples:
```text
prose_projection row missing
published_prose_draft_id NULL
prose draft missing
```
The runtime MUST report publication failure explicitly.

### Forbidden Runtime Behavior

The runtime MUST NOT begin prose interaction by asking the user:

what week
what day
what time
what event

unless canonical runtime resolution fails.

The runtime MUST attempt canonical state resolution first.

The runtime MUST NOT force the user to manually reconstruct executable hierarchy state already available in the database.

### Runtime Resolver Surface

Canonical resolver target:
```sql
    resolve_latest_event_prose(
    PDO $pdo,
    int $projectionId
    ): array
```
Canonical resolver responsibilities:

* resolve projection
* resolve latest executable event
* resolve publication state
* resolve published prose
* diagnose missing runtime state
* produce deterministic machine-readable status output

The resolver MUST remain read-only.

It MUST NOT:

* create events
* create prose drafts
* create projections
* create subevents
* mutate chronology
* infer persistence

### Architectural Doctrine

The prose runtime has entered:

runtime mediation phase

not:

manual hierarchy traversal phase

Hierarchy traversal is now an internal machine responsibility.

Narrative questioning is the user-facing interface.