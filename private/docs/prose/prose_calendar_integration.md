# Prose ↔ Calendar Integration Contract (Phase 1.5)

> Note: Calendar integration is one projection domain. Validation rules are defined centrally in the projection writer.

## Purpose

Define the **correct orchestration boundary** between:

- Calendar system (Week → Day → Time → Event)
- Prose system (Draft → Projection → Annotation)

This document ensures:

- prose endpoints remain clean and focused
- calendar hierarchy remains authoritative
- ingestion workflows are deterministic and idempotent
- no schema responsibilities bleed across layers

---

## ⚠️ Projection Target Validation Boundary

Calendar-backed prose projections use `target_entity_id = calendar_event:*`.

However, **calendar validation is NOT applied globally to all prose projections**.

All projection writes MUST go through:

`private/framework/prose/prose_projection_writer.php`

Which enforces:

- Calendar validation ONLY for `calendar_event:*`
- No validation for non-calendar domains (e.g. `dream_journal:*`)
- Rejection of unsupported domains

👉 See:
`prose_annotation_and_projection_contract_phase1.md` → "Target Entity Domain Rules"

### Critical Rule

Do NOT:

- Apply calendar validation outside the shared writer
- Assume all projections are calendar-backed
- Introduce guards in draft creators or domain-specific writers

Validation is **prefix-scoped and centralized**.

Violating this will break non-calendar projection types (e.g. dream journal).


## Core Principle

```text
Calendar owns structure.
Prose owns meaning.
```
### System Responsibilities
#### Calendar Layer

Owns:

* entity creation (calendar_event:*)
* hierarchy:
* Week
* Day
* Time
* Event
* indexing:
* week_index
* day_index
* time_index
* event_index
* chronology_address
* time labels

##### Calendar guarantees:

A valid, addressable temporal container exists.
#### Prose Layer

Owns:

* prose_drafts
* prose_projections
* prose_annotation_spans

##### Prose guarantees:

Narrative content + structured annotation attached to an existing entity.
## Critical Boundary Rule
Prose MUST NOT create or manage calendar hierarchy.
Prose MUST attach only to an existing calendar_event entity.



3. Add this under “Critical Boundary Rule”:


### Runtime Identity Rule

`projection_id` is the canonical runtime identity.

`projection_entity_id` and `calendar_event:<id>` remain compatibility ingress identities only.

Runtime orchestration must:

- resolve projection identity immediately
- propagate projection_id internally
- avoid runtime joins keyed by projection_entity_id


### Problem Statement

The current workflow risks:

requiring calendar fields (day_index, etc.) in prose payloads ❌
attaching prose to non-existent calendar targets ❌
duplicating calendar creation logic ❌
Solution: Orchestration Layer

Introduce a pre-prose orchestration step:

ensureCalendarEventForProse

This step ensures:

Week exists
→ Day exists
→ Time node exists
→ Event exists
→ entity_id resolved
Recommended Flow
[Client / Postman]

→ (1) ensureCalendarEventForProse
      OR manual calendar creation

→ (2) receive:
      target_entity_id = calendar_event:<id>

→ (3) createProseDraft
      attach to target_entity_id
Optional Wrapper Endpoint

To simplify usage, introduce:

createProjectedProseDraft
Responsibilities
Accept:
{
  "calendar_context": {
    "week_index": 3,
    "day_index": 1,
    "time_label": "morning",
    "event_index": 1
  },
  "prose": { ... }
}
Internally:
ensure week
ensure day
ensure time
ensure event
resolve entity_id
Call:
createProseDraft(target_entity_id)
createProseDraft Contract (LOCKED)
Input MUST include:
{
  "entity_id": "...",
  "title": "...",
  "prose_body": "...",
  "draft_status_id": "...",
  "projection": {
    "projection_type_id": "...",
    "target_entity_id": "calendar_event:<id>",
    "role_id": "primary",
    "projection_order": 1,
    "is_export_target": 0
  },
  "annotations": [...]
}
Validation Rules
target_entity_id MUST exist in calendar_events
MUST NOT require:
week_index
day_index
time_index
chronology_address
Annotation Constraints (Current Reality)

From implementation:

annotations REQUIRE:
annotation_type_id
annotation_value_id
source_type_id
spans must be valid
values must exist in classvals / entities
Invariants
1. Entity-first rule
Calendar entity must exist BEFORE prose attaches.
2. No dual responsibility
Calendar creates structure.
Prose attaches meaning.
3. Idempotency

Calendar creation must be:

safe under retries
safe under concurrency
4. No inference persistence
Prose = canonical truth
Annotations = structured signals
Limbic facts = derived later only
Anti-Patterns (Disallowed)

❌ Prose endpoint creating day/time/event rows
❌ Passing calendar hierarchy fields into prose payload
❌ Attaching prose to invalid/non-existent event
❌ Encoding narrative meaning in calendar metadata
❌ Skipping calendar layer and attaching prose to arbitrary entities
```

### Runtime Reality

Current runtime behavior:

```text
createProseDraft
```

already acts as the orchestration boundary for calendar-backed prose.

When a projection target is:
```text
calendar_event:<id>
```

the system automatically:

* validates the projection target
* persists prose
* persists projections
* persists annotations
* generates deterministic prose operations
* materializes calendar subevents
* createProseDraft

#### Phase 2 (Future)

* richer annotation system
* theme + presence
* multi-character POV
* limbic suggestion layer

#### Success Criteria

* prose drafts attach cleanly to valid events
* no calendar data required in prose payload
* no schema violations
* annotation spans valid
* retrieval consistent

## Derived Calendar Structures (Beats)

Calendar beats are derived from `prose_body`.

For calendar-backed prose projections:

```text
projection.target_entity_id = calendar_event:<id>
```

the prose write pipeline automatically performs:
```text
createProseDraft
→ prose persistence
→ projection persistence
→ annotation persistence
→ deterministic prose → calendar execution
```


Generated beats are materialized as:

`calendar_layer_subevent`

nodes beneath the parent:

`calendar_layer_event`

Persistence is handled exclusively by calendar APIs and ensurers.

Inference metadata MUST NOT be stored in prose tables.