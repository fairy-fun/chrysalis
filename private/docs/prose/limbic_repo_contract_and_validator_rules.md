## Limbic Repo Contract & Validator Rules

This document defines write boundaries and enforcement rules for all limbic-related data.

> The system may infer beyond the prose.
> It may not persist beyond the prose without explicit author confirmation.

---

## Write Boundary

```text
entity_linked_facts_event             = canonical prose-backed truth only
entity_limbic_state_suggestions_event = inferred/uncertain states only
entity_state_transitions_event        = curated, meaningful state changes
entity_coregulation_event             = regulatory interactions
```
These layers must remain strictly separated.

Suggestion persistence is the only exception to the general “no derived persistence” rule. Stored suggestions are non-canonical, non-authoritative, and must never be treated as truth.

---

## Core Separation

```text
facts        = what is canonically true
suggestions  = what is inferred or possible
coregulation = what affects others
transitions  = what meaningfully changes
```
Confidence is not evidence.

___

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
* require `subject_entity_id`
* require `context_entity_id`
* require `suggested_object_entity_id`
* must NOT insert into fact tables
* must NOT treat confidence as truth

---

### 2. Fact Insert
`POST /limbic/facts`
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
* require `subject_entity_id`
* require `context_entity_id`
* require `object_entity_id`
* require `source_document`
* reject inference-only inserts 
* reject confidence-only justification 
* fact must be justifiable from prose-backed evidence

A fact must be grounded in observable or narratively available evidence. Inference alone is not sufficient, regardless of confidence.

#### POV-Bounded Character Constraint

For POV-bounded characters, including:

```text
subject_entity_id = Shay
```

Reject fact inserts unless the evidence is prose-backed.

Valid evidence may include:
```text
explicit internal narration
physiological description
physiological settling
explicit regulation signals
directly narrated internal state
```

Invalid evidence includes:

```text
others calming down
the room stabilising
Shay stabilising the room
Shay stabilising another character
steady tone
competence
successful social performance
scene de-escalation
```
External regulation does not imply internal regulation.

---

### 3. Suggestion → Fact Promotion
`POST /limbic/suggestions/{id}/promote`
```json
{
  "source_document": "prose:book1_scene_40",
  "notes": "Author approved promotion based on prose evidence."
}
```

#### Validator Rules

* requires explicit endpoint call
  must create one fact row in entity_linked_facts_event
* must update suggestion:

    * `promoted_at`
    * `promoted_to_fact_id`
* must NOT auto-promote
* must re-run fact validation before insert 

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

* require subject_entity_id
* require context_entity_id 
* require from_object_entity_id 
* require to_object_entity_id 
* require source_document 
* verify both states already exist as facts for the same subject 
* reject inferred or missing states 
* do not create missing state facts 
* transitions must respect event ordering for the subject

For POV-bounded characters:
```text
no automatic transition creation
requires explicit author approval
```

---

### 5. Coregulation Insert
`POST /limbic/coregulation`
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
* require source_entity_id
* require target_entity_id
* require context_entity_id
* require regulation_type_id
* coregulation does not imply symmetry

If `caused_transition_id` is present:

* must reference existing transition
* `transition.subject_entity_id` == target_entity_id

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
* steady tone
* scene stabilisation

These may produce:

```text
entity_coregulation_event
source_entity_id = CHAR-MAIN-001
target_entity_id = another_character
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
* infer internal regulation from external performance
* collapse suggestions, facts, transitions, or coregulation into one layer

---

## Design Principle

```text
The system may think beyond the prose.
It may not write beyond the prose.
```
