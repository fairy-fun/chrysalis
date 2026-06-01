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
```
{
"operation": "createCalendarEvent",
"parent_time_entity_id": "calendar_event:TIME_ID",
"event_label": "Foxtrot Technique — Centre vs Foot",

"event_type_id": "EVENT_TYPE_CLASS",
"class_type_id": "CLASS_TYPE_CLASS",
"location_id": "PLACE_ID",

"notes": "...",
"source_document": "..."
}
```
#### Rules
parent_time_entity_id → required
event_label → maps to summary

event_type_id → classval (validated)
class_type_id → classval (validated)

event_type_id is the canonical root-event input used for beat-classset resolution via `calendar_event_type_classvals.beat_classset_id`.

domain categorization now lives on the event type registry, not on `calendar_events` rows.

location_id → reference (NOT classval)
### Structural Behaviour
sequence_index is NOT provided

→ API passes null
→ ensure_calendar_node assigns next index
→ append-only behaviour
### Subevent Creation
#### Operation
createCalendarSubevent
#### Handler
public_html/pecherie/chill-api/calendar/create_calendar_subevent.php
#### Request
```
{
"operation": "createCalendarSubevent",
"parent_event_entity_id": "calendar_event:EVENT_ID",
"event_label": "Sub-beat",

"event_type_id": "EVENT_TYPE_CLASS",
"class_type_id": "CLASS_TYPE_CLASS",
"location_id": "PLACE_ID"
}
```
#### Rules
parent_event_entity_id → must resolve to event node

subevents inherit beat-classset context from the canonical parent event
subevents do not accept or persist a separate domain_id
same append behaviour

### Day Creation

Same pattern as time:

day_index = API-only
sequence_index = real structure
Index Fields (IMPORTANT)
day_index
time_index
event_index
subevent_index

### Status:

deprecated / transitional
NOT structural truth
NOT guaranteed persisted
Sequence Index
sequence_index = ONLY structural ordering field

#### Rules:

owned by primitive
never manually assigned by API (default case)
optional only for strict ensure behaviour

### Time Creation

#### Operation

`createCalendarTime`

#### Input

```json
{
  "time_index": 1,
  "time_label_id": "CLASSVAL-TIME-001"
}
```
#### Rule

`time_index` is structural chronology ordering within the parent day.

`time_label_id` is required semantic meaning and MUST reference calendar_time_label_classvals.

For `calendar_layer_time` rows, summary is NOT the canonical human-readable label.

Clients MUST render the resolved label:

`calendar_events.time_label_id → calendar_time_label_classvals.label
`
#### Canonical Model
```
time_index     = structural order within the day
time_label_id  = semantic time label
time_label     = resolved display label
summary        = optional description only; not the label contract
```

## get_calendar_times_for_day

### Response Contract

Each returned `calendar_layer_time` node MUST include:

```json
{
  "entity_id": "calendar_event:303",
  "projection_entity_id": "...",
  "layer_id": "calendar_layer_time",
  "sequence_index": 1,

  "time_index": 1,
  "time_label_id": "CLASSVAL-TIME-001",
  "time_label_code": "early_morning",
  "time_label": "Early Morning",
  "time_sort_order": 2,

  "summary": "",
  "chronology_address": "3.1.1"
}
```
#### Time Label Rendering Rule

For calendar_layer_time rows, summary is NOT the canonical human-readable label.

Clients MUST render:

`calendar_events.time_label_id`
→ `calendar_time_label_classvals`

using the resolved fields:

* `time_label`
* `time_label_code`
* `time_sort_order`

[time_index]() is structural chronology ordering only and MUST NOT be treated as semantic display meaning.

### Classval Validation

Validated before calling ensurer:

time_label_id
event_type_id
class_type_id

#### Helper:

assert_valid_classval(...)
Reference Fields
location_id

#### Rules:

* NOT classval
* NOT validated via classval system
* passed through unchanged
* Forbidden Surface
* POST /calendar/event/ensure         ❌ (not real)
* direct INSERT INTO calendar_events ❌
* manual sequence_index              ❌
* chronology_address writes          ❌
* structural parent lookup by legacy event_id ❌
#### Final Rule
The API does not define structure.

The primitive defines structure.

The API only supplies intent.
