Calendar Projection Materialization — Contract
Purpose

Defines the invariants required when constructing calendar_event_projections.

This ensures that downstream consumers (e.g. build_calendar_tree()) operate on valid, complete, and deterministic data.

## Core Principle

A projection is a fully self-contained, structurally valid subset of the calendar graph.

It must not rely on missing parents, implicit structure, or runtime correction.

## Required Invariants
### 1. Parent Presence (Strict)

For every projected event:

if event.parent_event_id != null
→ that parent MUST also exist in the same projection
Enforcement Rule
For each row ep:
``` text
let parent_id = e.parent_event_id

    if parent_id IS NOT NULL:
        EXISTS (
            SELECT 1
            FROM calendar_event_projections ep2
            WHERE ep2.calendar_projection_id = ep.calendar_projection_id
              AND ep2.calendar_event_id = parent_id
        )`
``` 
Consequence
* ❌ Missing parent → materialization must fail
* ❌ Do NOT allow partial trees
* ❌ Do NOT rely on runtime orphan handling
### 2. Chronology Integrity

Each projection row must have a valid:

``` text
chronology_address
``` 
#### Requirements
* Must match:
``` regex
^[1-9][0-9]*(?:\.[1-9][0-9]*)*$
``` 
Must correspond to the event’s actual position in the tree
#### Forbidden
* zero segments (0.1) ❌
* padded numbers (1.02) ❌
* malformed paths (1..2) ❌
### 3. No Ambiguous Chronology

Within a single projection:

chronology_address must be unique
#### Why

If two nodes share the same address:

* ordering becomes undefined
* resolver semantics break
* tree output becomes non-deterministic
### 4. Structure Source of Truth

Projection structure must reflect:

calendar_events.parent_event_id
#### Rules
1. Do not invent hierarchy
2. Do not override parent relationships
3. Do not “flatten” trees

## Projection Coverage Requirement
For any event that has at least one row in `calendar_event_books`,
a corresponding row MUST exist in `calendar_event_projections`.



### 5. Projection Scope Consistency

All rows in a projection must belong to the same logical scope:

same entity (if applicable)
same timeline context
same projection intent

Mixed-scope projections are invalid.

### 6. Optional: Projection-Level Parent Linking

If using:

parent_projection_row_id

Then:

it must match the corresponding parent_event_id
it must reference a row within the same projection

This is redundant but stabilizing, and can be used to:

decouple from global event graph
speed up tree construction



## Materialization Workflow (Recommended)
### Step 1 — Select candidate events
Define projection scope (entity, time range, etc.)
### Step 2 — Expand to closure
Ensure all required parents are included

This is where most systems fail.

### Step 3 — Validate structure
* parent existence ✅
* no cycles ✅
* valid hierarchy ✅
### Step 4 — Assign chronology_address
derived from canonical ordering
must match resolver expectations
### Step 5 — Validate uniqueness
No duplicate chronology_address within projection
### Step 6 — Insert projection rows

Only after all checks pass.

## Failure Policy

If any invariant is violated:

* ❌ Abort materialization
* ❌ Do not partially write projection
* ❌ Do not “fix” data silently
Relationship to Tree Builder

build_calendar_tree() assumes:

all parents exist
all chronology is valid
ordering is deterministic

If this contract is violated, the tree builder will:

flag orphans (diagnostic only)
throw on invalid chronology

But it is not responsible for fixing data.

Relationship to Chronology Resolver
calendar_chronology_resolver.php

Defines:

valid chronology format
path semantics
uniqueness expectations

Projection materialization must remain fully compatible with this.

## Summary
| Concern	| Enforced At                    |
|-----------|--------------------------------|
|Parent existence	| materialization                |
|Chronology validity	| materialization + tree builder |
|Ordering	| tree builder|
|Path resolution	|chronology resolver |

### Why This Matters

Without this contract:

* trees become inconsistent
* ordering becomes ambiguous
* projections drift from reality
* debugging becomes guesswork

With it:

* projections are deterministic artifacts
* tree building becomes trivial
* system behavior is predictable