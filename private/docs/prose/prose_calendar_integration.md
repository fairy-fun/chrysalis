# Prose ↔ Calendar Integration Contract (Phase 1.5)

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

## Core Principle

```text
Calendar owns structure.
Prose owns meaning.

System Responsibilities
Calendar Layer

Owns:

entity creation (calendar_event:*)
hierarchy:
Week
Day
Time
Event
indexing:
week_index
day_index
time_index
event_index
chronology_address
time labels

Calendar guarantees:

A valid, addressable temporal container exists.
Prose Layer

Owns:

prose_drafts
prose_projections
prose_annotation_spans

Prose guarantees:

Narrative content + structured annotation attached to an existing entity.
Critical Boundary Rule
Prose MUST NOT create or manage calendar hierarchy.
Prose MUST attach only to an existing calendar_event entity.
Problem Statement

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

Implementation Plan
Phase 1 (Now)
Use existing createProseDraft
Ensure valid calendar_event manually or via DB queries
Validate storage + retrieval
Phase 1.5 (Immediate Upgrade)
Add ensureCalendarEventForProse helper
Or build wrapper endpoint
Phase 2 (Future)
richer annotation system
theme + presence
multi-character POV
limbic suggestion layer
Success Criteria
prose drafts attach cleanly to valid events
no calendar data required in prose payload
no schema violations
annotation spans valid
retrieval consistent