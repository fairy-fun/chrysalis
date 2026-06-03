# Event-Scoped Character Limbic State Guide

## Purpose

This document defines the database-level persistence model for recording a character's limbic state relative to a specific event.

The model is:

- event-scoped
- fact-based
- ontology-backed
- queryable over time

This document describes the persistence contract and data model.

API design, service-layer implementation, ID allocation, timestamp management, and repository write mechanics are intentionally out of scope.

---

# Core Doctrine

Limbic state is not a character trait.

Limbic state is not a profile attribute.

Limbic state is an event-scoped fact.

Mental model:

```text
Event
    ↓
induces state
    ↓
stored as fact
    ↓
queryable trajectory
```

Not:

```text
Character
    ↓
permanent trait storage
```

---

# Persistence Target

Verified database table:

```text
entity_linked_facts
```

Verified columns:

```text
linked_fact_id
subject_entity_id
context_entity_id
fact_type_id
object_entity_id
source_document
notes
created_at
updated_at
```

---

# Semantic Mapping

| Column | Meaning |
|----------|----------|
| subject_entity_id | Character entity |
| context_entity_id | Calendar event entity |
| fact_type_id | Fact classification |
| object_entity_id | Limbic-state entity |
| source_document | Provenance reference |
| notes | Optional explanatory notes |

---

# Canonical Fact Pattern

```text
subject_entity_id
    ↓
Character

context_entity_id
    ↓
Event

fact_type_id
    ↓
fact_type_event_character_limbic_state

object_entity_id
    ↓
Limbic State
```

Equivalent representation:

```text
(subject = character)
(context = event)
(object = limbic state)
```

---

# Event-Scoped Examples

Example 1:

```text
Character:
    CHAR-MAIN-001
    Shay Aurelia Vertue Young

Event:
    Event 33

Limbic State:
    entity_limbic_threat_activated
```

Stored fact:

```text
subject_entity_id = CHAR-MAIN-001
context_entity_id = Event 33
fact_type_id = fact_type_event_character_limbic_state
object_entity_id = entity_limbic_threat_activated
```

Example 2:

```text
Character:
    CHAR-MAIN-001
    Shay Aurelia Vertue Young

Event:
    Event 40

Limbic State:
    entity_limbic_window_regulated
```

Stored fact:

```text
subject_entity_id = CHAR-MAIN-001
context_entity_id = Event 40
fact_type_id = fact_type_event_character_limbic_state
object_entity_id = entity_limbic_window_regulated
```

---

# Fact Type

Canonical fact type:

```text
fact_type_event_character_limbic_state
```

Meaning:

```text
Character
    ↓
Event
    ↓
Limbic State
```

---

# Limbic State Ontology

Limbic states are represented as entities.

Ontology type:

```text
entity_type_limbic_state
```

Known examples:

```text
entity_limbic_hyperarousal
entity_limbic_hypoarousal
entity_limbic_window_regulated
entity_limbic_threat_activated
entity_limbic_co_regulated
```

Additional ontology entities may be added without changing the persistence model.

---

# Storage Rules

## DO

Store limbic state as a fact.

Use:

```text
entity_linked_facts
```

Include:

```text
subject_entity_id
context_entity_id
fact_type_id
object_entity_id
```

Treat the event as the context.

Maintain ontology-backed state values.

---

## DO NOT

Do not store limbic state:

```text
on character profiles
as permanent character traits
as detached attribute values
outside event context
```

Do not collapse multiple event states into a single character-level field.

---

# Conceptual Query Model

The structure supports queries such as:

```text
What was Shay's limbic state during Event 33?
```

```text
Show Shay's limbic-state trajectory across events.
```

```text
Which characters were threat activated during a given event?
```

Because state is stored as:

```text
Character
    ↓
Event
    ↓
Limbic State
```

rather than:

```text
Character
    ↓
Trait
```

---

# Database Workflow

1. Identify the character entity.
2. Identify the calendar event entity.
3. Identify the limbic-state ontology entity.
4. Associate them using:

```text
fact_type_event_character_limbic_state
```

5. Persist the fact in:

```text
entity_linked_facts
```

Result:

```text
character + event + limbic state
```

becomes a queryable historical fact.

---

# Scope Boundary

This guide documents:

- ontology contract
- fact structure
- event-scoped storage model
- persistence target table

This guide does not define:

- API endpoints
- workflow UI
- suggestion logic
- extraction logic
- ID allocation mechanisms
- timestamp generation mechanisms
- repository implementation details

Those concerns should be documented separately once the write path is formally defined.
