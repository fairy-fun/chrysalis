# Chrysalis Calendar Structural Refactor Notes

## Confirmed Architectural Direction

The old polymorphic model:

- `calendar_events`
    - week rows
    - day rows
    - time rows
    - event rows
    - subevent rows

was identified as structurally incorrect.

Weeks, days, and times are not narrative events.

They are chronology containers.

---

## Correct Structural Separation

The canonical hierarchy is now:

```text
calendar_book_weeks
    → calendar_book_days
        → calendar_book_times
            → calendar_events
                → calendar_subevents
```

Meaning:

- weeks = Book chronology containers
- days = Book chronology containers
- times = Book chronology containers
- events = narrative containers
- subevents = narrative refinements

---

## Projection Scope

Book chronology containers are projection-scoped.

Important distinction:

- `projection_type_book`
    - uses week/day/time chronology structure
- `projection_type_timeline_view`
    - does NOT use Book chronology containers

Therefore:

```text
calendar_book_weeks
calendar_book_days
calendar_book_times
```

must NEVER be treated as global chronology tables.

They are Book-projection chronology structures.

---

## calendar_book_weeks Semantics

The table now represents Book-local chronology.

### Identity Semantics

```text
id
    = storage identity

week_index
    = Book-local chronology position

entity_id
    = semantic identity
```

### Correct Identity Shape

Example:

```text
calendar_book_week:projection=1:week=1
```

### Week Index Rules

`week_index` is:

- 1-based
- projection-local
- dense chronology order

Examples:

```text
Book 1:
1
2
3

Book 2:
1
2
3
```

NOT globally increasing.

---

## Current Table Constraints

`calendar_book_weeks` currently uses:

```text
PRIMARY(id)

UNIQUE(entity_id)

UNIQUE(projection_id, week_index)
```

Meaning:

```text
projection_id + week_index
```

is the canonical Book chronology coordinate.

---

## Confirmed Materializer Behavior

`materialize_calendar_chronology.php`

does NOT require week/day/time rows to live inside `calendar_events`.

The procedure only materializes:

```text
event → subevent chronology inheritance
```

through chronology metadata fields:

- week_index
- day_index
- time_index
- event_index
- chronology_address

This means chronology synthesis can remain intact while structural tables are separated cleanly.

---

## Important Migration Principle

Do NOT rebuild structure from surviving parent_event_id relationships.

Those relationships are contaminated by the earlier polymorphic container system.

Instead:

```text
events
    → derive chronology tuples
        → synthesize canonical Book containers
```

---

## Current Migration Direction

1. Create Book chronology tables
2. Populate canonical Book week rows
3. Populate Book day rows
4. Populate Book time rows
5. Attach narrative events to Book times
6. Preserve chronology materialization boundary
7. Rebuild projections after graph stabilization

---

## Important Non-Goals

Do NOT:

- restore week/day/time rows inside `calendar_events`
- rebuild chronology dynamically during reads
- regress projection-backed architecture
- treat projection 5 as Book chronology
- conflate narrative events with chronology containers

# Chrysalis Calendar Book Day Structure Notes

## Core Architectural Principle

`calendar_book_days` separates:

- chronology position
- semantic weekday meaning

These are NOT the same concept.

---

## Correct Day Model

The table should contain BOTH:

```text
day_index
```

and:

```text
day_of_week_id
```

### day_index

Represents:

```text
position within the Book week
```

Examples:

```text
1
2
3
4
5
6
7
```

This is chronology ordering only.

---

### day_of_week_id

Represents:

```text
canonical semantic weekday identity
```

Examples:

```text
dow_sunday
dow_monday
dow_tuesday
dow_wednesday
dow_thursday
dow_friday
dow_saturday
```

This is semantic weekday meaning.

---

## Existing Canonical Weekday System

The database already contains:

```text
day_of_week_classvals
```

This is the canonical weekday abstraction layer.

Current rows include:

```text
dow_sunday
dow_monday
dow_tuesday
dow_wednesday
dow_thursday
dow_friday
dow_saturday
```

with additional metadata:

- ISO weekday numbering
- calendar weekday numbering
- labels
- codes

This means weekday semantics are already centralized and modernized.

---

## Important Architectural Distinction

These are DIFFERENT:

```text
day_index = 2
```

and:

```text
day_of_week_id = dow_monday
```

Meaning:

```text
Book Week position
```

is not the same thing as:

```text
weekday semantic identity
```

This prevents chronology ordering from becoming tightly coupled to weekday naming.

---

## Required calendar_book_days Update

Because `calendar_book_days` was empty, the semantic weekday field could be added cleanly.

### Add Semantic Weekday Field

```sql
ALTER TABLE calendar_book_days
ADD COLUMN day_of_week_id VARCHAR(64) NOT NULL
AFTER day_index;
```

---

### Add Referential Constraint

```sql
ALTER TABLE calendar_book_days
ADD CONSTRAINT fk_calendar_book_days_day_of_week
FOREIGN KEY (day_of_week_id)
REFERENCES day_of_week_classvals(id);
```

---

## Example Insert

```sql
INSERT INTO calendar_book_days (
    projection_id,
    week_id,
    day_index,
    day_of_week_id,
    entity_id,
    summary
)
VALUES (
    1,
    1,
    1,
    'dow_sunday',
    'calendar_book_day:projection=1:week=1:day=1',
    'Sunday'
);
```

Meaning:

```text
Book 1
Week 1
Day position 1
Semantic weekday Sunday
```

without hardcoded weekday systems.

---

## Future-Proofing Benefit

Because semantic weekdays are centralized in:

```text
day_of_week_classvals
```

future chronology systems can support:

- ISO calendars
- localized calendars
- narrative calendars
- alternate chronology systems
- custom ordering systems

without rewriting Book chronology structure tables.


