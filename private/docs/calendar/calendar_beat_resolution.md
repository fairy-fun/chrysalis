# Calendar Beat Resolution

## Status

Canonical.

The calendar beat system resolves beat types through domain-specific beat classsets.

The deprecated `calendar_beat_domain_map` table must not be used for beat resolution.

## Canonical Resolution Path

```text
calendar_event.entity_id
    ↓
calendar_events.domain_id
    ↓
calendar_domain_beat_classset_map
    ↓
classset_id
    ↓
cvt_calendar_beat_type (set_id, code)
    ↓
beat_type_id
```
## Identity Model

Beat type identity is scoped by classset.

(set_id, code) → beat_type_id

Beat codes are not globally authoritative.

The same beat code may exist in more than one classset and may resolve to a different beat type row depending on the parent event domain.

## Classset Role

calendar_domain_beat_classset_map is the authoritative bridge from calendar domain to beat classset.

cvt_calendar_beat_type.set_id partitions beat types by classset.

The planner must resolve the parent event first, then resolve the parent event domain, then select the classset, then resolve the beat type by (set_id, code).

## Fallback

The default fallback classset is:

CLASSSET-CALENDAR-BEAT-001

This fallback is defensive only.

It is not the primary resolution mechanism and must not be used to mask missing or invalid parent event resolution.

Failure Conditions

The planner must fail if:

* parent_event_entity_id is missing.
* The parent event cannot be resolved.
* The parent event is not a valid calendar event.
* The beat code is unknown within the resolved classset.
* Beat type resolution produces no row.
* Beat type resolution produces more than one row.

## Deprecated Table

calendar_beat_domain_map is deprecated.

It encodes the wrong relationship:

domain → individual beat type

The correct relationship is:

domain → beat classset → beat types

Do not read from calendar_beat_domain_map.

Do not use it as validation authority.

Do not reintroduce planner logic that joins domain directly to beat type.