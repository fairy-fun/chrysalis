# POV-Bounded Limbic & Causality Model

_(Shay as constrained interior, others as observable systems)_

Limbic state is an event-scoped interpretive layer derived from:
- observed behaviour in prose
- annotation signals
- expression outputs (affect/cognition/control)
- prior accepted event-scoped facts

It is never stored directly without explicit author confirmation.

## 1. Core Principle

The system must distinguish between:

``` text
what is observed (prose-grounded)
what is inferred (model-derived)
what is caused (interaction-driven)
```

And critically:
``` text
POV characters have restricted inferential access to their own internal state
```

POV characters do not produce direct limbic facts.

Their internal experience remains:
- prose-only
- annotation-driven
- suggestion-inferred (but never automatically accepted)

## 2. Shay as a Bounded POV Entity

###    2.1 Profile Tag
``` text
entity: character:Shay

profile_constraint:
type: pov_bounded
scope: books_1_3
rule: internal_state_requires_prose_evidence
```

### 2.2 Enforcement Rule

For Shay:

| Operation	                 | Allowed	 | Constraint                              |
|----------------------------|----------|-----------------------------------------|
| Limbic state fact	         | ✅	       | Must be explicitly supported by prose   |
| Transition creation	       | ⚠️	      | Only if both states are prose-supported |
| Trigger attribution	       | ⚠️	      | Must not exceed what prose justifies    |
| Co-regulation (as target)	 | ⚠️	      | Requires explicit textual grounding     |

Hard rule:
``` text
No inferred limbic state for Shay based solely on other characters’ reactions.
```


---

## 3. Limbic Facts (Unchanged, but Reinterpreted)

