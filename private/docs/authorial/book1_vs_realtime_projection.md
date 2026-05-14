# Book 1 Projection vs Real-Time Projection

## Canonical Runtime Distinction

The Chrysalis chronology system supports multiple projection contexts over the same canonical chronology spine.

Two projections are operationally important and intentionally distinct:

| Projection | Projection ID | Projection Code | Purpose |
|---|---:|---|---|
| Book 1 Projection | 1 | `book_projection_BOOK-001` | Canonical published narrative ordering |
| Real-Time Projection | 5 | `realtime_projection_main` | Real-world continuity / simulation chronology |

These projections are NOT interchangeable.

---

# Book 1 Projection

## Canonical Identity

```text
projection_id      = 1
projection_code    = book_projection_BOOK-001
projection_type_id = projection_type_book
```

## Purpose

The Book 1 projection exists to represent:

- canonical published narrative sequence
- publication ordering
- reader-facing prose continuity
- editorial narrative flow

This projection is intended for:

- book rendering
- prose export
- publication pipelines
- chapter continuity
- narrative reading order

---

## Book 1 Traversal Modes

The Book 1 projection supports TWO human-readable traversals.

---

### 1. Calendar View Traversal

This traversal combines:

| Table | Responsibility |
|---|---|
| `calendar_records` | chronology traversal structure |
| `calendar_events` | executable narrative content |

This produces a chronology-oriented reader view.

Example shape:

```text
Week 1
  Day 3
    Morning
      Shay arrives at the station.
      Shay meets Elias.
```

Structural layers originate from:

```text
calendar_layer_week
calendar_layer_day
calendar_layer_time
```

Executable layers originate from:

```text
calendar_layer_event
calendar_layer_subevent
```

The chronology hierarchy is authoritative.

Summaries are presentation only.

---

### 2. Published Prose Traversal

This traversal uses ONLY:

```text
calendar_events
```

restricted to:

```text
projection_id = 1
```

and executable layers only.

Structural traversal rows are intentionally excluded.

This produces canonical publication order suitable for:

- novel rendering
- prose export
- ebook generation
- chapter assembly
- reading continuity

Ordering authority is still derived from canonical chronology indexes:

```text
week.day.time.event.subevent
```

---

# Real-Time Projection

## Canonical Identity

```text
projection_id      = 5
projection_code    = realtime_projection_main
projection_type_id = projection_type_timeline_view
```

## Purpose

The real-time projection exists for:

- continuity management
- world-state inspection
- simulation chronology
- deterministic temporal sequencing
- operational timeline traversal

This projection is NOT a publication projection.

It is allowed to contain chronology events that are:

- off-page
- transitional
- continuity-only
- invisible to published prose
- editorially excluded from Book 1

Example:

```text
calendar_event:4
"Shay sleeps off her jet lag at the hotel."
```

was migrated from Book 1 into the real-time projection because it is continuity-valid but not canonical published prose.

---

# Canonical Separation Doctrine

## Structural Chronology

Canonical chronology structure lives in:

```text
calendar_records
```

ONLY.

Structural layers:

```text
calendar_layer_week
calendar_layer_day
calendar_layer_time
```

---

## Executable Narrative

Executable narrative nodes live in:

```text
calendar_events
```

ONLY.

Executable layers:

```text
calendar_layer_event
calendar_layer_subevent
```

---

# Canonical Authority Rules

## Chronology Authority

Chronology authority derives ONLY from structural indexes:

```text
week_index
day_index
time_index
event_index
subevent_index
```

Canonical chronology addresses are derived:

```text
week.day.time.event.subevent
```

Examples:

```text
1
1.3
1.3.1
1.3.1.4
1.3.1.4.2
```

---

## Semantic Authority

Semantic authority derives from:

```text
class_type_id
time_label_id
```

NOT from summaries.

---

# Runtime Intent

The system intentionally supports:

| Traversal | Uses Structure | Uses Events | Uses Prose |
|---|---|---|---|
| Calendar View | Yes | Yes | Optional |
| Published Prose | No | Yes | Yes |
| Real-Time Continuity | Optional | Yes | Optional |

This separation is canonical and should be preserved during future migrations and CI enforcement.
