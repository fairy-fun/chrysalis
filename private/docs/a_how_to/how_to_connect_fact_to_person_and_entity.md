# How To Connect a Fact to a Person and a Separate Entity

This document describes the live DB pattern currently used in `sxnzlfun_chrysalis`
for attaching a canonical fact to:

- a character/person
- another entity (company, event, song, medley, etc.)

using:

```text
entity_linked_facts_global
```

---

# Core Doctrine

A fact is represented as:

```text
subject_entity_id
    -> fact_type_id
        -> object_entity_id
```

The semantic payload usually lives in:

```text
notes
```

This allows:
- nuanced prose
- psychological interpretation
- historical framing
- continuity notes
- relationship context

without requiring schema expansion.

---

# Table Used

```text
entity_linked_facts_global
```

---

# Canonical Pattern

```sql
INSERT INTO entity_linked_facts_global (

    linked_fact_id,

    subject_entity_id,
    fact_type_id,
    object_entity_id,

    source_document,
    notes,

    epistemic_origin_classval_id,
    adjudication_status_classval_id,
    contradiction_state_classval_id

) VALUES (

    @next_linked_fact_id,

    '<SUBJECT_ENTITY_ID>',
    '<FACT_TYPE_ID>',
    '<OBJECT_ENTITY_ID>',

    'manual',

    '<FACT_TEXT>',

    'epistemic_origin_legacy_imported',
    'adjudication_status_grandfathered_canon',
    'contradiction_state_none'
);
```

---

# Step 1 — Allocate linked_fact_id

The live DB currently does NOT auto-increment `linked_fact_id`.

Use:

```sql
SET @next_linked_fact_id := (
    SELECT COALESCE(MAX(linked_fact_id), 0) + 1
    FROM entity_linked_facts_global
);
```

Do NOT inline this directly into the INSERT statement,
because MySQL will reject self-referencing target-table inserts.

---

# Step 2 — Choose the Subject Entity

The subject is the entity the fact is "about".

Examples:

| Subject | Entity ID |
|---|---|
| Shay | CHAR-MAIN-001 |
| Lenore Kingsley | CHAR-SUP-998 |
| RBDS | COMP-001 |

---

# Step 3 — Choose the Object Entity

The object is the contextual or related entity.

Examples:

| Object | Entity ID |
|---|---|
| LDS Church | COMP-003 |
| Standard Competitive Medley | MEDLEY-001 |
| Song entity | entity_song_* |

---

# Step 4 — Choose a fact_type_id

Examples already visible live:

| fact_type_id |
|---|
| fact_type_played_song |
| fact_type_song_artist |
| fact_type_event_theme |
| fact_type_choreography_intervention |

You may also author new semantic fact types if needed.

Examples:

```text
fact_type_religious_framework_reflection
fact_type_choreography_intervention
fact_type_identity_concealment
```

---

# Step 5 — Put Nuance in notes

The important narrative/psychological interpretation
should usually live in:

```text
notes
```

Example:

```text
Shay views the Church through a germ-theory analogy:
the absence of limbic and developmental understanding
produced real psychological harm despite the absence
of explicit malice.
```

---

# Example — Shay + LDS Church

```sql
SET @next_linked_fact_id := (
    SELECT COALESCE(MAX(linked_fact_id), 0) + 1
    FROM entity_linked_facts_global
);

INSERT INTO entity_linked_facts_global (

    linked_fact_id,

    subject_entity_id,
    fact_type_id,
    object_entity_id,

    source_document,
    notes,

    epistemic_origin_classval_id,
    adjudication_status_classval_id,
    contradiction_state_classval_id

) VALUES (

    @next_linked_fact_id,

    'CHAR-MAIN-001',
    'fact_type_religious_framework_reflection',
    'COMP-003',

    'manual',

    'Shay views the Church through a germ-theory analogy: the question of blame is separate from the question of cost.',

    'epistemic_origin_legacy_imported',
    'adjudication_status_grandfathered_canon',
    'contradiction_state_none'
);
```

---

# Important Conventions

## Use entity IDs directly

Use:
- `CHAR-*`
- `COMP-*`
- `MEDLEY-*`
- canonical entity IDs

Do NOT use:
- profile tables
- inferred aliases
- chronology-derived identities

---

## Facts are not relationships

Use:

```text
entity_linked_facts_global
```

for:
- beliefs
- interpretations
- observations
- continuity facts
- thematic assertions

Use:

```text
relationships
```

for:
- partnerships
- affiliation structures
- family links
- organizational links
- explicit social edges

---

# Current Known Good Status Values

These are confirmed live-valid:

```text
epistemic_origin_legacy_imported
adjudication_status_grandfathered_canon
contradiction_state_none
```

---

# DB Wrapper Safety

Safe:
- deterministic IDs
- simple predicates
- explicit inserts

Avoid:
- REGEXP-heavy logic
- CAST coercion ladders
- INFORMATION_SCHEMA introspection
- chronology reconstruction
