# How To Repair Calendar Events DB

This document describes the current minimal-semantic-core repair workflow for calendar events.

The system has intentionally removed most chronology-era residue.

Canonical chronology is now strictly:

```text
week.day.time.event
```

Example:

```text
3.1.1.1
```

Continuation semantics are NOT part of chronology.

---

# Canonical Ontology

```text
calendar_event
    -> calendar_event_passages
        -> prose_family
            -> prose_drafts
```

Current operational reality:

`calendar_event_passages` exists structurally but is not yet fully populated.

Current live semantic linkage still primarily relies on:

```text
calendar_event
    -> calendar_event_reference_labels
        -> prose_family
```

---

# Canonical Doctrine

## calendar_events

Responsible for:

- temporal semantic anchoring
- chronology coordinates
- stable event identity

Important fields:

| field       | meaning                                   |
|-------------|-------------------------------------------|
| id          | physical relational identity              |
| entity_id   | canonical semantic identity               |
| week_index  | chronology week                           |
| day_index   | chronology day                            |
| time_index  | chronology time slot                      |
| event_index | chronology event slot                     |
| prose_body  | lightweight semantic discoverability only |

---

## prose_body Doctrine

`calendar_events.prose_body` is NOT canonical prose storage.

It stores ONLY:

```text
prose_family:<id>
```

Example:

```text
prose_family:16
```

Do NOT store prose text inside `calendar_events.prose_body`.

---

# Current Surviving Event Topology

Canonical surviving rows:

| id | entity_id        | prose_body      |
|----|------------------|-----------------|
| 2  | calendar_event:2 | prose_family:11 |
| 3  | calendar_event:3 | prose_family:12 |
| 4  | calendar_event:4 | prose_family:13 |
| 5  | calendar_event:5 | prose_family:14 |
| 6  | calendar_event:6 | prose_family:15 |
| 7  | calendar_event:7 | prose_family:16 |

Rows `1` and `355` were intentionally deleted.

High-ID chronology residue was intentionally removed.

Current AUTO_INCREMENT target:

```sql
ALTER TABLE calendar_events AUTO_INCREMENT = 8;
```

---

# Phase 1 — Audit Existing Event State

## Inspect surviving events

```sql
SELECT
    id,
    entity_id,
    week_index,
    day_index,
    time_index,
    event_index,
    location_id,
    prose_body
FROM calendar_events
WHERE id BETWEEN 2 AND 7
ORDER BY
    week_index,
    day_index,
    time_index,
    event_index;
```

---

## Inspect prose-family linkage

```sql
SELECT
    cerl.calendar_event_id,
    cerl.prose_family_id,
    cerl.reference_label
FROM calendar_event_reference_labels cerl
WHERE cerl.calendar_event_id BETWEEN 2 AND 7
ORDER BY cerl.calendar_event_id;
```

---

# Phase 2 — Normalize Stable Location Semantics

Current doctrine:

`location_id` represents stable semantic environment.

NOT hyper-granular room topology.

Canonical normalization example:

```sql
UPDATE calendar_events
SET location_id = 'PLACE-013'
WHERE id IN (4,5,6,7);
```

---

# Phase 3 — Verify Prose Discoverability State

Validate that `prose_body` contains semantic references only.

```sql
SELECT
    id,
    entity_id,
    prose_body
FROM calendar_events
WHERE id BETWEEN 2 AND 7;
```

Expected pattern:

```text
prose_family:<id>
```

Example:

```text
prose_family:16
```

---

# Phase 4 — Verify Projection Attachments

The current runtime still depends on:

```text
calendar_event_projections
```

for operational projection resolution.

Both projection attachments may coexist legitimately.

Example:

| projection_type_id            | meaning                |
|-------------------------------|------------------------|
| projection_type_book          | export-capable         |
| projection_type_timeline_view | timeline visualization |

---

## Inspect projection attachments

```sql
SELECT
    cep.id,
    cep.calendar_event_id,
    cep.calendar_projection_id,
    cep.build_id,
    cp.projection_type_id
FROM calendar_event_projections cep
JOIN calendar_projections cp
    ON cp.id = cep.calendar_projection_id
WHERE cep.calendar_event_id = 7;
```

---

# Projection Resolution Doctrine

The runtime MUST resolve export-capable projections explicitly.

Do NOT select arbitrary attached projections.

Correct export-capable resolution query:

```sql
SELECT cep.*
FROM calendar_event_projections cep
JOIN calendar_projections cp
    ON cp.id = cep.calendar_projection_id
WHERE cep.calendar_event_id = 7
  AND cp.projection_type_id = 'projection_type_book'
LIMIT 1;
```

---

# Projection Build Doctrine

Current canonical surviving projection lineage:

| build_id | meaning                                |
|----------|----------------------------------------|
| 1        | canonical surviving projection lineage |

Obsolete chronology-era build lineage:

| build_id | status                |
|----------|-----------------------|
| 5        | intentionally deleted |

Cleanup performed:

```sql
DELETE FROM calendar_event_projections
WHERE build_id = 5;
```

Future canonical inserts should currently use:

```text
build_id = 1
```

---

# Phase 5 — Verify Chronology Semantics

Canonical chronology is now derived ONLY from:

```text
week_index
day_index
time_index
event_index
```

Verification query:

```sql
SELECT
    id,
    entity_id,
    CONCAT(
        week_index,
        '.',
        day_index,
        '.',
        time_index,
        '.',
        event_index
    ) AS chronology
FROM calendar_events
WHERE id BETWEEN 2 AND 7
ORDER BY
    week_index,
    day_index,
    time_index,
    event_index;
```

---

# Current Canonical Chronology

Expected current topology:

| entity_id        | chronology |
|------------------|------------|
| calendar_event:2 | 3.1.1.1    |
| calendar_event:3 | 3.1.1.2    |
| calendar_event:4 | 3.1.1.3    |
| calendar_event:5 | 3.1.1.4    |
| calendar_event:6 | 3.1.1.5    |
| calendar_event:7 | 3.1.1.6    |

---

# Important Operational Rules

## Do Not Reintroduce Chronology-Era Semantics

Do NOT:

- rebuild chronology trees
- encode continuation semantics into chronology
- treat chronology as narrative identity
- treat surviving temporal coordinates as sacred

Survivorship is semantic, not chronological.

---

## Do Not Store Prose in calendar_events

Invalid:

```text
calendar_events.prose_body = full prose text
```

Correct:

```text
calendar_events.prose_body = prose_family:<id>
```

---

## Do Not Assume Passage Migration Is Complete

Current live reality:

```text
calendar_event_passages
```

exists structurally but is not yet authoritative.

Current semantic linkage still primarily depends on:

```text
calendar_event_reference_labels
```

---

# Current Canonical Semantic Model

```text
calendar_event
    -> calendar_event_reference_labels
        -> prose_family
            -> prose_drafts
```

Transition target:

```text
calendar_event
    -> calendar_event_passages
        -> prose_family
            -> prose_drafts
```

---

# Runtime Workflow Responsibility

The prose attachment workflow should automatically populate:

```sql
UPDATE calendar_events
SET prose_body = CONCAT('prose_family:', :prose_family_id)
WHERE id = :calendar_event_id;
```

This occurs when prose becomes attached to the event.

---

# Final Doctrine

The system is now operating as a minimal semantic core.

Chronology is lightweight temporal positioning only.

Narrative continuity belongs to prose-family topology, not chronology.