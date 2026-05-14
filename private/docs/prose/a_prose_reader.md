# Projection Prose Reader Contract

## Purpose

This document defines the canonical runtime contract for chronological
projection prose reading.

This is a read-only validation/runtime system.

It exists so an operator or author can walk through projection prose
in canonical chronological order and compare database-attached prose
against externally developed human source documents.

This system is NOT:

- a prose drafting workflow
- an interview workflow
- an orchestration system
- a prose generation system
- a publication workflow
- a beat planning workflow

This document governs only chronological prose reading and validation.


---

# Runtime Doctrine

## Core Principles

### Prose interaction is state-resolution driven

The runtime resolves prose from canonical projection state.

The operator does not manually traverse hierarchy nodes.


### Hierarchy traversal is machine responsibility

The runtime resolves:

projection
→ executable event
→ published prose

without requiring the operator to manually select intermediate hierarchy nodes.


### Chronological authority derives from executable event ordering

Canonical chronology authority is:

```text
calendar_events.sequence_index
```

The runtime MUST NOT derive chronological order from:

chronology_address lexical sorting
projection_order
event_index
database insertion order
prose projection ordering
prose draft ordering
Publication authority derives from published prose bindings

Canonical prose publication authority derives from:

prose_projections.published_prose_draft_id

The runtime MUST NOT infer publication state from:

newest prose draft
highest prose draft id
projection_order
prose draft timestamps
Ambiguity must never be silently resolved

If multiple published prose bindings exist for the same executable event,
the runtime MUST surface ambiguity explicitly.

The runtime MUST NOT:

choose the newest draft
choose the oldest draft
choose by projection_order
choose arbitrarily
Canonical Runtime Resolution Path

The canonical runtime resolution path is:

calendar_projections
→ calendar_events
→ prose_projections
→ prose_drafts

More specifically:

calendar_events.projection_id
→ calendar_projections.id

calendar_events.entity_id
→ prose_projections.target_entity_id

prose_projections.published_prose_draft_id
→ prose_drafts.id
Projection Reader Purpose

The projection prose reader exists to support:

chronological prose validation
projection continuity auditing
human comparison against source manuscripts
prose attachment verification
publication verification
ambiguity detection
segmentation validation
Reader Traversal Model
Reader Loop

The intended reader loop is:

resolve projection
→ fetch first executable event
→ resolve canonical prose
→ display prose
→ await operator continuation
→ fetch next executable event
→ repeat

Chronological advancement derives exclusively from:

calendar_events.sequence_index
First Event Resolution

The first executable event is resolved using:

ORDER BY sequence_index ASC
LIMIT 1

restricted to:

calendar_layer_event
Next Event Resolution

The next executable event is resolved using:

WHERE sequence_index > :current_sequence_index
ORDER BY sequence_index ASC
LIMIT 1
Reader Runtime States

The runtime may return the following states.

projection_missing

No canonical projection exists.

no_executable_events

Projection exists but contains no executable event-layer nodes.

latest_event_exists_no_prose

Executable event exists but no prose projection is attached.

publication_missing

A prose projection exists but no canonical published prose draft resolves.

publication_ambiguous

Multiple published prose bindings exist for the same executable event.

prose_found

Exactly one canonical published prose draft resolves successfully.

projection_complete

No further executable events remain after the current event.

Subevent Prose

Subevent prose is generated through deterministic prose segmentation.

Current segmentation doctrine:

prose
→ deterministic segmentation
→ canonical slot assignment
→ replay-safe client identity
→ canonical persistence

Subevent persistence identity derives from:

build_subevent_client_id()

using:

sprintf(
'%s:slot:%d',
$parentEventEntityId,
$slot
)

The runtime MUST NOT generate duplicate namespace prefixes such as:

calendar_event:calendar_event:...
Parent Event vs Subevent Prose

Parent event prose and segmented subevent prose are distinct runtime layers.

The runtime MUST NOT silently merge them.

The runtime MUST explicitly distinguish:

parent executable event prose
generated subevent prose
Ambiguity Handling

Ambiguity is a valid runtime state.

The runtime MUST expose ambiguity explicitly.

The runtime MUST NOT:

silently reconcile
auto-select
auto-promote
auto-supersede
infer canonical prose from timestamps
Read-Only Doctrine

Projection prose reading is strictly read-only.

The runtime MUST NOT:

mutate prose
create drafts
publish drafts
create events
create subevents
rewrite projections
modify segmentation state
Intended API Surface

Potential API operations may include:

getProjectionProseReaderStep
getProjectionProseWalk
getProjectionFirstEvent
getProjectionNextEvent

Final naming remains unresolved.

Current Scope Boundary

This document governs ONLY:

projection prose reading
chronological traversal
publication resolution
ambiguity handling
segmentation visibility
prose validation

This document does NOT yet govern:

operator orchestration
interview routing
drafting UX
multi-phase author workflows
prose generation choreography
publication tooling
human approval systems