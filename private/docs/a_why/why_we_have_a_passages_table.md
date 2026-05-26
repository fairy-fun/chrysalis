# Calendar Event Passage Doctrine

Now the determining factor becomes:

does this row materially carry narrative state?

That’s a fundamentally better doctrine.

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

I think the eventual doctrine probably becomes:
calendar_events

Owns ONLY:

temporal placement
identity
scheduling semantics
calendar_event_passages

Owns:

ordered narrative structure
prose_families

Owns:

semantic continuity of prose
prose_drafts

Owns:

actual mutable prose realizations

And critically:

prose_body inside events stops being canonical

That’s the huge simplification.

New prose_body Doctrine

calendar_events.prose_body is:

a denormalized operator-visible narrative projection

NOT:

canonical prose storage

Canonical narrative authority becomes:

calendar_event_passages
-> prose_families
-> prose_drafts

while:

calendar_events.prose_body

exists for:

GPT discoverability
operator continuity
semantic visibility
searchability
lightweight rendering
indexing
quick inspection
The implementation strategy should be:
Phase 1 — Stabilize semantics

Do NOT remove prose_body.

Instead:

prose_body becomes rebuildable cache state

That is psychologically and operationally safer.

Phase 2 — Introduce projection generation

You need a deterministic way to rebuild:

calendar_events.prose_body

from passages.

Probably:

event
-> ordered passages
-> current prose draft per family
-> concatenated render
Proposed render doctrine

Example:

Event
3.1.1.1
Passages
passage_index	prose
1	opening
2	continuation
3	resolution
Generated prose_body
opening

continuation

resolution

That becomes the operator-visible projection.

This means prose_body should become:
Derived state

NOT editable authority.

That’s the critical shift.

Then the implementation becomes very clean
Add canonical linkage

You already created:

calendar_event_passages

Now the next important fields are probably:

ALTER TABLE calendar_events
ADD COLUMN prose_projection_updated_at DATETIME NULL;

Possibly later:

ADD COLUMN prose_projection_hash VARCHAR(64) NULL;

for rebuild invalidation.

Then establish rebuild doctrine

Whenever:

passage changes
prose family changes
prose draft changes

the event projection becomes stale.

Then regenerate:

calendar_events.prose_body
Important architectural recommendation

I would strongly avoid:

event directly owns prose family

now.

Instead:

event
-> passages
-> prose families

because this preserves future flexibility:

multiple passages
alternate renders
passage-specific drafts
branching narrative
partial rewrites

without destabilizing event identity.

Suggested passage render query

Eventually something conceptually like:

SELECT
cep.calendar_event_id,
cep.passage_index,
pd.prose_body
FROM calendar_event_passages cep
JOIN prose_drafts pd
ON pd.prose_family_id = cep.prose_family_id
WHERE pd.id = (
SELECT MAX(pd2.id)
FROM prose_drafts pd2
WHERE pd2.prose_family_id = cep.prose_family_id
)
ORDER BY cep.passage_index;

Then concatenate into:

calendar_events.prose_body
The most important thing

This architecture finally gives you:

concept	authority
temporal identity	calendar_events
narrative ordering	passages
semantic continuity	prose_families
mutable text	prose_drafts
GPT visibility	prose_body

That is an actually stable ontology.

And most importantly:

future GPT chats no longer need chronology archaeology to discover narrative state.

yeah, what we don't know about is the actual functional relationship between migrating attached prose to the passages ... so we don't want to get too precious until we've cleaned out the semantic rot

Exactly. That restraint is correct.

Right now you do not yet understand the full operational semantics of:

event
↔ prose family
↔ passage
↔ draft lineage

especially under real workflow mutation.

So becoming too doctrinal too early would be dangerous.

Phase 1 — Remove semantic rot

Aggressively prune:

empty lineage artifacts
obsolete chronology residue
duplicate shells
semantically dead rows

while preserving:

active prose continuity
modern references
meaningful temporal anchors
Phase 2 — Observe live passage behavior

Actually use:

calendar_event_passages

in real workflows.

See what naturally emerges around:

continuation
editing
regeneration
rendering
chronology interaction
draft evolution
Phase 3 — Refine doctrine from operational reality

Not from archaeology.

That’s the key maturation step.

You’ve already escaped the most dangerous phase, which was:

implicit multiplexed chronology semantics

Now the system can evolve through explicit structures instead of hidden topology accidents.