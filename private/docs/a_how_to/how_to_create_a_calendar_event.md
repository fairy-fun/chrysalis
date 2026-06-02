# How To Create A Calendar Event

This document explains the purpose of each column on `calendar_events`, when it should be populated, and where the value should come from.

---

## iso_date

### Purpose

`iso_date` is the canonical ISO-8601 calendar date for the event.

Example:

```text
2025-01-20
```

Type:

DATE
Doctrine

iso_date is not authorial input.

It is derived chronology.

Authors should establish chronology through the calendar hierarchy:

Book Week
→ Book Day
→ Book Time
→ Event

or through explicit real-date assignment:

real_date_start_id
real_date_end_id

The system then derives:

iso_date

from the resolved calendar date.

Source Of Truth

Authoritative source:

real_date_start_id
→ dates.id
→ dates.date_value

Example:

real_date_start_id = date_2025_01_20

resolves to:

iso_date = 2025-01-20
Book Chronology

For Book events:

calendar_book_week
→ calendar_book_day
→ calendar_book_time
→ calendar_event

the Book containers should carry the date assignment.

Example:

Week 1 Day 1 = 2025-01-19
Week 1 Day 2 = 2025-01-20

Events attached to Day 2 should ultimately resolve to:

iso_date = 2025-01-20
When To Populate

Populate when:

the event has a known chronology date
the event has a real_date_start_id
the Book chronology has been anchored to a real date

Leave NULL when:

the event is intentionally undated
chronology has not yet been assigned
Validation

If:

real_date_start_id IS NOT NULL

then:

iso_date

should normally also be populated.

The following query should return zero rows:

SELECT
id,
entity_id
FROM calendar_events
WHERE real_date_start_id IS NOT NULL
AND iso_date IS NULL;
Anti-Patterns

Do not manually invent dates.

Do not assign dates that disagree with:

real_date_start_id

Do not treat iso_date as an independent source of truth.

iso_date is a chronology projection, not an authorial field.


This captures the doctrine you just established: `iso_date` should be derived from c