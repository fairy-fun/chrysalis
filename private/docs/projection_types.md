# Projection Types (Framework-Level)

## Overview

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
