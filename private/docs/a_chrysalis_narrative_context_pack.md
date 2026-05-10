# Chrysalis Narrative Cognition Architecture — Context Pack

This document consolidates the architectural discussion, doctrine, workflow, and repository references needed to resume work in a future chat without re-deriving the conceptual model.

---

# Table of Contents

1. Core Goal
2. High-Level Workflow
3. Architectural Principles
4. Repository Files Referenced
5. Calendar Subevent Persistence
6. Prose Batch Planner
7. Limbic System
8. POV Doctrine
9. Long-Term Goal
10. NPC / PC Boundary
11. Current Architecture Summary
12. Immediate Next Steps
13. Core Doctrine

---

# 1. Core Goal

Build an:

```text
author-supervised narrative cognition system
```

where:

- the author retains canonical authority
- the system performs semantic interpretation and continuity assistance
- the system can eventually roleplay NPCs
- the system may NEVER override the author-controlled POV/PC character

---

# 2. High-Level Workflow

```text
1. ID check
2. Initial interview
3. Author provides prose block
4. System segments prose into subevents
5. System classifies beats
6. System derives semantic suggestions:
   - people
   - places
   - relationships
   - themes
   - limbic states
   - triggers
   - continuity references
7. Second interview:
   - system presents semantic interpretations
   - author confirms/rejects/edits
8. Review-only JSON generated
9. Author manually submits JSON via Postman
10. Database persists canonical truth
```

Critical doctrine:

```text
The system may think beyond the prose.
It may not write beyond the prose.
```

---

# 3. Architectural Principles

- The system proposes. The author decides. The database records the decision.
- The system may think beyond the prose. It may not write beyond the prose.
- Suggestions are non-canonical until author approval.
- NPC interiority may be modeled; PC interiority remains author-authored.
- Canonical persistence occurs only after explicit human review.

---

# 4. Repository Files Referenced

| Repository File                                                  | Purpose                                                                      |
|------------------------------------------------------------------|------------------------------------------------------------------------------|
| `private/framework/calendar/calendar_subevent_service.php`       | Canonical calendar subevent persistence and projection-aware materialization |
| `private/framework/calendar/calendar_prose_batch_planner.php`    | Semantic prose segmentation, beat extraction, and review-plan generation     |
| `private/docs/prose/limbic_documentation.md`                     | Event-scoped limbic state doctrine and suggestion/fact architecture          |
| `private/docs/prose/limbic_pov.md`                               | POV-bounded epistemic constraints, Shay doctrine, and NPC/PC asymmetry       |
| `private/docs/calendar/beat_classsets.md`                        | Referenced by planner for beat classset definitions and classifier contracts |
| File                                                             | Purpose                                                                      |
| ---------------------------------------------------------------- | ---------------------------------------------------------------------------  |
| `private/framework/prose/prose_draft_creator.php`                | Unified prose ingestion, annotation persistence, and calendar orchestration  |
| `private/framework/entity/entity_link_suggestions.php`           | Non-canonical SQL suggestion generation for entity linking                   |
| `private/framework/limbic/limbic_fact_validator.php`             | Runtime POV admissibility enforcement and Shay protection                    |
| `private/framework/limbic/limbic_fact_writer.php`                | Canonical limbic fact persistence                                            |
| `public_html/pecherie/chill-api/prose/add_prose_annotations.php` | Annotation ingestion endpoint                                                |
| `public_html/pecherie/chill-api/entity/suggest_link_entity.php`  | Suggestion-generation API endpoint                                           |


---

# 5. Calendar Subevent Persistence

File:

```text
private/framework/calendar/calendar_subevent_service.php
```

Role:

```text
canonical subevent materializer
```

Key behaviors:

- projection-first runtime identity
- deterministic persistence
- idempotent creation
- beat_type_id support
- inherited semantic context

Pipeline position:

```text
semantic planning
→ confirmation
→ create_calendar_subevent_core()
→ canonical persistence
```

---

# 6. Prose Batch Planner

File:

```text
private/framework/calendar/calendar_prose_batch_planner.php
```

Role:

```text
semantic batch compiler
```

Key functions:

```php
generate_calendar_batch_from_prose()
split_prose_into_candidate_segments()
extract_calendar_beats()
classify_calendar_beat_type()
```

Important doctrine:

```text
double newline boundaries
NOT sentence boundaries
```

The planner returns:

```php
'mode' => 'plan_only'
```

Meaning:

- review-only execution plans
- NOT automatic persistence

Operation payload example:

