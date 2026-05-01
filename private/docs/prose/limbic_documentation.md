# Limbic System — Event-Scoped Fact Model

## Overview

This system models **character limbic state as event-scoped facts**, not traits.

Each record captures:

```text
(character) + (event) → (limbic state)
```

Limbic state is:

- time-bound
- context-specific
- non-global

A character does not have a limbic state globally. A character enters a limbic state within a specific event.

---

## Table Architecture

```text
entity_linked_facts_event   → event-scoped facts
entity_linked_facts_global  → global facts
entity_linked_facts         → read-only compatibility VIEW
```

For limbic state tracking, use:

```text
entity_linked_facts_event
```

Do not use `entity_linked_facts_global` for limbic state.

Do not write to `entity_linked_facts`.

---

## Compatibility View Rule

`entity_linked_facts` is a compatibility VIEW.

It exists only to support legacy read paths during migration.

Do not insert, update, or delete through this view.

All writes must target one concrete table:

- `entity_linked_facts_global` for global facts
- `entity_linked_facts_event` for event-scoped facts

Inserting into `entity_linked_facts` may trigger missing-default warnings or errors because view inserts do not reliably preserve the underlying table defaults such as `AUTO_INCREMENT`, `DEFAULT CURRENT_TIMESTAMP`, or `ON UPDATE CURRENT_TIMESTAMP`.

Locked rule:

```text
entity_linked_facts = read bridge only, never a write target
```

---

## Fact Semantics

```text
subject_entity_id = what the fact is about
context_entity_id = where the fact is true
object_entity_id  = the linked value/entity
```

For limbic state facts:

```text
subject_entity_id = character
context_entity_id = calendar_event:<id>
fact_type_id      = fact_type_event_character_limbic_state
object_entity_id  = entity_limbic_<state>
```

---

## Relevant Table: `entity_linked_facts_event`

### Relevant Columns

| Column | Description |
|---|---|
| `linked_fact_id` | Auto-generated ID |
| `subject_entity_id` | Character ID |
| `context_entity_id` | Event ID, e.g. `calendar_event:33` |
| `fact_type_id` | Fact type |
| `object_entity_id` | Limbic state entity |
| `source_document` | Source attribution |
| `notes` | Optional reasoning or interpretation |
| `created_at` | Automatic timestamp |
| `updated_at` | Automatic timestamp |

The table handles `linked_fact_id`, `created_at`, and `updated_at` automatically.

Do not include those columns in normal inserts.

---

## Ontology

### Entity Type

```text
entity_type_limbic_state
```

### Limbic State Entities

| Entity | Meaning |
|---|---|
| `entity_limbic_hyperarousal` | Activated, elevated energy, stress response |
| `entity_limbic_hypoarousal` | Shutdown, dissociation, low energy |
| `entity_limbic_window_regulated` | Within regulation window; controlled |
| `entity_limbic_threat_activated` | Threat detection engaged |
| `entity_limbic_co_regulated` | Stabilised through another character or relational input |

### Fact Type

```text
fact_type_event_character_limbic_state
```

---

## Insert Pattern

Always insert event-scoped limbic state facts into `entity_linked_facts_event`.

```sql
INSERT INTO entity_linked_facts_event (
    subject_entity_id,
    context_entity_id,
    fact_type_id,
    object_entity_id,
    source_document,
    notes
) VALUES (
    '<CHAR_ID>',
    'calendar_event:<EVENT_ID>',
    'fact_type_event_character_limbic_state',
    '<LIMBIC_ENTITY>',
    'manual:<analysis_source>',
    '<optional_notes>'
);
```

Do not use:

```sql
INSERT INTO entity_linked_facts (...)
```

That targets the compatibility view and is not a valid write path.

---

## Example Inserts

```sql
INSERT INTO entity_linked_facts_event (
    subject_entity_id,
    context_entity_id,
    fact_type_id,
    object_entity_id,
    source_document,
    notes
) VALUES
(
    'CHAR-MAIN-001',
    'calendar_event:33',
    'fact_type_event_character_limbic_state',
    'entity_limbic_threat_activated',
    'manual:shay_analysis',
    'Summoned; room goes quiet; suspects Kai; threat system activates.'
),
(
    'CHAR-MAIN-001',
    'calendar_event:34',
    'fact_type_event_character_limbic_state',
    'entity_limbic_hyperarousal',
    'manual:shay_analysis',
    'Escalation into full activation.'
),
(
    'CHAR-MAIN-001',
    'calendar_event:40',
    'fact_type_event_character_limbic_state',
    'entity_limbic_window_regulated',
    'manual:shay_event_40_analysis',
    'Challenged by Kai; remains controlled and regulated.'
)
ON DUPLICATE KEY UPDATE
    notes = VALUES(notes),
    source_document = VALUES(source_document);
```

