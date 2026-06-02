# Calendar System - Day Layer
> See `calendar_invariants.md` for system-wide rules.

## Overview

This document describes the current state of Day chronology in Chrysalis.

The historical model stored Day containers as `calendar_layer_day` rows inside `calendar_events`.
That model is no longer the canonical chronology architecture.

The canonical Book chronology hierarchy is now:

```text
calendar_book_weeks
    -> calendar_book_days
        -> calendar_book_times
            -> calendar_events
```

Meaning:

- `calendar_book_weeks` owns Book week containers
- `calendar_book_days` owns Book day containers
- `calendar_book_times` owns Book time containers
- `calendar_events` owns narrative event rows

## Current Authority

For Book chronology, the canonical Day container is a row in:

```text
calendar_book_days
```

Its canonical containment is:

```text
calendar_book_days.week_id -> calendar_book_weeks.id
```

Its canonical locality is:

```text
projection_id + week_id + day_index
```

Day rows in `calendar_events` may still exist as legacy compatibility nodes, but they are not the target architecture.

## Canonical Day Semantics

A canonical Book day row separates chronology position from semantic weekday meaning.

Important fields:

```text
day_index
    = position within the Book week

day_of_week_id
    = semantic weekday identity

entity_id
    = canonical external identity
```

Examples:

```text
calendar_book_day:projection=1:week=3:day=1
calendar_book_day:projection=1:week=3:day=2
```

## Uniqueness

The old Day-layer document described uniqueness through generated columns on `calendar_events`:

- `day_unique_parent_id`
- `day_unique_index`
- `day_layer_unique_key`

That is historical schema guidance, not current architectural authority.

Current runtime doctrine has moved to:

```text
calendar_book_days
    unique by Book containment locality

calendar_events
    unique by structural identity
    projection + parent + layer + sequence_index
```

For legacy `calendar_events` nodes, structural identity is enforced through the shared calendar node ensure path, not Day-specific generated columns.

## Legacy Compatibility Surface

Some runtime surfaces still accept or read legacy `calendar_layer_day` identities from `calendar_events`.

Those compatibility paths exist to resolve older Day references into canonical Book chronology.

Important rule:

```text
legacy calendar_layer_day rows are compatibility bridges
not the canonical Day storage model
```

When compatibility code resolves a legacy Day node, it derives canonical Book locality from the legacy row and then resolves the corresponding `calendar_book_days` row.

## Historical Note

The previous model treated Day containers as second-layer `calendar_events` rows and documented generated-column uniqueness such as:

```text
(parent_event_id, day_index)
```

That guidance pre-dates the Book chronology split and should not be used as the source of truth for new schema or runtime work.

In particular, the following are now historical residues rather than architectural requirements:

- generated Day-only uniqueness columns on `calendar_events`
- Day-only unique keys built from those generated columns
- treating `calendar_layer_day` rows as the canonical Day container model

## Design Rule

For new work:

```text
Book chronology containers belong in:
- calendar_book_weeks
- calendar_book_days
- calendar_book_times

Narrative events belong in:
- calendar_events
```

If a code path still depends on `calendar_layer_day`, it should be treated as compatibility or migration residue unless it is explicitly required for legacy read/write support.

## Practical Guidance

Use `calendar_book_days` when the task is about:

- creating a Book day
- locating a Book day within a week
- attaching canonical times beneath a day
- enforcing canonical Book chronology containment

Use legacy `calendar_layer_day` rows only when:

- resolving older identities still emitted by compatibility surfaces
- mapping historical `calendar_events` chronology references into canonical Book rows

## Status

- canonical Day chronology lives in `calendar_book_days`
- canonical containment is `calendar_book_days.week_id`
- Book chronology no longer requires Day containers to live inside `calendar_events`
- Day-specific generated-column uniqueness on `calendar_events` is historical guidance, not current architecture
