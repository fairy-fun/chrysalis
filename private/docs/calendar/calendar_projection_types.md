# Calendar Projection Types

## Overview
Defines the different projection types and their required fields.

---

## 1. Time-Based Projection

Used for:
- global timelines
- infinite past/future projections

### Required fields
- projection_starts_at

### Optional fields
- projection_ends_at

### Not used
- projection_address
- chronology_address

---

## 2. Structure-Based Projection

Used for:
- trees
- hierarchical event groupings

### Required fields
- projection_address

### Optional fields
- parent_projection_row_id

### Not used
- projection_starts_at
- chronology_address

---

## 3. Book 1 Chronology Projection

Used for:
- fixed narrative ordering

### Required fields
- chronology_address

### Optional fields
- projection_address (if dual-structured)

---

## Invariants

- Every row belongs to a build
- Required fields are enforced by the materializer, not the DB