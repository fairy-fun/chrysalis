# Book 1 Chronology → Time Mapping

## Purpose

Define how Book 1 structural chronology (`chronology_address`) maps to real calendar time.

This mapping enables dual projection:

* `book1` → narrative structure
* `time` → real-world chronology

---

## Core Principle

> Chronology is not inherently temporal.
> It becomes temporal only within a projection that defines an anchor.

---

## Scope

This mapping applies **only** to:

```text
projection_code = book_projection_BOOK-001
```

It MUST NOT be applied globally to all projections.

---

## Anchor Definition

Book 1 defines a fixed temporal anchor:

```text
chronology_address = 1
= Week 1

Week 1 start date = Sunday, 2025-01-19
```

---

## Address Structure

```text
W.D.T...

W = week index (1-based)
D = day index within week (1–7)
T = timeblock within day (1+)
```

Examples:

```text
1       → Week 1
1.1     → Week 1, Day 1 (Sunday)
1.1.1   → Week 1, Day 1, Timeblock 1
1.2     → Week 1, Day 2 (Monday)
```

---

## Deterministic Mapping Rule

For any Book 1 event with `chronology_address = W.D...`:

```text
date =
  2025-01-19
  + (W - 1) * 7 days
  + (D - 1) days
```

Timeblocks (`T`) do not affect the date.

---

## Examples

```text
1.1     → 2025-01-19 (Sunday)
1.2     → 2025-01-20 (Monday)
1.3     → 2025-01-21 (Tuesday)

2.1     → 2025-01-26 (Sunday)
2.3     → 2025-01-28 (Tuesday)
```

---

## Invariants

### 1. Projection Scope

```text
Mapping applies ONLY to Book 1 projection
```

---

### 2. Address Requirement

```text
chronology_address must include at least W.D
```

Events without day-level address cannot be mapped deterministically.

---

### 3. Anchor Consistency

```text
chronology_address = 1
MUST correspond to 2025-01-19
```

---

### 4. No Temporal Leakage

```text
book1 projection MUST NOT store projection_starts_at
```

Time is derived in the `time` projection only.

---

## Integration with Materializer

Two valid approaches:

### A. Source-driven (current system)

```text
calendar_events.real_date_start_id is source of truth
```

* Mapping acts as validation / fallback
* Detects inconsistencies

---

### B. Derived (future option)

```text
time projection derives dates from chronology_address
```

* Removes need for stored date IDs
* Guarantees consistency
* Requires strict address discipline

---

## Validation Queries

Detect mismatch between structure and time:

```sql
SELECT *
FROM (
         SELECT
             e.id,
             e.chronology_address,
             d.date_value AS stored_date,
             DATE_ADD(
                     '2025-01-19',
                     INTERVAL (
                         (CAST(SUBSTRING_INDEX(e.chronology_address, '.', 1) AS UNSIGNED) - 1) * 7
                             +
                         (CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(e.chronology_address, '.', 2), '.', -1) AS UNSIGNED) - 1)
                         ) DAY
             ) AS derived_date
         FROM calendar_events e
                  JOIN dates d
                       ON d.id = e.real_date_start_id
                  JOIN calendar_projections p
                       ON p.entity_id = e.projection_entity_id
         WHERE p.projection_code = 'book_projection_BOOK-001'
           AND e.chronology_address REGEXP '^[0-9]+\\.[0-9]+(\\.|$)'
     ) q
WHERE q.stored_date <> q.derived_date;
```

Validation queries must not rely on HAVING for non-aggregate comparisons.
Compute derived_date in a subquery, then compare stored_date and derived_date in WHERE.

---

```sql
SELECT
    e.id,
    e.chronology_address,
    e.week_index,
    e.day_index
FROM calendar_events e
         JOIN calendar_projections p
              ON p.entity_id = e.projection_entity_id
WHERE p.projection_code = 'book_projection_BOOK-001'
  AND e.week_index IS NOT NULL
  AND e.day_index IS NOT NULL
  AND e.real_date_start_id IS NULL;
```

A Book 1 calendar event is eligible for date derivation when it belongs to
book_projection_BOOK-001 and has both week_index and day_index populated.

String-form chronology_address is descriptive. Enforcement should prefer
the normalized week_index/day_index fields.

---

## Design Implication

Book 1 defines a **coordinate transformation**:

```text
(Book structure) → (Real time)
```

This is:

* deterministic
* projection-scoped
* anchored
* non-global

---

## Future Extensions

Each projection may define its own anchor:

```text
Book 2 → different start date
Alternate timeline → different calendar system
```

No assumption of shared temporal alignment across projections.

---