```php
[
  'operation' => 'createCalendarSubevent',
  'event_label',
  'prose_body',
  'beat_type_id',
  'beat_inference',
  'client_id',
]
```

---

# 7. Limbic System

Primary file:

```text
private/docs/prose/limbic_documentation.md
```

Core model:

```text
(character) + (event) → (limbic state)
```

Limbic state is:

- event-scoped
- contextual
- NOT a permanent trait

Canonical facts:

```text
entity_linked_facts_event
```

Suggestion layer:

```text
entity_limbic_state_suggestions_event
```

Critical distinction:

```text
Suggestion = inference
Fact = prose-supported canonical truth
```

Important principle:

```text
external steadiness ≠ internal regulation
```

Especially important for Shay.

### Existing Durable Suggestion Infrastructure
entity_limbic_state_suggestions_event

already supports:
```text
durable machine-generated inference persistence
confidence values
evidence/support metadata
promotion tracking
canonical fact linkage
```

#### Existing columns include:
```text
suggestion_id
basis_type
supporting_entities
confidence
promoted_at
promoted_to_fact_id
```
#### And now planned additions:
```text
review_status
reviewed_by_entity_id
reviewed_at
epistemic_scope
inferred_by_entity_id
supporting_annotation_ids
```

---

# 8. POV Doctrine

Primary file:

```text
private/docs/prose/limbic_pov.md
```

The system distinguishes:

```text
what is observed
what is inferred
what is caused
```

AND:

```text
who is allowed to know what
```

Shay is:

```text
pov_bounded
```

Meaning:

```text
No inferred limbic state for Shay
may be stored as canonical fact.
```

Allowed:

```text
Shay causes emotional reactions in others
```

Forbidden:

```text
others react to Shay
→ infer Shay interior state
→ persist as fact
```

Central doctrine:

```text
The system may think beyond the prose.
It may not write beyond the prose.
```

limbic_assert_pov_bounded_fact_support()

already exist in runtime validation.

#### Runtime Enforcement Exists

private/framework/limbic/limbic_fact_validator.php

already enforces:
```text
Shay POV restrictions
explicit evidence requirements
```

Current doctrine is therefore:
```text
conceptual
AND runtime-enforced
```
That is a major distinction.

---

# 9. Long-Term Goal

Enable:

```text
author as PC
system as NPC/world simulator
```

using:

- limbic state
- relationship state
- continuity memory
- event history
- voice constraints
- causal modeling

---

# 10. NPC / PC Boundary

System MAY:

- roleplay NPCs
- infer NPC emotional trajectories
- react according to continuity
- evolve through transitions

System MAY NOT:

- define Shay’s feelings
- define Shay’s intentions
- override author intent
- canonize inferred PC interiority

Author remains sovereign.

---

# 11. Current Architecture Summary

```text
prose
→ annotation spans
→ segmentation
→ beat extraction
→ semantic inference
→ limbic interpretation
→ causal modeling
→ POV admissibility filtering
→ suggestion/fact separation
→ author confirmation
→ JSON commit payload
→ manual Postman execution
→ canonical persistence
```
### the system already includes:
```text
durable suggestions
annotations
semantic persistence layers
```

## Suggestion Governance

Because this is now the real architectural center.
```text
Suggestions are durable but non-canonical.

A suggestion may:
- originate from machine inference
- originate from NPC cognition
- reference prose annotations
- contain confidence metadata
- contain epistemic scope metadata

A suggestion may NOT become canonical truth
without explicit author adjudication.

Rejected suggestions remain durable
to prevent continuity drift and repeated inference.
```

## Prose Annotation Layer

Core persistence table:
```text
prose_annotation_spans
```
Supports:

* span-level semantic grounding
* subject-scoped annotations
* ontology-typed annotations
* source attribution
* evidence linkage for inference review

This is the explainability layer for semantic cognition.


## Governance Model
### 1. Separate Artifact From Canonical Status

Never store:

{
"is_canonical": true
}

Instead:

artifact
artifact_revision
artifact_adjudication
artifact_promotion_event

Canon is not a property.
Canon is the result of governance history.

### 2. Epistemic Classes

Every machine-generated entity should carry an epistemic origin.

Example:

OBSERVED
Directly stated in source material

DERIVED
Mechanically extracted from explicit text

INFERRED
Semantic implication generated by cognition systems

SPECULATIVE
Weak-confidence narrative possibility

SYNTHETIC
Machine-authored connective proposal

AUTHOR_CONFIRMED
Explicitly approved by human authority

