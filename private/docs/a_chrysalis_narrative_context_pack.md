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

| Repository File | Purpose |
|---|---|
| `private/framework/calendar/calendar_subevent_service.php` | Canonical calendar subevent persistence and projection-aware materialization |
| `private/framework/calendar/calendar_prose_batch_planner.php` | Semantic prose segmentation, beat extraction, and review-plan generation |
| `private/docs/prose/limbic_documentation.md` | Event-scoped limbic state doctrine and suggestion/fact architecture |
| `private/docs/prose/limbic_pov.md` | POV-bounded epistemic constraints, Shay doctrine, and NPC/PC asymmetry |
| `private/docs/calendar/beat_classsets.md` | Referenced by planner for beat classset definitions and classifier contracts |

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

---

# 12. Immediate Next Steps

1. Define NPC/PC boundary contract
2. Inspect limbic framework code
3. Create POV admissibility validator
4. Add Shay protection checks
5. Prototype NPC response generator
6. Keep all outputs suggestion-only
7. Avoid auto-persistence

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
