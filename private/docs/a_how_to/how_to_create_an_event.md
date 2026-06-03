# how_to_create_an_event

## Workflow

The public workflow:

```text
calendar_event_create
```

delegates directly to:

```text
calendar_book_event_create
```

and currently supports:

```text
projection_type_book
```

only.

---

## Purpose

Create a new calendar event shell in canonical Book chronology.

The workflow does **not** create prose.

The workflow does **not** derive beats.

The workflow creates the calendar event record and places it in the correct:

```text
Book
  → Week
  → Day
  → Time
```

location.

---

## State Machine

```text
await_projection_id
        │
        ▼
await_week_index
        │
        ▼
await_day_index
        │
        ▼
await_time_index
        │
        ▼
normalize_book_event_input
        │
        ▼
route_projection_ontology
        │
        ▼
validate_book_week
        │
        ▼
validate_book_day
        │
        ▼
validate_book_time
        │
        ▼
create_book_calendar_event
        │
        ▼
terminal_event_created
```

---

## Step 1 — Choose Book

State:

```text
await_projection_id
```

Prompt:

```text
Which book should this event belong to?
```

Examples:

```text
Book 1
BOOK-001
```

Stores:

```text
input.projection_id
```

---

## Step 2 — Choose Week

State:

```text
await_week_index
```

Prompt:

```text
Which week should this event belong to?
```

Examples:

```text
Week 1
1
```

Stores:

```text
input.week_index
```

---

## Step 3 — Choose Day

State:

```text
await_day_index
```

Prompt:

```text
Which day should this event belong to?
```

Examples:

```text
Day 2
2
```

Stores:

```text
input.day_index
```

---

## Step 4 — Choose Time Slot

State:

```text
await_time_index
```

Prompt:

```text
Which time slot should this event belong to?
```

Examples:

```text
Morning
Time 1
1
```

Stores:

```text
input.time_index
```

---

## Step 5 — Normalize Input

State:

```text
normalize_book_event_input
```

Operation:

```text
normalize_book_event_input
```

Purpose:

Convert labels and shorthand values into canonical IDs and indexes.

Produces:

```text
context.calendar_normalized_input
```

containing normalized:

```text
projection_id
week_index
day_index
time_index
```

---

## Step 6 — Validate Projection Type

State:

```text
route_projection_ontology
```

Assertion:

```text
projection_type_id
    ==
projection_type_book
```

If not:

```text
terminal_unsupported_projection_type
```

---

## Step 7 — Validate Week

State:

```text
validate_book_week
```

Checks:

```sql
calendar_book_weeks
```

using:

```text
projection_id
week_index
```

Produces:

```text
context.book_week
```

---

## Step 8 — Validate Day

State:

```text
validate_book_day
```

Checks:

```sql
calendar_book_days
```

using:

```text
projection_id
week_id
day_index
```

Produces:

```text
context.book_day
```

---

## Step 9 — Validate Time

State:

```text
validate_book_time
```

Checks:

```sql
calendar_book_times
```

using:

```text
projection_id
day_id
time_index
```

Produces:

```text
context.book_time
```

---

## Step 10 — Create Event

State:

```text
create_book_calendar_event
```

Operation:

```text
create_book_event
```

Payload:

```php
[
    'projection_id'
        => context.calendar_normalized_input.projection_id,

    'book_time_id'
        => context.book_time.id,

    'event_index'
        => '',

    'summary'
        => '[Beat pending]',
]
```

The workflow intentionally creates a shell event.

Initial summary:

```text
[Beat pending]
```

---

## Result

Success terminal:

```text
terminal_event_created
```

Message:

```text
Calendar event shell created successfully.
Continue with beat generation for the attached author handoff.
```

---

## What Gets Created

The workflow creates a new row in:

```text
calendar_events
```

attached to:

```text
projection_id
book_time_id
```

with a placeholder summary.

The event now exists in chronology and can be:

```text
1. assigned prose
2. processed into beat/title metadata
3. tagged with characters
4. tagged with locations
5. linked to ontology
```

---

## Recommended Follow-On Workflow

After event creation:

```text
calendar_event_add_prose
```

Attach prose to the newly created event.

After prose exists:

```text
calendar_event_process_attached_prose
```

to derive metadata and ontology from the prose.

---

## Canonical Sequence

```text
calendar_book_event_create
        ↓
calendar_event_add_prose
        ↓
calendar_event_process_attached_prose
        ↓
beat/title derivation
        ↓
character suggestions
        ↓
location suggestions
        ↓
ontology persistence
```
