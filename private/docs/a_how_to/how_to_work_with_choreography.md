# How to Work With Choreography in Calendar

## Purpose

This guide documents the canonical calendar-facing choreography model.

A calendar event should be able to link directly to one or more specific choreography elements.

Examples:

```text
Rehearse a medley
Rehearse a routine
Rehearse a segment
Drill a figure
Review choreography
```

The important rule is not the choreography level.
The important rule is that the link belongs to the canonical calendar event surface.

---

# Core Rule

The canonical event surface is:

```text
calendar_events
```

If choreography is attached to an event, it should attach to:

```text
calendar_events.id
```

That means the event-facing choreography link semantics should be understood as:

```text
calendar_event_id
    -> calendar_events.id
```

and:

```text
choreography_entity_id
    -> entities.id
```

So a calendar event can carry choreography links directly, without requiring the documentation to route through any older intermediary calendar structure.

---

# Canonical Link Semantics

A choreography attachment row should mean:

```text
This calendar event is linked to this choreography entity
with this relationship type
and this optional scope note.
```

In practice, the semantic fields are:

- `calendar_event_id`
- `link_type_id`
- `choreography_entity_id`
- `scope_note`

The canonical meaning of each field is:

- `calendar_event_id`: which calendar event the link belongs to
- `link_type_id`: what the event is doing with the choreography
- `choreography_entity_id`: which choreography target is in scope
- `scope_note`: optional human clarification

---

# Choreography Identity Model

The choreography target should be stored as an entity id:

```text
choreography_entity_id
```

which resolves through:

```text
entities.id
```

This keeps the event link flexible enough to reference choreography at different levels.

Examples:

```text
MEDLEY-001
ROUTINE-001
SEG-008
FIG-014
```

The event-facing link does not need to store choreography type directly.
It only needs the choreography entity identifier.

The choreography domain then determines what that entity is through surfaces such as:

```text
entities
choreography_entity_map
choreography_hierarchy
choreography_relationships
```

---

# What Counts As A Specific Choreography Element

A specific choreography element may be any choreography entity that the choreography domain recognizes.

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

# Link Types

The event-facing choreography link should also carry relationship semantics through:

```text
link_type_id
```

Verified live values currently include patterns such as:

```text
clt_rehearses
clt_drills
```

Those resolve through:

```text
calendar_link_type_classvals
```

Current labels include:

```text
rehearses
drills
```

This means the choreography link can distinguish between different event behaviors,
for example:

```text
The event rehearses this choreography target.
The event drills this choreography target.
```

---

# Business Semantics

Calendar choreography attachments represent event scope.

Examples:

```text
This event rehearses the full medley.
This event drills one specific segment.
This event focuses on a specific figure inside a broader choreography context.
```

A single calendar event may link to multiple choreography targets.

Examples:

```text
Medley + Segment
Routine + Figure
Segment + Figure
Multiple Figures
```

The event layer does not need to encode choreography hierarchy directly.
It only stores the references.

---

# Recommended Query Shape

If choreography is modeled directly on the canonical event surface,
the read pattern should look like:

```sql
SELECT
    cel.*
FROM calendar_event_choreography_links cel
WHERE cel.calendar_event_id = :calendar_event_id;
```

If link types are resolved with labels:

```sql
SELECT
    cel.id,
    cel.calendar_event_id,
    cel.link_type_id,
    clt.code AS link_type_code,
    clt.label AS link_type_label,
    cel.choreography_entity_id,
    cel.scope_note
FROM calendar_event_choreography_links cel
JOIN calendar_link_type_classvals clt
    ON clt.id = cel.link_type_id
WHERE cel.calendar_event_id = :calendar_event_id;
```

If choreography type needs to be resolved:

```sql
SELECT
    cel.id,
    cel.calendar_event_id,
    cel.choreography_entity_id,
    e.entity_type_id
FROM calendar_event_choreography_links cel
JOIN entities e
    ON e.id = cel.choreography_entity_id
WHERE cel.calendar_event_id = :calendar_event_id;
```

These query shapes reflect the intended canonical model:

```text
calendar event -> choreography link -> choreography entity
```

---

# Design Principle

The calendar system should not try to redefine choreography.

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

then the canonical model should be understood as:

```text
calendar_events.id
    -> event-facing choreography link table.calendar_event_id
event-facing choreography link table.choreography_entity_id
    -> entities.id
```

So the main things this documentation must keep clear are:

- the linked event is a `calendar_events` row
- the linked choreography target is a choreography entity id
- link semantics are carried by `link_type_id`
- a single event may link to multiple choreography targets
- choreography type should be resolved through the choreography domain, not duplicated into the event link row
