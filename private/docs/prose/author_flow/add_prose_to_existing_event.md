# Add Prose To Existing Event

## Intent

Add new prose to an already-existing calendar event by routing the operator through the existing workflow state machine.

This is a bounded operator action window.

The goal is not to redesign prose creation.
The goal is to supply the workflow with the minimum required inputs, let the runtime resolve state, and stop after one prose operation.

Operator memory is not authoritative.
Durable runtime state is authoritative.

---

## Runtime Workflow Mapping

Primary workflow definition:

`private/framework/procedures/workflow_calendar_event_add_prose_definition.php`

This operator procedure maps to the runtime flow:

```text
await_calendar_event_entity_id
    -> validate_calendar_event_entity
        success -> route_calendar_event_layer
        failure -> terminal_calendar_event_not_found

route_calendar_event_layer
    calendar_layer_event -> await_projection_binding
    calendar_layer_subevent -> await_prose_text
    default -> terminal_wrong_layer

await_projection_binding
    -> validate_projection_binding
        success -> await_prose_text
        failure -> terminal_projection_mismatch

await_prose_text
    -> persist_prose_draft

persist_prose_draft
    success -> terminal_prose_created
    failure -> terminal_prose_persist_failed