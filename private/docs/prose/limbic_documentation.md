# Limbic System — Event-Scoped Fact Model

## Overview

This system models **character limbic state as event-scoped facts**, not traits.

Each record captures:

(character) + (event) → (limbic state)

---

## Table: `entity_linked_facts`

### Relevant Columns

| Column              | Type          | Description |
|--------------------|--------------|-------------|
| linked_fact_id      | bigint (PK)  | Auto-generated ID |
| subject_entity_id   | varchar(64)  | Character ID |
| context_entity_id   | varchar(64)  | Event ID (`calendar_event:<id>`) |
| fact_type_id        | varchar(64)  | Fact type |
| object_entity_id    | varchar(64)  | Limbic state |
| notes               | text         | Optional reasoning |
| source_document     | varchar(255) | Source attribution |
| created_at          | datetime     | ავტომatic |
| updated_at          | datetime     | ავტომatic |

---

## Ontology

### Entity Type

entity_type_limbic_state


### Limbic States

* entity_limbic_hyperarousal
* entity_limbic_hypoarousal
* entity_limbic_window_regulated
* entity_limbic_threat_activated
* entity_limbic_co_regulated


### Fact Type
fact_type_event_character_limbic_state


---

## Insert Pattern (Required)

Always use explicit columns:

```sql
INSERT INTO entity_linked_facts (
    subject_entity_id,
    context_entity_id,
    fact_type_id,
    object_entity_id,
    source_document
) VALUES (
    '<CHAR_ID>',
    'calendar_event:<EVENT_ID>',
    'fact_type_event_character_limbic_state',
    '<LIMBIC_ENTITY>',
    'manual:shay_analysis'
);

```

## Query: Limbic Trajectory
```sql
SELECT
context_entity_id AS event,
object_entity_id  AS limbic_state
FROM entity_linked_facts
WHERE subject_entity_id = 'CHAR-MAIN-001'
AND fact_type_id = 'fact_type_event_character_limbic_state'
ORDER BY context_entity_id;

```

## Constraints

Do NOT store limbic state on character profiles
Do NOT use attribute systems
ALWAYS include context_entity_id
Limbic state must remain an entity reference
Inserts must be explicit and manual

## Design Principle

This system models:

event → induced state → stored fact → queryable trajectory

NOT:

character personality or traits

## Future Extensions

Causal attribution (who/what triggered state)
Event participant joins
Recovery dependency mapping (self vs co-regulation)

## Limbic Trigger Facts

Trigger facts explain what induced the observed limbic state.

#### Pattern:

```text
subject_entity_id = character
context_entity_id = calendar_event
fact_type_id      = fact_type_event_limbic_trigger
object_entity_id  = triggering entity or condition
```

Trigger facts do not replace limbic state facts. They explain them.

### Example:

```sql
INSERT INTO entity_linked_facts (
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
Query state + trigger together:

```sql
SELECT
s.context_entity_id AS event,
t.object_entity_id AS trigger_entity,
s.object_entity_id AS limbic_state,
t.notes AS trigger_notes
FROM entity_linked_facts s
LEFT JOIN entity_linked_facts t
ON t.subject_entity_id = s.subject_entity_id
AND t.context_entity_id = s.context_entity_id
AND t.fact_type_id = 'fact_type_event_limbic_trigger'
WHERE s.subject_entity_id = 'CHAR-MAIN-001'
AND s.fact_type_id = 'fact_type_event_character_limbic_state'
ORDER BY s.context_entity_id;
```

This is the right next layer. Keep **state** and **cause** separate.

