# Calendar Event Creation Protocol

## Status

This document has been updated after the calendar identity refactor.

The old manual SQL creation procedure is no longer valid and must not be used.

## Canonical Write Boundary

All calendar writes must go through:

`private/framework/calendar/calendar_node_ensurer.php`

The primitive is:

`ensure_calendar_node(...)`

Event-layer callers should use the semantic creator functions in:

`private/framework/calendar/calendar_layer_ensurers.php`

Those functions validate the parent entity and delegate to the ensurer.

## Current Rules

Manual creation of rows in `calendar_events` is forbidden outside `calendar_node_ensurer.php`.

Structure is represented by `parent_event_id`, `layer_id`, and `sequence_index`.

`chronology_address` is passive. It may be used for display, search, and editorial reference, but not for identity, hierarchy, ordering, or write input.

External references should use `entity_id`.

Layer and entity type must agree:

`calendar_layer_week` maps to `entity_type_calendar_week`.
`calendar_layer_day` maps to `entity_type_calendar_day`.
`calendar_layer_time` maps to `entity_type_calendar_time`.
`calendar_layer_event` maps to `entity_type_calendar_event`.

If a calendar write does not go through `ensure_calendar_node(...)`, it is a bug.