From [limbic_documentation.md:](https://github.com/fairy-fun/chrysalis/blob/8fe92ae405f8e81fe4886b5aec6866c4874a4b7b/private/docs/prose/limbic_documentation.md)
``` text
(character) + (event) → (limbic state)
```
Still stored in:
``` text
entity_linked_facts_event
```
But now:

### 3.1 Two Evidence Modes

Add conceptual layer (can live in `notes` or later structured):
``` text
evidence_mode:
- prose_explicit
- prose_close_pov
- inferred_external
```

### 3.2 POV Constraint Overlay
``` text
  IF subject = Shay
  THEN evidence_mode MUST NOT be inferred_external
```
For other characters:
``` text
inferred_external = allowed
```
## 4. Transitions: From Derived → Semi-First-Class

The limbic doc says:

> transitions are derived, not stored

But our system is evolving beyond that. The correct refinement is:

### 4.1 Dual Nature of Transitions
derived_transition = default (query-time)
materialized_transition = optional (when narratively or causally important)

Stored in:

entity_state_transitions_event
### 4.2 Transition Validity Rule

A transition is valid if:
``` text
state(t1) and state(t2) both exist as event facts
```
### 4.3 POV Constraint on Transitions

For Shay:
``` text
A transition may only exist if BOTH endpoints are prose-supported.
```
No gap-filling like:
``` text
others reacted → Shay must have escalated → insert transition
```
Not allowed.

---

## 5. Co-Regulation as Causal Layer

We’ve already separated:
``` text
transition = what changed
coregulation = who influenced it
```
Now we formalize interaction with POV constraints.

---

## 6. Correct Linkage (Finalized)
   ### 6.1 Direction
``` text
   entity_coregulation_event
   → may cause →
   entity_state_transitions_event
```
### 6.2 Schema
``` sql
   entity_coregulation_event.caused_transition_id NULL
```
### 6.3 Semantics
| Case	 | Meaning |
|-------|---------| 
   | NULL	| influence attempt / ambient regulation |
   | SET	| contributed to this specific transition|

---

## 7. Critical POV Interaction Rule

This is where your system becomes writer-aware, not just data-aware.

### 7.1 Asymmetry
``` text
Shay → can cause transitions in others
Others → cannot define Shay’s internal state without prose
```
### 7.2 Allowed Pattern
``` text
Shay (action/silence/presence)
→ coregulation_event
→ causes →
Other Character transition
```
### 7.3 Restricted Pattern
``` text
Other Character reacts to Shay
→ infer Shay limbic state
→ create transition

❌ DISALLOWED
```
## 8. Trigger Facts vs Co-Regulation vs Causality

From the limbic doc:
``` text
trigger = what induced state (fact-level)
```
Now:

| Layer	          | Table	                          | Meaning                             |
|-----------------|---------------------------------|-------------------------------------|
| State	          | entity_linked_facts_event	      | what is true                        |
| Trigger	        | entity_linked_facts_event	      | what contributed                    |
| Transition	     | entity_state_transitions_event	 | what changed                        |
| Co-regulation	  | entity_coregulation_event	      | who influenced                      |
| Causality link	 | FK	                             | which influence caused which change |

## 9. Writing-System Consequence (This is the big shift)

This model enforces:

### 9.1 Shay becomes a gravitational POV center
Her interiority is sparse and precise
Her external impact is rich and traceable
### 9.2 Other characters become readable systems
They can:
* escalate
* regulate
* destabilize
* mirror

in response to Shay—even when Shay remains opaque

### 9.3 Resulting Narrative Texture

We get:
``` text
high external emotional resolution
+
selective internal opacity
```
Which is exactly what strong POV-limited prose does.

## 10. What We Must Not Break

From limbic documentation:

Do not store derived transitions

Refinement (not contradiction):

Do not store ALL transitions by default
DO store transitions when:
- causality is being explicitly modeled
- narrative significance exists
- suggestion is accepted
---
## 11. Minimal Next Step (Concrete)

Implement:
``` sql
ALTER TABLE entity_coregulation_event
ADD COLUMN caused_transition_id BIGINT NULL;
```

And add one enforcement rule in our pipeline:
``` text
IF subject = Shay
AND evidence_mode = inferred_external
THEN reject write
```
That alone will enforce the POV boundary across the system.

## 12. Why This Works

This design avoids three common failure modes:

### 1. Over-psychologizing the POV character

→ prevented by evidence constraint

### 2. Flattening causality

→ solved by explicit co-regulation → transition link

### 3. Treating all characters symmetrically

→ broken intentionally (correctly)

# Limbic System — POV Constraints & Suggestion Layer

File: private/docs/prose/limbic_pov.md

## Overview

This document defines POV-aware constraints on the limbic system model, with a specific focus on:
``` text
Shay as a bounded POV character (Books 1–3)
```
It extends:
``` text
private/docs/prose/limbic_documentation.md
```
without modifying its core rules.


## Core Principle

The system must distinguish between:
``` text
observed state   → prose-grounded, storable
inferred state   → model-generated, non-storable (for POV-bound subjects)
causal influence → interaction-driven, independently storable
```
## Shay as POV-Bounded Entity
### Profile Constraint
``` text
entity: character:Shay

profile_constraint:
type: pov_bounded
scope: books_1_3
rule: internal_state_requires_prose_evidence
```
___

## Hard Boundary Rule
``` text
No inferred limbic state for Shay may be stored as a fact.
```

This includes inferences derived from:

* other characters’ reactions
* scene tone
* dialogue patterns
* narrative expectations
___

## Suggestion vs Fact Model
### Required Separation
``` text
SUGGESTION ≠ FACT
```
| Layer	      | Table	                                 | Meaning                          |
|-------------|----------------------------------------|----------------------------------|
| Fact	       | entity_linked_facts_event	             | Canonical, prose-supported truth |
| Suggestion	 | entity_limbic_state_suggestions_event	 | Model inference, not committed   |

## Suggestion Layer
### Purpose

Allow the system to reason about Shay’s likely internal state **without violating POV constraints.**

### Table
``` sql
entity_limbic_state_suggestions_event
```
### Semantics
suggestion:
- may be wrong
- may contradict future prose
- must never be auto-promoted to fact
___

## Promotion Rule

A suggestion becomes a fact only if:

explicit or sufficiently grounded prose evidence appears

Pipeline:

suggestion → WAIT → prose support → fact insert

Never:

suggestion → direct fact
Evidence Modes

All limbic facts conceptually fall into:

- prose_explicit
- prose_close_pov
- inferred_external
  Enforcement
  IF subject = Shay
  THEN inferred_external is not allowed for stored facts
  ## CPTSD Constraint: Delayed Regulation
  ### Critical Rule
  Shay regulating others is NOT evidence that Shay is regulated.
  ### Decoupling Principle
  external regulation capacity
  ≠
  internal limbic state
  ### Disallowed Inferences for Shay

Do NOT infer Shay is regulated from:

others calming down around her
Shay speaking steadily or clearly
Shay organizing or structuring a situation
Shay de-escalating conflict
reduction of external tension

These support:

Shay → source of co-regulation (valid)

But NOT:

Shay → internally regulated (invalid unless prose-supported)
Valid Evidence for Shay Regulation

Shay may be considered regulated only when supported by prose signals such as:

physiological settling (breath, body)
reduction in threat anticipation
cessation of hypervigilance (“waiting for the other shoe to drop”)
internal quieting
restored agency or choicefulness
explicit narrative confirmation
Co-Regulation Model Interaction
Allowed
Shay → coregulation → other_character → transition

This is fully valid even when Shay’s internal state is unknown.

Restricted
other_character reacts → infer Shay state → store

Disallowed.

Shay as Target
coregulation → Shay → transition

Allowed ONLY if:

the resulting Shay transition is prose-supported

Otherwise:

store coregulation event WITHOUT transition
Transitions
From Limbic Documentation

Transitions are derived, not stored by default

Extended Rule
Transitions may be materialized when:
- causality is being explicitly modeled
- narrative significance exists
  Shay Constraint
  A Shay transition may only be stored if BOTH states are prose-supported.

No inferred bridging.

Trigger Facts vs Co-Regulation
Concept	Table	Role
Limbic state	entity_linked_facts_event	what is true
Trigger	entity_linked_facts_event	what contributed
Transition	entity_state_transitions_event	what changed
Co-regulation	entity_coregulation_event	who influenced
Suggestion	entity_limbic_state_suggestions_event	what is hypothesized
Writing-System Effect

This model intentionally creates asymmetry:

Shay
low explicit internal state
high constraint
suggestion-rich shadow layer
Other Characters
high observable state
high inferential flexibility
full transition modeling
Narrative Outcome
External emotional clarity
+
Internal POV restraint

Shay becomes:

a causal center with constrained interior visibility
Enforcement Summary
Required System Rules
1. Shay facts require prose evidence
2. Shay inferred states → suggestions only
3. No auto-promotion from suggestion → fact
4. External regulation ≠ internal regulation (CPTSD rule)
5. Shay transitions require prose-supported endpoints
   Design Intent

This is not just data hygiene.

It ensures:

the system does not over-interpret the POV character
trauma dynamics (delayed settling, hypervigilance) are preserved
causality remains expressive without corrupting interior truth
Final Principle
The system may think beyond the prose.
It may not write beyond the prose.

Pre-Enforcement Operating Mode

Append this section to limbic_pov.md:

Pre-Enforcement Operating Mode

This system is currently not enforced at the database or CI level.

All rules in this document are therefore:

normative (must be followed)
but not yet programmatically enforced
Practical Implication

The system operates in two layers:

1. Storage layer (permissive)
2. Modeling discipline (strict)

Meaning:

The database can accept invalid writes
The process must not produce them
Required Behavioral Discipline

Until enforcement exists, the following must be treated as manual invariants:

- Do not insert inferred Shay limbic states into entity_linked_facts_event
- Always route Shay inferences into the suggestion layer
- Do not materialize Shay transitions without prose evidence
- Do not equate external regulation with internal regulation

Violations will not fail technically—they will corrupt the model.

Soft-Gating Pattern (Interim)

Before inserting any Shay limbic fact, require an explicit check:

evidence_check:
- Is this directly supported by prose?
- Can it be pointed to in text?

If the answer is not clearly yes:

→ write to suggestion table instead
Annotation Requirement

All Shay limbic facts should include:

notes:
- short justification
- reference to scene, line, or narrative signal

This acts as a human-auditable substitute for CI.

Suggestion Visibility Rule

Suggestions must remain visible and queryable.

They are not second-class—they are:

the system thinking ahead of the prose

But they must remain clearly separated from:

what the story has actually committed to.

