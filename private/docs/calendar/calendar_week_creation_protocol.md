# Calendar Week Construction Protocol

## Status

This document has been updated after the calendar identity refactor.

The previous manual SQL procedure for constructing weeks, days, times, and events is deprecated and must not be used.

## Canonical Write Boundary

All calendar node creation must go through:

`private/framework/calendar/calendar_node_ensurer.php`

The write primitive is:

`ensure_calendar_node(...)`

Event-layer creation should go through the semantic creator in:

`private/framework/calendar/calendar_event_semantic_creator.php`

## Current Construction Rule

A complete week is still structurally:

`week -> day -> time -> event`

But construction must be ensurer-backed at every layer. No level may be hand-written into the database.

The ensurer is responsible for:

* creating the calendar row
* creating or validating the matching entity row
* assigning append-safe `sequence_index` values
* preserving parent/child structure
* keeping `calendar_events.layer_id` aligned with `entities.entity_type_id`

## Chronology Address

`chronology_address` is passive.

It may be used for display, search, and editorial reference, but it must not be used as identity, hierarchy, ordering, or write input.

## Prose Attachment Rule

Prose may attach only to event-layer calendar entities.

A valid prose target must satisfy:

`entities.entity_type_id = entity_type_calendar_event`

and

`calendar_events.layer_id = calendar_layer_event`

## Validation Queries

Read-only validation queries are still allowed. They must not be converted into write or repair procedures that bypass the ensurer.

## Final Rule

If a calendar write does not go through `ensure_calendar_node(...)`, it is a bug.
