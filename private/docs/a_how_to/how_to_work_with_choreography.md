# Calendar Choreography Links

## Purpose

`calendar_choreography_links` attaches choreography entities to calendar events.

A calendar event may independently reference any choreography entity that can be rehearsed, reviewed, taught, drilled, or discussed.

Examples:

```text
Rehearse a Routine
Rehearse a Segment
Drill a Figure
Review choreography
```

The table does not care what choreography type is being linked.

It only stores the entity identifier.

---

# Table Structure

```text
calendar_choreography_links
───────────────────────────

id
calendar_record_id
link_type_id
choreography_entity_id
scope_note
created_at
```

---

# Identity Model

The authoritative choreography identifier is:

```text
choreography_entity_id
```

which references:

```text
entities.id
```

Examples:

```text
MEDLEY-001
ROUTINE-001
SEG-008
FIG-014
```

Object type is determined through the entity system:

```text
entities.entity_type_id
```

Examples:

```text
entity_type_routine
entity_type_segment
entity_type_figure
entity_type_medley
```

Calendar links never need to store the choreography type directly.

---

# Common Queries

## Find choreography attached to a calendar event

```sql
SELECT
    ccl.*
FROM calendar_choreography_links ccl
WHERE ccl.calendar_record_id = :calendar_record_id;
```

---

## Determine entity types for linked choreography

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

Example result:

```text
SEG-008      entity_type_segment
FIG-014      entity_type_figure
ROUTINE-001  entity_type_routine
```

---

## Find all calendar events linked to a choreography entity

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

## Find all events linked to a Segment

```sql
SELECT
    ccl.*,
    e.entity_type_id
FROM calendar_choreography_links ccl
JOIN entities e
    ON e.id = ccl.choreography_entity_id
WHERE e.entity_type_id = 'entity_type_segment';
```

---

## Find all events linked to a Figure

```sql
SELECT
    ccl.*,
    e.entity_type_id
FROM calendar_choreography_links ccl
JOIN entities e
    ON e.id = ccl.choreography_entity_id
WHERE e.entity_type_id = 'entity_type_figure';
```

---

## Find all choreography linked to a calendar event with names

The entity type determines which domain table contains descriptive data.

Typical workflow:

```sql
SELECT
    ccl.choreography_entity_id,
    e.entity_type_id
FROM calendar_choreography_links ccl
JOIN entities e
    ON e.id = ccl.choreography_entity_id
WHERE ccl.calendar_record_id = :calendar_record_id;
```

Then load the appropriate object based on entity type.

Examples:

```text
entity_type_routine
    → performance_routines

entity_type_segment
    → segments

entity_type_figure
    → figures
```

---

# Business Semantics

Calendar attachments represent rehearsal or instructional scope.

Examples:

```text
Entire Routine
Specific Segment
Individual Figure
```

Multiple choreography entities may be attached to the same calendar event.

Examples:

```text
Routine + Segment

Segment + Figure

Multiple Figures
```

No hierarchy assumptions are required by the calendar system.

The calendar simply references choreography entities.

---

# Design Principle

The calendar module does not determine choreography type.

The calendar module only stores:

```text
choreography_entity_id
```

The choreography domain determines:

```text
what the entity is
how it behaves
how it is displayed
how it relates to other choreography entities
```

through the entity registry and choreography relationship model.
