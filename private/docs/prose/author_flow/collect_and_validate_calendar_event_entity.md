Collect And Validate Calendar Event Entity
Purpose

Collect a calendar event entity identifier from the user and validate it against durable state.

This procedure maps directly onto runtime states:

await_calendar_event_entity_id
validate_calendar_event_entity

The runtime state machine is authoritative.

This document defines only the bounded operational scope.

Runtime Behavior

If no entity_id is present:

ask the user for the target calendar event entity_id
remain in the current chat session
wait for user reply

If an entity_id is supplied:

consume the value immediately
run the workflow validation query
inspect durable state
continue runtime execution
Canonical Validation Query
SELECT
id,
entity_id,
layer_id,
projection_id
FROM calendar_events
WHERE entity_id = :entity_id
LIMIT 1
Success Condition

If exactly one row is returned:

hydrate runtime context with the returned calendar event
transition to:
route_calendar_event_layer
Failure Condition

If no row is returned:

transition to:

terminal_calendar_event_not_found

Do not infer alternate events.

Do not inspect latest-event heuristics.

Do not mutate author intent.

Stop Condition

This bounded procedure stops when:

the calendar event has been validated
or
validation fails cleanly