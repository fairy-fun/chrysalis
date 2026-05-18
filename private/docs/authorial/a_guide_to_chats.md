# Starting a Workflow From a New NL Chat

Send a request through:

public_html/pecherie/chill-api/index.php

Body:

```json
{
  "operation": "startWorkflowChat",
  "message": "I want to add prose to a calendar event."
}
```

The runtime will:

resolve the workflow from the NL message
enter the workflow entry state
continue recursively
return awaiting_input if more input is required

Example expected result:
```json
{
"status": "ok",
"result": {
"status": "awaiting_input",
"workflow_id": "calendar_event_add_prose",
"state_id": "await_calendar_event_entity_id",
"expected_input": "entity_id"
  }
}
```