# Projection Types (Framework-Level)

## Overview


### Projection layer
Projection = publication topology placement

vs

### Export layer
Export context = curated assembly of projections

This document defines the core projection types supported by the system, independent of domain (calendar, character, narrative, etc).

A projection is a **derived, build-scoped representation** of source entities.

All projections must conform to one of the structural models below.

---

## 1. Time-Based Projection

Represents entities ordered along a temporal axis.

### Characteristics

* Ordered by real or logical time
* Supports unbounded past and future
* Does not require structural positioning

### Required fields

* `projection_starts_at`

### Optional fields

* `projection_ends_at`

### Not used

* `projection_address`

---

## 2. Structure-Based Projection

Represents entities organized into a hierarchy or ordered structure.

### Characteristics

* Explicit positional addressing
* May represent trees, sequences, or nested groups
* Order is independent of time

### Required fields

* `projection_address`

### Optional fields

* `parent_projection_row_id`
* `projection_sequence`

### Not used

* `projection_starts_at`
* `projection_ends_at`

---

## 3. Hybrid Projection

Combines temporal and structural positioning.

### Characteristics

* Has both ordering in time and structural placement
* Used when hierarchy and chronology both matter

### Required fields

* `projection_starts_at`
* `projection_address`

### Optional fields

* `projection_ends_at`
* `parent_projection_row_id`

---

## 4. Specialized Projection

Domain-specific extensions of the above types.

Examples:

* narrative chronologies
* ranked lists
* weighted graphs

These may introduce additional fields (e.g. `chronology_address`) but must still conform to one of:

* time-based
* structure-based
* hybrid

---

## Core Invariants

* Every projection row belongs to a **build**
* Projections are **fully derived**
* Projection tables are **not written to directly by domain logic**
* Required fields are enforced by the **materializer**, not the database schema

---

## Design Rule

A projection must declare:

* its **type**
* its **required positioning fields**

The materializer is responsible for enforcing correctness for that type.

## Querying Projection Membership

Projection topology is resolved through prose_projections.

Export assembly topology is resolved through:
- prose_export_contexts
- prose_export_context_projections

`projection_type_id` represents the projection topology surface
(e.g. `book1`, `book2`, chronology exports, alternate cuts).

Example: resolve all prose assigned to Book 1.

```sql
SELECT
    pd.entity_id      AS prose_entity_id,
    pd.title,
    pp.projection_type_id,
    pp.target_entity_id,
    pp.projection_order
FROM prose_projections pp
JOIN prose_drafts pd
    ON pd.id = pp.published_prose_draft_id
WHERE pp.projection_type_id = 'book1'
ORDER BY
    pp.target_entity_id,
    pp.projection_order,
    pp.id;
```

Export-only view:

```sql
SELECT
  pec.export_context_key,
  pd.entity_id,
  pd.title,
  pp.target_entity_id,
  pecp.export_order
FROM prose_export_contexts pec
       JOIN prose_export_context_projections pecp
            ON pecp.export_context_id = pec.id
       JOIN prose_projections pp
            ON pp.id = pecp.prose_projection_id
       JOIN prose_drafts pd
            ON pd.id = pp.published_prose_draft_id
WHERE pp.projection_type_id = 'book1'
ORDER BY
  pec.export_context_key,
  pecp.export_order,
  pp.projection_order;
```

### Export prose text

Use `pd.prose_body` when resolving the actual prose content for a projection export surface.

```sql
SELECT
  pec.export_context_key,
  pd.entity_id,
  pd.title,
  pp.target_entity_id,
  pd.prose_body
FROM prose_export_contexts pec
       JOIN prose_export_context_projections pecp
            ON pecp.export_context_id = pec.id
       JOIN prose_projections pp
            ON pp.id = pecp.prose_projection_id
       JOIN prose_drafts pd
            ON pd.id = pp.published_prose_draft_id
WHERE pp.projection_type_id = 'book1'
ORDER BY
  pec.export_context_key,
  pecp.export_order,
  pp.projection_order;
```

Notes:

- membership queries do not require `prose_body`
- export topology queries do not require `prose_body`
- prose rendering/export queries should use `pd.prose_body`

Architecture:

- `projection_classval_id`
  → semantic category

- `projection_type_id`
  → projection topology/query surface
