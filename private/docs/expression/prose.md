# Prose Projections

## Overview

`prose_projections` defines how reusable prose drafts are attached to other entities in the system (e.g. calendar events, future book chapters, character arcs).

This table represents a **projection layer**, not authored content.

- `prose_drafts` → source material
- `prose_projections` → placement / usage
- downstream systems → interpretation (themes, dreams, etc.)

---

## Table: `prose_projections`

### Purpose

To map a `prose_draft` onto a target entity in a structured, reusable, and extensible way.

---

## Key Fields

### `prose_draft_id`
Foreign key to `prose_drafts.id`.

Represents the **source narrative content**.

---

### `projection_type_id`
Defines the type of projection.

Must align with `projection_type_classvals`.

Examples:
- `calendar_event`
- `book_chapter` *(future)*
- `character_arc` *(future)*

---

### `target_entity_id`

Canonical reference to the target entity.

**Required format:**
```text
<entity_type_code>:<id>
```


Examples:
- `calendar_event:5001`
- `character:42`

This ensures:
- global consistency
- compatibility with `entity_type_classvals`
- cross-system portability

---

### `role_id`

Describes how the prose is used in this projection.

This is intentionally flexible.

Examples:
- `primary`
- `subevent`
- `dream_source`
- `music_source`
- `voice_training_source`

Avoid hardcoding meaning into schema—this field enables pipeline behavior.

---

### `projection_order`

Optional ordering of prose within the target context.

Used when:
- multiple prose drafts attach to the same target
- narrative sequencing matters

---

### `is_export_target`

Boolean flag indicating the **active/selected prose** for a given projection.

Only one row per `(projection_type_id, target_entity_id)` should have this set to `1`.

Enforced via a generated column + unique index (no triggers).

---

## Constraints & Rules

### Entity ID Format

All `target_entity_id` values must follow:
```text
<entity_type_code>:<id>
```


This is not optional. Do not store raw numeric IDs.

---

### Projection Type Integrity

`projection_type_id` should come from:
```text
projection_type_classvals
```


Do not introduce ad hoc strings at runtime.

---

### Export Target Uniqueness

At most one row per projection target may have:
```text
is_export_target = 1
```


Enforced at the database level.

---

## Design Principles

### 1. Prose is reusable

A single `prose_draft` may be projected into:
- multiple calendar events
- future book structures
- multiple narrative contexts

---

### 2. Projections do not own content

This table does **not** store:
- prose text
- derived meaning (themes, dreams, tone)

It only defines relationships.

---

### 3. Interpretation is downstream

Systems such as:
- `narrative_theme_observations`
- `dream_theme_instances`

should treat:
```text
prose_draft → source
projection → context
```

---

### 4. No schema coupling to calendar

Although calendar events are a primary use case, this table must remain:

- calendar-agnostic
- future-proof for non-temporal narratives

---

## Example

### Prose Draft
```text
prose_draft:101
"She dreams of drowning in light..."
```


### Projection

| field                | value                  |
|---------------------|------------------------|
| prose_draft_id      | 101                    |
| projection_type_id  | calendar_event         |
| target_entity_id    | calendar_event:5001    |
| role_id             | primary                |
| is_export_target    | 1                      |

---

## Pipeline Flow
```text
prose_drafts
↓
prose_projections
↓
calendar_events (or other targets)
↓
theme / dream / tone extraction
↓
observation tables
```


---

## Anti-Patterns (Avoid)

- Storing numeric IDs in `target_entity_id`
- Embedding theme/dream flags in this table
- Using projections to mutate prose content
- Creating separate tables for subevents

---

## Summary

`prose_projections` is the **bridge between narrative content and system structure**.

It enables:
- reuse
- multi-context storytelling
- clean separation of concerns
- future expansion beyond calendar-based narratives