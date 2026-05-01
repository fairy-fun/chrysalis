## Limbic Repo Contract & Validator Rules

This document defines **write boundaries and enforcement rules** for all limbic-related data.

---

## Write Boundary

```text
entity_linked_facts_event           = canonical truth only
entity_limbic_state_suggestions_event = inferred/uncertain states
entity_state_transitions_event      = curated, meaningful state changes
entity_coregulation_event           = regulatory interactions
```

These layers must remain strictly separated.

---

## API Contracts

### 1. Suggestion Insert
POST /limbic/suggestions

```json
{
  "subject_entity_id": "CHAR-SHAY-001",
  "context_entity_id": "calendar_event:40",
  "suggested_object_entity_id": "entity_limbic_window_regulated",
  "basis_type": "behavioral_inference",
  "supporting_entities": ["CHAR-KAI-001", "scene_deescalation"],
  "confidence": 0.35,
  "notes": "Externally steady, but prose does not confirm internal regulation."
}
```

#### Validator Rules

* allow inference
* require `basis_type`
* require `confidence`
* must NOT insert into fact tables

---

### 2. Fact Insert
POST /limbic/facts
```json
{
  "subject_entity_id": "CHAR-SHAY-001",
  "context_entity_id": "calendar_event:40",
  "object_entity_id": "entity_limbic_threat_activated",
  "source_document": "prose:book1_scene_40",
  "notes": "Prose-supported internal threat activation."
}
```

#### Validator Rules

* must insert into `entity_linked_facts_event`
* require `source_document`
* reject if derived from inference

#### Shay Constraint (POV-Bounded)

If:

```text
subject_entity_id = Shay
```

Then reject unless:

* evidence is prose-backed
* notes reflect internal state evidence

Explicitly invalid signals:

```text
others calming down
Shay stabilising the room
steady tone or competence
scene de-escalation
```

---

### 3. Suggestion → Fact Promotion
POST /limbic/suggestions/{id}/promote
```json
{
  "source_document": "prose:book1_scene_40",
  "notes": "Author approved promotion based on prose evidence."
}
```

#### Validator Rules

* requires explicit endpoint call
* must create fact row
* must update suggestion:

    * `promoted_at`
    * `promoted_to_fact_id`
* must NOT auto-promote

---

### 4. Transition Insert
POST /limbic/transitions
```json
{
  "subject_entity_id": "CHAR-SHAY-001",
  "context_entity_id": "calendar_event:40",
  "from_object_entity_id": "entity_limbic_threat_activated",
  "to_object_entity_id": "entity_limbic_window_regulated",
  "source_document": "prose:book1_scene_40",
  "notes": "Author-approved meaningful transition."
}
```

#### Validator Rules

* require both `from` and `to`
* verify both exist as facts for subject
* reject inferred or missing states

For Shay:

```text
no automatic transition creation
requires explicit author approval
```

---

### 5. Coregulation Insert
POST /limbic/coregulation
```json
{
  "source_entity_id": "CHAR-SHAY-001",
  "target_entity_id": "CHAR-KAI-001",
  "context_entity_id": "calendar_event:40",
  "regulation_type_id": "entity_regulation_type_stabilise",
  "caused_transition_id": 12,
  "notes": "Shay stabilizes Kai; does not imply Shay is internally regulated."
}
```

#### Validator Rules

* source = regulator
* target = affected subject

If `caused_transition_id` is present:

* must reference existing transition
* transition.subject_entity_id == target_entity_id

---

## CPTSD Constraint (Shay Critical Rule)

```text
external regulation ≠ internal regulation
```

The system must NOT infer Shay’s internal regulation from:

* others calming down
* successful de-escalation
* behavioural control
* perceived competence

These may produce:

```text
coregulation events (Shay → others)
```

They must NOT produce:

```text
Shay = entity_limbic_window_regulated (fact)
```

---

## Prohibited Behaviors

The API must NOT:

* write inferred states into `entity_linked_facts_event`
* auto-promote suggestions
* derive and store transitions automatically
* treat confidence as truth
* bypass POV constraints

---

## Design Principle

```text
The system may think beyond the prose.
It may not write beyond the prose.
```
