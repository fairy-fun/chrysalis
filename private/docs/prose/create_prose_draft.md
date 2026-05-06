# Prose Draft → Calendar Subevent Workflow

## Current Contract

`createProseDraft` is now the primary prose write entrypoint.

When `projection.target_entity_id` starts with `calendar_event:`, the endpoint:

1. stores the prose draft
2. stores the prose projection
3. stores prose annotations
4. executes deterministic prose → calendar subevent generation

So calendar subevents may be created during `createProseDraft`.

This supersedes the older two-step wording where `createProseDraft` stored prose only and `executeCalendarBatchFromProse` had to be called separately.

## Endpoint

POST `/pecherie/chill-api/index.php`

```json
{
  "operation": "createProseDraft",
  "entity_id": "prose_draft:example-1",
  "title": "Example",
  "prose_body": "Arrive\nSetup\nBegin session",
  "draft_status_id": "prose_status_draft",
  "author_entity_id": null,
  "projection": {
    "projection_type_id": "projection_type_book",
    "target_entity_id": "calendar_event:322",
    "role_id": "primary",
    "projection_order": 1,
    "is_export_target": 1
  },
  "annotations": []
}
```

Calendar Execution Rule

If:

"target_entity_id": "calendar_event:<id>"

then createProseDraft calls:

execute_calendar_batch_from_prose(parent_event_entity_id, prose_body)

after the prose draft, projection, and annotations are persisted.

Parent Event Rules

projection.target_entity_id must:

refer to an existing calendar_events.entity_id
use the calendar_event:<id> format
refer to a calendar_layer_event node

Subevents are never attached to:

calendar_layer_week
calendar_layer_day
calendar_layer_time
calendar_layer_subevent

Only this transition is valid:

calendar_layer_event → calendar_layer_subevent
Verify Parent Event
SELECT
id,
entity_id,
projection_id,
layer_id,
parent_event_id,
sequence_index,
summary
FROM calendar_events
WHERE entity_id = 'calendar_event:322';

Expected:

layer_id = calendar_layer_event
projection_id IS NOT NULL
Verify After Write
SELECT
entity_id,
layer_id,
parent_event_id,
client_id,
beat_hash,
sequence_index,
summary
FROM calendar_events
WHERE parent_event_id = (
SELECT id
FROM calendar_events
WHERE entity_id = 'calendar_event:322'
)
AND layer_id = 'calendar_layer_subevent'
ORDER BY sequence_index;

Expected:

rows exist for generated prose beats
layer_id = calendar_layer_subevent
client_id is populated
sequence_index is continuous
repeated execution is idempotent
Internal Flow
createProseDraft
→ create_prose_draft()
→ insert prose_drafts row
→ insert prose_projections row
→ insert prose_annotation_spans rows
→ if target_entity_id starts with calendar_event:
→ execute_calendar_batch_from_prose()
→ generate_calendar_batch_from_prose()
→ create_calendar_subevent_core()
→ ensure_calendar_subevent()
→ ensure_calendar_node()
Rules

Do not write directly to calendar_events.

Do not attach subevents to non-event nodes.

Do not pass week/day/time hierarchy fields into createProseDraft.

Do not use projection_entity_id as runtime identity.

Do use calendar_event:<id> as compatibility ingress only.

Runtime calendar writes should resolve and propagate projection_id.

Low-Level Endpoint

executeCalendarBatchFromProse still exists and may be used for controlled replay/testing:

{
"operation": "executeCalendarBatchFromProse",
"parent_event_entity_id": "calendar_event:322",
"prose": "Arrive\nSetup\nBegin session"
}

This endpoint executes from the provided prose string. It does not load prose from stored draft records.