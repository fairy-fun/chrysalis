# Calendar Event Passage Doctrine

## Purpose

This document formalizes the extraction of narrative continuation semantics out of chronology addressing and into a dedicated relational structure.

The chronology archaeology work revealed that chronology suffixes were carrying two distinct meanings simultaneously:

1. temporal placement
2. narrative/prose continuation

This document establishes the new canonical separation.

---

# Canonical Temporal Doctrine

`calendar_events` is now strictly responsible for temporal identity and chronology placement.

Canonical chronology structure:

```text
week.day.time.event
```

Example:

```text
3.1.1.1
```

Meaning:

| component | meaning |
|---|---|
| 3 | week_index |
| 1 | day_index |
| 1 | time_index |
| 1 | event_index |

Chronology beyond the fourth component is no longer interpreted as temporal hierarchy.

---

# Passage Doctrine

Narrative continuation and prose subdivision are now represented through:

```text
calendar_event_passages
```

A calendar event acts as a temporal container.

Passages act as ordered narrative units attached to that container.

---

# Passage Chronology Interpretation

Legacy chronology structures such as:

```text
3.1.1.1.1
3.1.1.1.2
3.1.1.1.3
3.1.1.1.4
```

are now interpreted as:

| chronology | meaning |
|---|---|
| 3.1.1.1 | temporal event identity |
| 3.1.1.1.1 | passage 1 |
| 3.1.1.1.2 | passage 2 |
| 3.1.1.1.3 | passage 3 |
| 3.1.1.1.4 | passage 4 |

This preserves the historical chronology archaeology while separating prose structure from temporal structure.

---

# Table Definition

```sql
CREATE TABLE calendar_event_passages
(
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    entity_id VARCHAR(64) NOT NULL,

    calendar_event_id BIGINT NOT NULL,

    passage_index INT NOT NULL,

    prose_family_id BIGINT NULL,

    source_calendar_event_id BIGINT NULL,

    created_at DATETIME NULL,
    updated_at DATETIME NULL,

    CONSTRAINT fk_calendar_event_passages_event
        FOREIGN KEY (calendar_event_id)
        REFERENCES calendar_events(id),

    UNIQUE KEY uniq_event_passage
        (calendar_event_id, passage_index),

    UNIQUE KEY uniq_passage_entity
        (entity_id)
);
```

---

# Important Semantic Clarification

The calendar event itself is not considered a prose-bearing unit.

Instead:

```text
calendar event = temporal container
passage = prose-bearing narrative unit
```

This creates a uniform structure for:

- rendering
- continuation
- editing
- regeneration
- indexing
- migration
- prose attachment

---

# Migration Doctrine

Existing chronology lineage rows should migrate according to:

| old chronology | migrated structure |
|---|---|
| 3.1.1.1.1 | event + passage_index = 1 |
| 3.1.1.1.2 | event + passage_index = 2 |
| 3.1.1.1.3 | event + passage_index = 3 |
| 3.1.1.1.4 | event + passage_index = 4 |

The field:

```text
source_calendar_event_id
```

exists to preserve archaeological traceability during migration.

---

# Cleanup Doctrine

Chronology cleanup rules now become significantly safer.

Rows ending in:

```text
.1
```

must no longer be treated as automatic duplicate artifacts.

A fifth chronology component may now represent:

```text
passage_index
```

rather than duplicate chronology corruption.

Duplicate detection must therefore distinguish between:

- stale duplicate artifacts
- legitimate passage lineage

---

# Long-Term Architectural Goal

Chronology should stabilize into a purely temporal namespace.

Narrative topology should evolve independently through:

```text
calendar_event_passages
```

This separation prevents chronology from carrying multiplexed semantic responsibilities and dramatically simplifies future normalization and migration work.
