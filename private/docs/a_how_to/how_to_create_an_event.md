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

After creation, the workflow hands off to beat design guidance for the new event.

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
Tuesday
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
Time 1
1
```

Stores:

```text
input.time_index
```

The current normalizer accepts numeric time-slot input such as `1` or `Time 1`.
It does not currently normalize semantic labels such as `Morning`.

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

`calendar_book_weeks`


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


`calendar_book_days`


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


`calendar_book_times`


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

## Step 10A — Attach Projection Memberships

The event's canonical source projection is:

```text
projection_id
```

used during event creation.

Some events must also participate in additional projections.

For example:

```text
Projection 1
    Icehearts
    (Book)

Projection 5
    Real-Time Continuity
    (Timeline View)
```

In these cases, create projection memberships using:

```text
calendar_event_projection_membership
```

The event should remain canonically localized in the Book projection while also being attached to any required downstream projections.

Example:

```text
calendar_event_id
        ↓
Projection 1
Projection 5
```

This allows the event to appear in both chronology views.


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

The live driver also emits a handoff packet whose intent is:

```text
beat_generation_author_handoff
```

and whose guidance explicitly says beat design precedes prose authoring.

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
1. used for beat design handoff
2. assigned prose
3. processed for beat/title derivation
4. processed into subevent structure if needed
5. tagged with characters
6. tagged with locations
```

---

## Recommended Follow-On Workflow

Immediately after event creation, follow the emitted author handoff and begin with beat design for the existing event.

When prose is ready to attach:

```text
calendar_event_add_prose
```

Attach prose to the newly created event.

After prose exists, there are two distinct repo-supported next steps depending on intent.

For event-level beat/title derivation and ontology persistence:

```text
calendar_event_title_narrative_ontology
```

For structural processing of attached prose into subevents / beat structure:

```text
calendar_event_process_attached_prose
```

---

## Canonical Sequence

```text
calendar_book_event_create
        ↓
beat generation author handoff
        ↓
calendar_event_add_prose
        ↓
calendar_event_title_narrative_ontology
        ↓
calendar_event_suggest_characters
        ↓
calendar_event_suggest_locations
```

Optional structural branch after prose attachment:

```text
calendar_event_process_attached_prose
```

Use that workflow when the goal is to segment or orchestrate attached prose into subevent structure, rather than only derive event-level beat/title metadata.
