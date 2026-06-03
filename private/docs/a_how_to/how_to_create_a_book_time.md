# how_to_create_a_book_time

## Purpose

Create or retrieve a canonical Book Time under an existing Book Day.

This document is derived directly from:

```text
public_html/pecherie/chill-api/calendar/create_calendar_time.php
private/framework/calendar/admin/calendar_book_chronology_materializer.php
```

---

## Required Inputs

The API requires:

```text
parent_day_entity_id
time_index
time_label_id
```

Example:

```json
{
  "parent_day_entity_id": "calendar_book_day:projection=1:week=2:day=4",
  "time_index": 3,
  "time_label_id": "CLASSVAL-TIME-002"
}
```

---

## Valid Time Labels

The supplied:

```text
time_label_id
```

must exist in:

```text
calendar_time_label_classvals
```
```sql
SELECT id, label
FROM calendar_time_label_classvals
ORDER BY sort_order, id;
```
Current examples include:

```text
Pre-dawn
Early Morning
Morning
Lunch
Afternoon
Evening
Night
Late Night
```

---

## Execution Flow

```text
validate_parent_day_entity_id
        ↓
validate_time_index
        ↓
validate_time_label_id
        ↓
resolve_book_day_for_calendar_time_creation
        ↓
materialize_calendar_book_time
        ↓
update_time_label_and_summary
        ↓
reload_calendar_book_time_response
        ↓
return_response
```

---

## Step 1 — Validate Parent Day

Input:

```text
parent_day_entity_id
```

Validation:

```text
must be a non-empty string
```

Failure:

```text
parent_day_entity_id required
```

---

## Step 2 — Validate Time Index

Input:

```text
time_index
```

Validation:

```text
must be a positive integer
```

Failure:

```text
time_index must be positive integer
```

---

## Step 3 — Validate Time Label

Input:

```text
time_label_id
```

Validation:

```text
must be a non-empty string
must exist in calendar_time_label_classvals
```

Failure:

```text
time_label_id required
Invalid time_label_id
```

---

## Step 4 — Resolve Canonical Book Day

Operation:

```text
resolve_book_day_for_calendar_time_creation
```

Purpose:

Resolve the supplied day entity into a canonical:

```text
calendar_book_days
```

record.

Produces a canonical calendar_book_days record including:

```text
id
projection_id
week_id
week_index
day_index
entity_id
```
The operation may resolve:

```text
canonical Book Day entities
```

or:

```text
legacy calendar day entities
```

before returning the canonical Book Day.

---

## Step 5 — Ensure Book Time Exists

Operation:

```text
materialize_calendar_book_time
```

Inputs:

```text
projection_id
day_id
time_index
```

Before inserting, the implementation checks:

```sql
SELECT *
FROM calendar_book_times
WHERE projection_id = :projection_id
  AND day_id = :day_id
  AND time_index = :time_index
LIMIT 1
```

If a matching row already exists:

```text
return existing row
```

No duplicate Book Time is created.

If no row exists:

```text
insert new calendar_book_times row
```

---

## Step 6 — Generate Canonical Entity Identity

New Book Times receive a canonical entity identity:

```text
calendar_book_time:
    projection={projection_id}:
    week={week_index}:
    day={day_index}:
    time={time_index}
```

Example:

```text
calendar_book_time:projection=1:week=2:day=4:time=3
```

The implementation also ensures an entity row exists using:

```text
entity_type_calendar_time
```

---

## Step 7 — Apply Time Label

After the Book Time exists, the implementation updates:

```text
calendar_book_times
```

setting:

```text
time_label_id
summary
```

The summary is automatically derived from the classval label.

Example:

```text
CLASSVAL-TIME-002
        ↓
Morning
```

Result:

```text
summary = 'Morning'
```

---

## Step 8 — Reload Response

Operation:

```text
reload_calendar_book_time_response
```

Returns:

```text
id
entity_id
projection_entity_id
projection_id
day_id
time_index
time_label_id
time_label_code
time_label
summary
notes
created_at
updated_at
```

---

## What Gets Created

A row in the table:

```text
calendar_book_times
```

containing:

```text
projection_id
day_id
time_index
entity_id
time_label_id
summary
```

If the Book Time already exists, the existing row is reused and updated.

---

## Important Behavior

Book Time creation is effectively:

```text
ensure_book_time_exists
```

rather than:

```text
always_create_book_time
```

Uniqueness is determined by:

```text
projection_id
day_id
time_index
```

What the code actually guarantees is:
```sql
SELECT *
FROM calendar_book_times
WHERE projection_id = :projection_id
AND day_id = :day_id
AND time_index = :time_index
LIMIT 1
```
followed by insert-if-missing.

The materializer treats
(projection_id, day_id, time_index)
as the canonical identity of a Book Time and reuses an existing row when one is found.