The event table enforces uniqueness across:

```text
subject_entity_id + context_entity_id + fact_type_id + object_entity_id
```

This makes inserts idempotent per observed fact.

---

## Query: Limbic Trajectory

Order events numerically, not lexically.

```sql
SELECT
    context_entity_id,
    object_entity_id,
    notes
FROM entity_linked_facts_event
WHERE subject_entity_id = 'CHAR-MAIN-001'
  AND fact_type_id = 'fact_type_event_character_limbic_state'
ORDER BY CAST(SUBSTRING_INDEX(context_entity_id, ':', -1) AS UNSIGNED);
```

---

## Modelling Rules

- Limbic state is never stored as a character trait.
- Limbic state is always tied to a specific `calendar_event`.
- Limbic state must remain an entity reference.
- Use `notes` to capture interpretation, context, or reasoning.
- Do not create new limbic state entities for one-off nuance.
- Do not store derived transitions.
- Do not store broad claims such as “Shay is co-regulated by Kai” unless represented as an event-scoped fact.

---

## Design Principle

This system models:

```text
event → induced state → stored fact → queryable trajectory
```

Not:

```text
character personality or traits
```

---

## Limbic Trigger Facts

Trigger facts explain what induced an observed limbic state.

Trigger facts do not replace limbic state facts. They explain them.

### Pattern

```text
subject_entity_id = character
context_entity_id = calendar_event
fact_type_id      = fact_type_event_limbic_trigger
object_entity_id  = triggering entity or condition
```

### Insert Pattern

```sql
INSERT INTO entity_linked_facts_event (
    subject_entity_id,
    context_entity_id,
    fact_type_id,
    object_entity_id,
    notes,
    source_document
) VALUES (
    'CHAR-MAIN-001',
    'calendar_event:33',
    'fact_type_event_limbic_trigger',
    'CHAR-KAI-001',
    'Suspected Kai involvement produces threat activation.',
    'manual:shay_analysis'
);
```

---

## Query: State + Trigger

```sql
SELECT
    s.context_entity_id AS event,
    s.object_entity_id AS limbic_state,
    t.object_entity_id AS trigger_entity,
    t.notes AS trigger_notes
FROM entity_linked_facts_event s
LEFT JOIN entity_linked_facts_event t
  ON t.subject_entity_id = s.subject_entity_id
 AND t.context_entity_id = s.context_entity_id
 AND t.fact_type_id = 'fact_type_event_limbic_trigger'
WHERE s.subject_entity_id = 'CHAR-MAIN-001'
  AND s.fact_type_id = 'fact_type_event_character_limbic_state'
ORDER BY CAST(SUBSTRING_INDEX(s.context_entity_id, ':', -1) AS UNSIGNED);
```

Keep state and cause separate.

---

## Limbic State Transitions

Limbic transitions are not stored as facts.

They are derived dynamically from ordered event-scoped limbic states.

A transition is defined as:

```text
(previous_event_state) → (next_event_state)
```

for the same character across sequential known limbic observations.

### Robust Transition Query

This query handles sparse event IDs, such as `calendar_event:34` followed by `calendar_event:40`.

```sql
SELECT
    t1.context_entity_id AS from_event,
    t1.object_entity_id AS from_state,
    t2.context_entity_id AS to_event,
    t2.object_entity_id AS to_state
FROM entity_linked_facts_event t1
JOIN entity_linked_facts_event t2
  ON t1.subject_entity_id = t2.subject_entity_id
 AND CAST(SUBSTRING_INDEX(t2.context_entity_id, ':', -1) AS UNSIGNED) =
(
    SELECT MIN(CAST(SUBSTRING_INDEX(t3.context_entity_id, ':', -1) AS UNSIGNED))
    FROM entity_linked_facts_event t3
    WHERE t3.subject_entity_id = t1.subject_entity_id
      AND t3.fact_type_id = 'fact_type_event_character_limbic_state'
      AND CAST(SUBSTRING_INDEX(t3.context_entity_id, ':', -1) AS UNSIGNED) >
          CAST(SUBSTRING_INDEX(t1.context_entity_id, ':', -1) AS UNSIGNED)
)
WHERE t1.subject_entity_id = 'CHAR-MAIN-001'
  AND t1.fact_type_id = 'fact_type_event_character_limbic_state'
ORDER BY CAST(SUBSTRING_INDEX(t1.context_entity_id, ':', -1) AS UNSIGNED);
```

