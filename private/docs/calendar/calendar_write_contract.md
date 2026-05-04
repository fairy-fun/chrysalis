# Calendar System — Write Contract

## Core Rule

All calendar writes must follow:

```text
API → ensure_calendar_* → ensure_calendar_node → DB
```
The only allowed calendar write entry points are:

ensure_calendar_week(...)
ensure_calendar_day(...)
ensure_calendar_time(...)
ensure_calendar_event(...)

No API endpoint, helper, migration, or framework file may write directly to calendar_events except:

private/framework/calendar/calendar_node_ensurer.php
Identity Model
calendar_events.id        = internal structural node id
calendar_events.event_id  = stable business id
calendar_events.entity_id = external API identity

Hard rule:

parent_event_id always points to calendar_events.id
parent_event_id never points to calendar_events.event_id
Structure Model

Calendar structure is defined only by:

parent_event_id + sequence_index

Layers:

week
day
time
event

Subevents are also calendar_layer_event nodes whose parent is another event node.

Write Responsibilities
API Layer

The API layer owns:

input validation
semantic validation
classval validation
payload shaping

The API layer must not:

INSERT INTO calendar_events
allocate sequence_index manually
write chronology_address
use event_id for parent linkage
construct structural paths
Boundary Layer

The boundary layer is:

private/framework/calendar/calendar_layer_ensurers.php

It owns:

parent resolution by entity_id
layer transition validation
semantic parent-child validation
delegation to ensure_calendar_node(...)
Primitive Layer

The primitive layer is:

private/framework/calendar/calendar_node_ensurer.php

It owns:

structural identity
idempotency
sequence_index allocation
entity row creation
calendar_events insert
duplicate-key retry behaviour

Do not move semantic validation into the primitive.

Chronology Rule

Chronology is read-only navigation.

Allowed:

resolve human-readable chronology addresses
walk the tree by parent_event_id + sequence_index
display chronology paths
search by chronology through resolver logic

Forbidden:

writing chronology_address
using chronology_address as identity
using chronology_address as hierarchy
using chronology_address as ordering
looking up structural nodes directly by chronology_address

## Semantic Fields

Semantic fields are payload data, not structure.

They must not influence structural placement or ordering.

Current semantic fields include:

- time_label_id
- event_type_id
- domain_id
- class_type_id
- location_id
- notes
- source_document

### Classval-backed fields

These fields derive their meaning strictly from classval tables.

They must be validated using their canonical classval IDs before calling the ensurer.

Canonical ID format is required.

Do not use:
- symbolic codes
- human-readable labels

Mappings:

- time_label_id  → calendar_time_label_classvals.id
- event_type_id  → calendar_event_type_classvals.id
- domain_id      → calendar_domain_classvals.id
- class_type_id  → calendar_class_type_classvals.id

Rule:

Semantic meaning exists only in classval tables.

No semantic meaning may be introduced through:
- summary
- sequence_index
- inferred logic

## Time Node Semantics

Time nodes are structural only.

They do not carry semantic meaning by position.

Rules:

- sequence_index does not imply time-of-day
- time nodes must not be interpreted as Morning/Afternoon/etc.
- semantic labels must come exclusively from time_label_id

Source of truth:

calendar_time_label_classvals

Not:
- time nodes
- sequence_index
- summary

## Display Fields
summary

summary is display text.

For classval-backed time labels:

time_label_id = semantic meaning (classval ID)
summary       = display label (derived from classval.label)
sequence_index = structural order only

summary must not be treated as a semantic source of truth.

## Idempotency

Writes must be idempotent.

Repeated calls with the same structural identity must return the existing node instead of creating duplicates.

Structural identity is:

projection_entity_id
layer_id
parent_event_id
sequence_index

For append-style event creation, the API passes:

sequenceIndex: null

The primitive allocates the next valid sequence_index.

Forbidden Patterns

These are contract violations:

INSERT INTO calendar_events outside calendar_node_ensurer.php
legacy create_calendar_event* usage
manual sequence_index allocation outside primitive
parent_event_id = event_id
WHERE chronology_address = ...
UPDATE calendar_events SET chronology_address = ...
free-text canonical classval values
classval validation for location_id
Final Principle

Structure creates the node.

Identity exposes the node.

Semantics describe the node.

Display names the node.

Chronology only helps humans find the node.


### `private/docs/calendar/calendar_write_surface.md`


# Calendar System — Write Surface

## Purpose

This document defines the public write surface for the calendar system.

The write surface is intentionally narrow.

All writes must enter through API endpoints, pass through layer ensurers, and terminate in the single primitive node ensurer.

```text
API → ensure_calendar_* → ensure_calendar_node → DB
```
Allowed Framework Entry Points

The only allowed write functions are:

ensure_calendar_week(...)
ensure_calendar_day(...)
ensure_calendar_time(...)
ensure_calendar_event(...)

These live in:

private/framework/calendar/calendar_layer_ensurers.php

All of them delegate to:

private/framework/calendar/calendar_node_ensurer.php
API Endpoints

Current calendar write endpoints:

