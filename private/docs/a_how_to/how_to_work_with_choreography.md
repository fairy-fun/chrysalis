# How to Work With Choreography in Calendar

## Purpose

This guide explains how a calendar event is linked to specific choreography in the live Chrysalis database.

In the current live system, choreography is attached to calendar records through:

```text
calendar_choreography_links
```

That table allows a single calendar record to reference one or more specific choreography entities that are being rehearsed, drilled, reviewed, taught, or discussed.

Examples:

```text
Rehearse a medley
Rehearse a routine
Rehearse a segment
Drill a figure
```

---

# Core Rule

A calendar event in user-facing language is represented in the database by a row in:

```text
calendar_records
```

The choreography attachment row then points at that event through:

```text
calendar_choreography_links.calendar_record_id
    -> calendar_records.id
```

So if you want to attach a specific choreography element to a calendar event,
the actual stored link is:

```text
calendar_records.id
    -> calendar_choreography_links.calendar_record_id
```

not a separate `calendar_event_id` column.

---

# Active Live Table

The currently populated live table is:

```text
calendar_choreography_links
```

Verified live columns:

```text
id                       bigint      primary key
calendar_record_id       bigint      not null
link_type_id             varchar     not null
choreography_entity_id   varchar     not null
scope_note               mediumtext  nullable
created_at               datetime    not null
```

This is the current operational link surface for choreography attached to calendar records.

---

# Link Semantics

Each row in `calendar_choreography_links` means:

```text
This calendar record is linked to this choreography entity
with this relationship type
and this optional scope note.
```

In practice:

- `calendar_record_id` identifies the calendar record
- `link_type_id` describes the relationship semantics
- `choreography_entity_id` identifies the choreography target
- `scope_note` stores optional human clarification

Verified live `link_type_id` values currently include:

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
-drills
```

---

# Choreography Identity Model

The currently active link table stores choreography as an entity id:

```text
choreography_entity_id
```

Examples from the live table include:

```text
MEDLEY-001
SEG-008
```

This means the calendar system is currently linking to choreography through the entity layer.

The choreography object itself is then interpreted through the broader choreography model,
including surfaces such as:

```text
entities
choreography_entity_map
choreography_hierarchy
choreography_relationships
```

Important consequence:

The calendar link row does not need to store choreography type directly.
It stores the choreography entity id and lets the choreography domain resolve what that entity is.

---

# Current Live Examples

Verified sample rows from `calendar_choreography_links` include patterns like:

```text
calendar_record_id = 1
link_type_id = clt_rehearses
choreography_entity_id = MEDLEY-001
scope_note = Primary segment focus of the event.
```

and:

```text
calendar_record_id = 1
link_type_id = clt_drills
choreography_entity_id = SEG-008
scope_note = Specific figure receiving extra attention.
```

This confirms that a single calendar record may carry multiple choreography links,
and that those links may point to choreography at different levels of specificity.

---

# What Counts As A Specific Choreography Element

Because the live link uses `choreography_entity_id`, the specific choreography element may be any choreography entity represented in the entity system.

Examples discussed in existing docs include:

```text
Medley
Routine
Segment
Figure
```

The calendar link table itself does not care which one it is.

It only stores:

```text
choreography_entity_id
```

So the semantic rule is:

```text
A calendar record can be linked to a specific choreography element
as long as that element has a choreography entity id.
```

---

# Common Queries

## Find choreography attached to a calendar record

```sql
SELECT
    ccl.*
FROM calendar_choreography_links ccl
WHERE ccl.calendar_record_id = :calendar_record_id;
```

---

## Find link semantics with labels

```sql
SELECT
    ccl.id,
    ccl.calendar_record_id,
    ccl.link_type_id,
    clt.code AS link_type_code,
    clt.label AS link_type_label,
    ccl.choreography_entity_id,
    ccl.scope_note
FROM calendar_choreography_links ccl
JOIN calendar_link_type_classvals clt
    ON clt.id = ccl.link_type_id
WHERE ccl.calendar_record_id = :calendar_record_id;
```

---

## Find all calendar records linked to a choreography entity

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
WHERE choreography_entity_id = 'SEG-008';
```

---

## Find choreography entity types for linked rows

```sql
SELECT
    ccl.id,
    ccl.calendar_record_id,
    ccl.choreography_entity_id,
    e.entity_type_id
FROM calendar_choreography_links ccl
JOIN entities e
    ON e.id = ccl.choreography_entity_id
WHERE ccl.calendar_record_id = :calendar_record_id;
```

This is the right pattern when you need to know whether a linked object is a segment, figure, routine, medley, or another choreography entity type.

---

# Business Semantics

Calendar choreography attachments represent event scope, rehearsal scope, or drill scope.

Examples:

```text
This event rehearses the full medley.
This event drills one specific segment.
This event focuses on a narrower choreography target inside a broader rehearsal block.
```

Multiple choreography rows may attach to the same calendar record.

This allows patterns such as:

```text
Medley + Segment
Routine + Figure
Segment + Figure
Multiple Figures
```

The calendar layer does not need to encode the choreography hierarchy itself.
It only stores the references.

---

# Object-Based Companion Table

A second live table also exists:

```text
calendar_choreography_object_links
```

Verified live columns include:

```text
id
calendar_record_id
link_type_id
choreography_object_id
scope_note
created_at
```

However, at the time of this documentation update, that table exists but is not populated in the live DB.

That means:

- `calendar_choreography_links` is the active live attachment surface
- `calendar_choreography_object_links` appears to be a newer or alternative object-based surface
- documentation about current event-to-choreography linking should primarily describe `calendar_choreography_links`
  unless and until the object-based table becomes the active write/read path

---

# Design Principle

The calendar module should not try to redefine choreography.

Its job is to say:

```text
this calendar record links to this choreography target
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

then the current live model already supports that through:

```text
calendar_records.id
    -> calendar_choreography_links.calendar_record_id
calendar_choreography_links.choreography_entity_id
    -> entities.id
```

So the main thing the documentation must keep clear is:

- the linked event is a `calendar_records` row
- the linked choreography target is currently a choreography entity id
- the active live link table is `calendar_choreography_links`
- link semantics are carried by `link_type_id`
- object-based choreography links exist in schema, but are not yet the populated live surface
