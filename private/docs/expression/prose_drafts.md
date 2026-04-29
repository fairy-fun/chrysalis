# Prose Drafts

## Overview

`prose_drafts` stores reusable narrative content.

It represents the **source layer** of the narrative system.

- `prose_drafts` → authored content
- `prose_projections` → placement into structures
- downstream systems → interpretation (themes, dreams, tone)

This table is intentionally **agnostic of calendar, books, or any projection target**.

---

## Table: `prose_drafts`

### Purpose

To store prose as independent, reusable units that can be:

- attached to multiple contexts
- analyzed by narrative systems
- revised over time

---

## Key Fields

### `id`

Primary key (auto-increment).

---

### `entity_id`

Canonical global identifier.

**Format:**

prose_draft:<id>


Example:

prose_draft:101


Used by:
- theme observations
- projections
- cross-system references

---

### `title`

Optional short label for the prose draft.

Used for:
- UI display
- quick identification

---

### `prose_body`

The full narrative text.

- Stored as `MEDIUMTEXT`
- No derived data should be embedded here

---

### `draft_status_id`

Lifecycle state of the draft.

References:

prose_draft_status_classvals


Typical values:
- `prose_status_draft`
- `prose_status_revised`
- `prose_status_approved`
- `prose_status_superseded`

---

### `author_entity_id`

Optional reference to the author.

**Format:**

<entity_type_code>:<id>


Examples:
- `character:42`
- (future) `user:7`

---

### `created_at`, `updated_at`

Standard timestamps.

---

## Constraints & Rules

### Entity ID Requirement

Every row must have a valid:
prose_draft:<id>

This is typically set **after insert** by the API.

---

### No Embedded Semantics

Do **not** store:

- theme tags
- dream indicators
- music flags
- projection references

These belong to other systems.

---

### Status Integrity

`draft_status_id` must exist in:

prose_draft_status_classvals


---

## Design Principles

### 1. Prose is reusable

A single draft can be:

- attached to multiple calendar events
- reused in different narrative contexts
- included in future book structures

---

### 2. Prose is the source of truth

All interpretation flows from here.

Examples:
- theme extraction
- dream analysis
- tone detection

---

### 3. Prose is not tied to time

Temporal placement belongs to:

prose_projections → calendar_event


Not to this table.

---

### 4. Prose is minimally structured

This table stores:

- text
- identity
- lifecycle

Nothing more.

---

## Example

| field            | value                                  |
|------------------|----------------------------------------|
| id               | 101                                    |
| entity_id        | prose_draft:101                        |
| title            | The Light Dream                        |
| prose_body       | She dreams of drowning in light...     |
| draft_status_id  | prose_status_draft                     |
| author_entity_id | character:42                           |

---

## Lifecycle Flow

create draft
↓
revise draft
↓
approve draft
↓
project into system (via prose_projections)


---

## Integration Points

### With Projections

prose_drafts.id → prose_projections.prose_draft_id


---

### With Theme System
source_type_id = 'prose_draft'
source_entity_id = 'prose_draft:<id>'


Used by:
- `narrative_theme_observations`

---

### With Dream System

Prose may be analyzed and result in:

- `dream_theme_instances`
- `dream_tone_instances`

---

## API Responsibilities

The API should:

- insert prose drafts
- generate and assign `entity_id`
- manage status transitions
- trigger downstream processing

The database should **not**:

- generate entity IDs
- run interpretation logic
- enforce workflow transitions

---

## Anti-Patterns (Avoid)

- Attaching prose directly to calendar tables
- Embedding derived meaning into `prose_body`
- Using this table to store projections
- Treating prose as single-use

---

## Summary

`prose_drafts` is the **foundation of the narrative system**.

It enables:

- reusable storytelling
- clean separation from structure
- consistent downstream analysis
- expansion into non-calendar narratives