AUTHOR_REJECTED
Explicitly denied by human authority

This becomes critically important later when:

contradictions emerge
timelines fork
POV boundaries matter
NPC cognition diverges
memory corruption exists intentionally

3. Promotion Must Be Event-Based

Do not mutate rows into canon.

Instead:
```text
artifact_created
artifact_reviewed
artifact_promoted
artifact_rejected
artifact_superseded
artifact_restored
artifact_deprecated
```

This gives:

* auditability
* replayability
* temporal truth reconstruction
* provenance integrity

You already have orchestration thinking aligned with this.

### 4. Rejection Must Persist

This is one of the most important pieces.

A rejected inference cannot simply disappear.

Because otherwise the system will regenerate it forever.

You need durable rejection memory.

Example:
```text
Suggestion:
"Shay trusts Elias"

Rejected because:
"POV violation: Shay never internally resolves trust"

Future inference systems must consult:
- rejection history
- rejection rationale
- rejection class

This turns rejection into training data for the cognition layer.
```

### 5. Rejection Types Matter

Not all rejections mean the same thing.

Suggested taxonomy:
```text
FACTUAL_ERROR
TIMELINE_CONTRADICTION
POV_VIOLATION
THEMATIC_VIOLATION
VOICE_VIOLATION
TOO_EXPLICIT
TOO_ON_THE_NOSE
PREMATURE_REVELATION
CHARACTER_INCONSISTENCY
WORLD_RULE_CONFLICT
AUTHORIAL_PREFERENCE
```
This becomes extraordinarily valuable later for:

* adaptive inference suppression
* stylistic tuning
* narrative safety rails
* future model steering

### 6. Promotion Should Require Provenance Closure

A promoted artifact should never exist without:

* source lineage
* supporting annotations
* originating beats
* inference chain
* model/version metadata
* adjudicator identity
* timestamp

You want future explainability:

“Why does the system believe this?”

Without this, continuity debugging becomes impossible at scale.

7. Contradictions Need First-Class Representation

Do not treat contradiction as corruption.

Narratives often intentionally contain:

* unreliable narration
* memory fracture
* conflicting testimony
* evolving truths
* retcons
* perspective asymmetry

So instead of:
```text
fact A invalidates fact B
```
You often need:
```text
fact A contradicts fact B
under context:
- narrator=shay
- temporal_state=pre-collapse
- confidence=subjective
```
Contradictions should be governable entities.

### 8. Canon Should Be Temporal

Avoid “current truth only.”

Instead:
```text
canonical during revision window X
superseded at Y
restored at Z
```
This matters because:

* books evolve
* revisions happen
* scenes migrate
* interpretations sharpen
* future tooling may need historical reconstruction

### 9. Governance Requires Explicit Authority Hierarchy

You already implicitly have this.

You should formalize it.

Example:
```text
AUTHOR
absolute authority

EDITOR
review authority

SYSTEM
proposal authority only

READER_MODEL
non-authoritative inference

AUTOMATION
transport/extraction authority only
```
### 10. Shay POV Doctrine Should Become Enforcement Rules
Our architecture repeatedly emphasizes:

* anti-omniscience
* constrained interpretation
* emotional opacity
* protected ambiguity

That should become executable policy.

#### RULE:
No inferred internal emotional certainty may be promoted for Shay

This prevents accidental canonization by automation.

### Minimal Schema Layer

Something approximately like:
```text
artifacts
artifact_revisions
artifact_sources
artifact_annotations
artifact_inferences
artifact_adjudications
artifact_promotion_events
artifact_contradictions
artifact_rejection_reasons
artifact_authority
```
And critically:
```text
canonical_views
```
should probably be computed projections, not primary storage.

---

### Most Important Architectural Principle

The cognition system should never ask:

> “What is true?”

It should ask:

> “What is currently governably admissible as canon under this authority and temporal context?”

That distinction is the difference between:

* autocomplete lore systems
and
* durable narrative cognition infrastructure.

---

# 12. Immediate Next Steps
```text
1. Extend durable suggestion review governance
2. Add review_status lifecycle support
3. Persist author thumbs up/down decisions
4. Add annotation-grounded evidence linkage
5. Add epistemic_scope enforcement
6. Prevent repeated rejected suggestions
7. Build second-interview adjudication API
8. Prototype NPC cognition constrained by epistemic scope
```

---

# 13. Core Doctrine

```text
The system proposes.
The author decides.
The database records the decision.
```

And:

```text
NPC interiority may be modeled.
PC interiority remains author-authored.
```