### Transition Semantics

Transition interpretation is derived and optional.

| Transition | Possible interpretation |
|---|---|
| threat → hyperarousal | escalation |
| hyperarousal → regulated | recovery |
| threat → regulated | controlled override |
| regulated → hyperarousal | destabilisation |
| hypoarousal → regulated | re-engagement |

Do not persist these labels as base facts.

---

## Co-Regulation

Co-regulation can be stored directly as an event-scoped limbic state when the source supports it.

### Direct Co-Regulation Fact

```sql
INSERT INTO entity_linked_facts_event (
    subject_entity_id,
    context_entity_id,
    fact_type_id,
    object_entity_id,
    source_document,
    notes
) VALUES (
    'CHAR-MAIN-001',
    'calendar_event:<ID>',
    'fact_type_event_character_limbic_state',
    'entity_limbic_co_regulated',
    'manual:<analysis_source>',
    '<who or what stabilises them>'
);
```

### Derived Co-Regulation Signal

A possible co-regulation signal exists when:

```text
Character A: activated/hypoaroused/threatened → regulated/co-regulated
near or within an event where Character B performs a stabilising action
```

This is a candidate signal, not proof.

### Candidate Query: Recovery After Activation

```sql
SELECT
    t1.subject_entity_id,
    t1.context_entity_id AS from_event,
    t1.object_entity_id AS from_state,
    t2.context_entity_id AS to_event,
    t2.object_entity_id AS to_state
FROM entity_linked_facts_event t1
JOIN entity_linked_facts_event t2
  ON t1.subject_entity_id = t2.subject_entity_id
 AND CAST(SUBSTRING_INDEX(t2.context_entity_id, ':', -1) AS UNSIGNED) =
(
    SELECT MIN(CAST(SUBSTRING_INDEX(t3.context_entity_id, ':', -1) AS UNSIGNED))
    FROM entity_linked_facts_event t3
    WHERE t3.subject_entity_id = t1.subject_entity_id
      AND t3.fact_type_id = 'fact_type_event_character_limbic_state'
      AND CAST(SUBSTRING_INDEX(t3.context_entity_id, ':', -1) AS UNSIGNED) >
          CAST(SUBSTRING_INDEX(t1.context_entity_id, ':', -1) AS UNSIGNED)
)
WHERE t1.fact_type_id = 'fact_type_event_character_limbic_state'
  AND t2.fact_type_id = 'fact_type_event_character_limbic_state'
  AND t1.object_entity_id IN (
      'entity_limbic_hyperarousal',
      'entity_limbic_hypoarousal',
      'entity_limbic_threat_activated'
  )
  AND t2.object_entity_id IN (
      'entity_limbic_window_regulated',
      'entity_limbic_co_regulated'
  )
ORDER BY t1.subject_entity_id, from_event;
```

### Interpretation Rules

Do not infer co-regulation from recovery alone.

Require at least one of:

- another character is present in the event
- another character performs a stabilising action
- notes explicitly mention grounding, calming, mirroring, attunement, protection, touch, voice, pacing, or containment
- the state is directly stored as `entity_limbic_co_regulated`

Preferred modelling:

```text
subject = character
context = calendar_event:<ID>
object  = entity_limbic_co_regulated
notes   = stabilised by another character's presence, voice, pacing, containment, or action
```

---

## What Not To Store

Do not create persistent facts like:

```text
Shay is co-regulated by Kai
Kai regulates Shay
entity_limbic_transition_*
```

Unless the source explicitly supports a specific event-scoped relationship, keep those readings derived and optional.

---

## Outcome

This model enables:

- emotional timelines per character
- cross-event state transitions
- regulation pattern analysis
- multi-character comparison within shared events
- later analysis of who tends to stabilise whom

while keeping co-regulation:

- event-specific
- evidence-based
- queryable
- not overgeneralised
