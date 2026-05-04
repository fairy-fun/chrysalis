# Calendar System — Write Surface

## Purpose

Defines the **actual API surface** for calendar writes.

This is not conceptual. This reflects the **real, callable system**.

---

## Core Write Flow

```text
POST → index.php (operation)
     → create_calendar_*.php
     → ensure_calendar_*
     → ensure_calendar_node
     → DB
```
## Router Model (ACTUAL)

All requests go through:

https://antheapeche.com/pecherie/chill-api/index.php

Using:

```text
{
"operation": "..."
}
```

### Event Creation

#### Operation
```text
createCalendarEvent
```

#### Handler
#### Operation
```text
public_html/pecherie/chill-api/calendar/create_calendar_event.php
```

### Request

{
"operation": "createCalendarEvent",
"parent_time_entity_id": "calendar_event:TIME_ID",
"event_label": "Foxtrot Technique — Centre vs Foot",

"event_type_id": "EVENT_TYPE_CLASS",
"domain_id": "DOMAIN_CLASS",
"class_type_id": "CLASS_TYPE_CLASS",
"location_id": "PLACE_ID",

"notes": "...",
"source_document": "..."
}
Rules
parent_time_entity_id → required
event_label → maps to summary

event_type_id → classval (validated)
domain_id → classval (validated)
class_type_id → classval (validated)

location_id → reference (NOT classval)
Structural Behaviour
sequence_index is NOT provided

→ API passes null
→ ensure_calendar_node assigns next index
→ append-only behaviour
Subevent Creation
Operation
createCalendarSubevent
Handler
public_html/pecherie/chill-api/calendar/create_calendar_subevent.php
Request
{
"operation": "createCalendarSubevent",
"parent_event_entity_id": "calendar_event:EVENT_ID",
"event_label": "Sub-beat",

"event_type_id": "EVENT_TYPE_CLASS",
"domain_id": "DOMAIN_CLASS",
"class_type_id": "CLASS_TYPE_CLASS",
"location_id": "PLACE_ID"
}
Rules
parent_event_entity_id → must resolve to event node

same semantic rules as event creation
same append behaviour
Time Creation
Operation
createCalendarTime
Transitional Input
{
"time_index": 1
}

Rule:

time_index is API-only
maps to sequence_index
NOT persisted
Canonical Model
sequence_index = real structure
time_label_id  = semantic meaning
summary        = display
Day Creation

Same pattern as time:

day_index = API-only
sequence_index = real structure
Index Fields (IMPORTANT)
day_index
time_index
event_index
subevent_index

Status:

deprecated / transitional
NOT structural truth
NOT guaranteed persisted
Sequence Index
sequence_index = ONLY structural ordering field

Rules:

owned by primitive
never manually assigned by API (default case)
optional only for strict ensure behaviour
Classval Validation

Validated before calling ensurer:

time_label_id
event_type_id
domain_id
class_type_id

Helper:

assert_valid_classval(...)
Reference Fields
location_id

Rules:

NOT classval
NOT validated via classval system
passed through unchanged
Forbidden Surface
POST /calendar/event/ensure         ❌ (not real)
direct INSERT INTO calendar_events ❌
manual sequence_index              ❌
chronology_address writes          ❌
event_id as parent                 ❌
Final Rule
The API does not define structure.

The primitive defines structure.

The API only supplies intent.