# Calendar System — Week Layer

## Status

Updated after the calendar identity refactor. Any previous manual SQL insert patterns are deprecated and must not be used.

## Overview

Week-level calendar nodes are the root layer of the hierarchy:

`Week -> Day -> Time -> Event`

A Week has no parent and anchors all downstream structure.

## Canonical Write Boundary

All calendar node creation must go through:

`private/framework/calendar/calendar_node_ensurer.php`

Primitive:

`ensure_calendar_node(...)`

Week creation should be implemented via an ensurer-backed wrapper (e.g., `ensure_calendar_week(...)`) that validates parent rules and delegates to the primitive.

## Core Invariants

### Entity-first (enforced by ensurer)

Each calendar node has a corresponding entity:

`entity_id = calendar_event:<id>`

The ensurer guarantees the entity row exists and that its `entity_type_id` matches the node’s `layer_id`.

### Identity and relationships

- `calendar_events.id` is the structural row identity used for joins
- `calendar_events.entity_id` is the external handle derived from `id`

Relationships use:

`parent_event_id -> calendar_events.id`

For weeks:

`parent_event_id = NULL`

### Structural position

Structure is defined by:

`parent_event_id, layer_id, sequence_index`

`chronology_address` is passive (display/search only) and must not be used as a write input.

## Construction (ensurer-backed)

A week is created by calling the appropriate wrapper which internally calls:

`ensure_calendar_node($pdo, $projectionEntityId, 'calendar_layer_week', null, $sequenceIndexOrNull, $payload)`

The ensurer provides:

- idempotent creation
- append-safe `sequence_index` assignment
- parent/child integrity
- entity/type alignment

## Querying Weeks

Read queries may use `layer_id` and projection scoping. They must not be repurposed as write procedures.

## Gotchas (updated)

- Do not hand-write SQL inserts for calendar nodes
- Do not compute hierarchy from `chronology_address` in write paths
- Do not expose internal `id` as a public handle
- Do not decide projection validity from `layer_id` alone (use guards)

## Final Rule

If a calendar write does not go through `ensure_calendar_node(...)`, it is a bug.
