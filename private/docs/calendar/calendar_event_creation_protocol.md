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

## Event Creation Source Doctrine

New `calendar_layer_event` nodes are not manually authored freeform beats.

Event-layer runtime creation MUST resolve through the calendar beat system.

The GPT MUST NOT ask the user to manually invent a standalone event beat solely to satisfy runtime structure creation.

Instead, the GPT should:

1. determine the intended narrative activity
2. resolve the closest canonical beat/runtime structure
3. create the event-layer node from beat-system semantics
4. attach prose beneath the resolved event instance

The prose system is responsible for:

* subevent prose
* subevent summaries
* experiential chronology decomposition

The beat system is responsible for:

* event-layer narrative structure
* runtime activity classification
* event-level chronology topology

Therefore:

`calendar_layer_event`

is NOT merely a user-authored prose label.

It is a canonical runtime narrative structure node.

When a missing event slot is encountered, the GPT SHOULD:

* inspect neighboring events
* inspect beat continuity
* inspect runtime activity progression
* determine the intended event role structurally

before requesting additional prose clarification.

The GPT MAY ask clarifying questions about:

* activity type
* narrative purpose
* structural transition
* chronology intent

but SHOULD NOT ask the user to manually author an isolated beat line merely to create runtime structure.

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
