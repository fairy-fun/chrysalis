# How to Work With Choreography

## Purpose

This guide explains how choreography is linked to canonical calendar events in Chrysalis.

The live event-facing choreography link table is:

```text
calendar_choreography_links
```

A single calendar event may link to one or more specific choreography targets.

Examples:

```text
Rehearse a medley
Rehearse a routine
Rehearse a segment
Drill a segment
Drill a figure
Review choreography
```

The important rule is not the choreography level.
The important rule is that the link belongs directly to the canonical calendar event surface.

---

# Core Rule

The canonical event surface is:

```text
calendar_events
```

Choreography links attach directly to:

```text
calendar_choreography_links.calendar_event_id
    -> calendar_events.id
```

The choreography target is stored as:

```text
calendar_choreography_links.choreography_entity_id
    -> entities.id
```

So the live model is:

```text
calendar event
    -> choreography link row
    -> choreography entity
```

---

# Live Table Structure

Verified live columns in `calendar_choreography_links`:

```text
id                     bigint      primary key
calendar_event_id      bigint      not null
link_type_id           varchar     not null
choreography_entity_id varchar     not null
scope_note             mediumtext  nullable
created_at             datetime    not null
```

This is the current operational event-to-choreography attachment surface.

---

# Column Semantics

Each row in `calendar_choreography_links` means:

```text
This calendar event is linked to this choreography entity
with this relationship type
and this optional scope note.
```

The fields mean:

- `calendar_event_id`: which canonical calendar event the link belongs to
- `link_type_id`: what the event is doing with the choreography
- `choreography_entity_id`: which choreography target is in scope
- `scope_note`: optional human clarification

---

# Link Types

Verified live `link_type_id` values currently include:

```text
clt_rehearses
clt_drills
```

Those resolve through:

```text
calendar_link_type_classvals
```

Current live codes and labels include:

```text
clt_rehearses -> rehearses
clt_drills    -> drills
```

This means the choreography link can distinguish between event behaviors such as:

```text
The event rehearses this choreography target.
The event drills this choreography target.
```

---

# Choreography Identity Model

The choreography target is stored as an entity id:

```text
choreography_entity_id
```

Examples from the live table currently include:

```text
medley-001
seg-008
segment-waltz-2
```

This means the event-facing choreography link is entity-based.
The link row does not need to duplicate choreography type.

The choreography domain determines what the target actually is through surfaces such as:

```text
entities
choreography_entity_map
choreography_hierarchy
choreography_relationships
```

---

# What Counts As A Specific Choreography Element

A specific choreography element may be any choreography target represented by a choreography entity id.

Examples include:

```text
Medley
Routine
Segment
Figure
```

So the semantic rule is:

```text
A calendar event can link to a specific choreography element
as long as that choreography target has a choreography entity id.
```

---

# Current Live Examples

Verified live rows currently include:

```text
id = 1
calendar_event_id = 24
link_type_id = clt_rehearses
choreography_entity_id = medley-001
scope_note = Primary segment focus of the event.
```

```text
id = 2
calendar_event_id = 49
link_type_id = clt_drills
choreography_entity_id = seg-008
scope_note = Specific figure receiving extra attention.
```

```text
id = 3
calendar_event_id = 52
link_type_id = clt_rehearses
choreography_entity_id = segment-waltz-2
scope_note = General company technique and integration work inside the Book 1 medley system; no narrower segment named in source calendar yet.
```

These examples confirm:

- choreography links are attached directly to `calendar_event_id`
- choreography targets are currently stored as entity ids
- different event rows may link to different choreography targets at different levels of specificity

---

# Business Semantics

Calendar choreography attachments represent event scope.

Examples:

```text
This event rehearses the full medley.
This event drills one specific segment.
This event focuses on a narrower choreography target inside a broader rehearsal context.
```

A single calendar event may link to multiple choreography targets.

Examples:

```text
Medley + Segment
Routine + Figure
Segment + Figure
Multiple Figures
```

The calendar layer does not need to encode choreography hierarchy directly.
It only stores the references and the link semantics.

---

# Common Queries

## Find choreography attached to a calendar event

```sql
SELECT
    ccl.*
FROM calendar_choreography_links ccl
WHERE ccl.calendar_event_id = :calendar_event_id;
```

---

## Find link semantics with labels

```sql
SELECT
    ccl.id,
    ccl.calendar_event_id,
    ccl.link_type_id,
    clt.code AS link_type_code,
    clt.label AS link_type_label,
    ccl.choreography_entity_id,
    ccl.scope_note
FROM calendar_choreography_links ccl
JOIN calendar_link_type_classvals clt
    ON clt.id = ccl.link_type_id
WHERE ccl.calendar_event_id = :calendar_event_id;
```

---

## Find all events linked to a choreography entity

```sql
SELECT
    ccl.*
FROM calendar_choreography_links ccl
WHERE ccl.choreography_entity_id = :entity_id;
```

Example:

```sql
SELECT *
FROM calendar_choreography_links
WHERE choreography_entity_id = 'seg-008';
```

---

## Resolve choreography target types

```sql
SELECT
    ccl.id,
    ccl.calendar_event_id,
    ccl.choreography_entity_id,
    e.entity_type_id
FROM calendar_choreography_links ccl
JOIN entities e
    ON e.id = ccl.choreography_entity_id
WHERE ccl.calendar_event_id = :calendar_event_id;
```

This is the right pattern when you need to know whether the linked choreography target is a medley, routine, segment, figure, or another choreography entity type.

---

# Design Principle

The calendar system should not redefine choreography.

Its job is only to say:

```text
this event links to this choreography target
with this relationship type
```

The choreography domain is responsible for determining:

```text
what the choreography entity is
how it is typed
how it is displayed
how it relates to other choreography entities
```

through the entity registry and choreography model.

---

# Practical Conclusion

If the goal is:

```text
A calendar event should be able to link to a specific choreography element.
```

then the live canonical model is:

```text
calendar_events.id
    -> calendar_choreography_links.calendar_event_id
calendar_choreography_links.choreography_entity_id
    -> entities.id
```

So the main things this documentation must keep clear are:

- the linked event is a `calendar_events` row
- the linked choreography target is a choreography entity id
- the active live link table is `calendar_choreography_links`
- link semantics are carried by `link_type_id`
- a single event may link to multiple choreography targets
- choreography type should be resolved through the choreography domain, not duplicated into the event link row