public_html/pecherie/chill-api/calendar/create_calendar_week.php
public_html/pecherie/chill-api/calendar/create_calendar_day.php
public_html/pecherie/chill-api/calendar/create_calendar_time.php
public_html/pecherie/chill-api/calendar/create_calendar_event.php
public_html/pecherie/chill-api/calendar/create_calendar_subevent.php
Week Creation

Endpoint:

create_calendar_week.php

Allowed input:

{
  "operation": "createCalendarWeek",
  "projection_entity_id": "prose_projection:...",
  "week_index": 1,
  "week_label": "Week 1"
}

Payload to ensurer:

[
    'summary' => $weekLabel,
]

Rules:

week_label maps to summary
week_index maps to sequence_index
no chronology writes
Day Creation

Endpoint:

create_calendar_day.php

Allowed input:

{
  "operation": "createCalendarDay",
  "parent_week_entity_id": "calendar_event:...",
  "day_index": 1,
  "day_label": "Sunday",
  "real_date_id": "DATE-..."
}

Payload to ensurer:
```
[
    'summary' => $dayLabel,
    'real_date_start_id' => $realDateId,
    'real_date_end_id' => $realDateId
]
```
Rules:

day_label maps to summary
day_index maps to sequence_index
real_date_id maps to start/end date fields
parent is resolved by entity_id
parent_event_id uses internal calendar_events.id


## Time Creation

Endpoint:

create_calendar_time.php

Allowed input:

{
"operation": "createCalendarTime",
"parent_day_entity_id": "calendar_event:...",
"time_index": 1,
"time_label_id": "CLASSVAL-TIME-002"
}

Payload to ensurer:
```
[
'summary' => $labelFromClassval,
'time_label_id' => $timeLabelId
]
```
Rules:

- time_index maps to sequence_index
- time_label_id must be a valid calendar_time_label_classvals.id
- time_label_id is the only source of semantic meaning
- summary is derived from calendar_time_label_classvals.label

### Legacy Fallback (Non-Canonical)

If time_label_id is not provided:

- a free-text time_label may be accepted
- it is stored as summary only
- no semantic classification is recorded

This is legacy behavior.

New writes must use time_label_id.

## Event Creation

Endpoint:

create_calendar_event.php

Allowed input:

{
  "operation": "createCalendarEvent",
  "parent_time_entity_id": "calendar_event:...",
  "event_label": "Foxtrot tutorial",
  "event_type_id": "calendar_event_type_...",
  "location_id": "PLACE-013",
  "domain_id": "calendar_domain_...",
  "class_type_id": "calendar_class_type_...",
  "notes": "...",
  "source_document": "..."
}

Payload to ensurer:

[
    'summary' => $eventLabel,
    'event_type_id' => $eventTypeId,
    'location_id' => $locationId,
    'domain_id' => $domainId,
    'class_type_id' => $classTypeId,
    'notes' => $notes,
    'source_document' => $sourceDocument,
]

## Rules:

parent_time_entity_id is resolved by entity_id
event is appended under the time node
sequence_index is allocated by ensure_calendar_node
event_type_id must be classval-valid
domain_id must be classval-valid
class_type_id must be classval-valid
location_id is not a classval
Subevent Creation

Endpoint:

create_calendar_subevent.php

Allowed input:

{
  "operation": "createCalendarSubevent",
  "parent_event_entity_id": "calendar_event:...",
  "event_label": "Sub-beat",
  "event_type_id": "calendar_event_type_...",
  "location_id": "PLACE-013",
  "domain_id": "calendar_domain_...",
  "class_type_id": "calendar_class_type_...",
  "notes": "...",
  "source_document": "..."
}

Payload to ensurer:

[
    'summary' => $eventLabel,
    'event_type_id' => $eventTypeId,
    'location_id' => $locationId,
    'domain_id' => $domainId,
    'class_type_id' => $classTypeId,
    'notes' => $notes,
    'source_document' => $sourceDocument,
]

Rules:

subevents are calendar_layer_event nodes
parent_event_entity_id must resolve to a calendar event node
parent_event_id uses internal calendar_events.id
sequence_index is allocated by ensure_calendar_node

## Classval Validation

Classval validation belongs at the API/helper layer.

Valid classval-backed calendar fields:

time_label_id  → calendar_time_label_classvals
event_type_id  → calendar_event_type_classvals
domain_id      → calendar_domain_classvals
class_type_id  → calendar_class_type_classvals

Shared helper:

private/framework/classvals/classval_validation.php

Required helper:

assert_valid_classval(...)
Reference Fields
location_id

location_id is a reference field.

It is not part of the classval system.

Do not validate it with:

assert_valid_classval(...)

If location validation is added later, validate against the real location/entity model.

Forbidden Write Surface

Do not use or reintroduce:

create_calendar_event*
create_calendar_event_under_*
direct INSERT INTO calendar_events
manual sequence_index
chronology_address writes
event_id as structural parent
location_id classval validation
Final Rule

The write surface should stay boring.

APIs validate and shape payloads.

Layer ensurers resolve and guard parents.

The primitive writes structure.

Nothing else writes calendar nodes.