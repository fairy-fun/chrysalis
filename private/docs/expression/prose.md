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
