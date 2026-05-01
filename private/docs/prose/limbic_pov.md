# POV-Bounded Limbic & Causality Model

_(Shay as constrained interior, others as observable systems)_

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

transitions are derived, not stored

But your system is evolving beyond that. The correct refinement is:

### 4.1 Dual Nature of Transitions
derived_transition = default (query-time)
materialized_transition = optional (when narratively or causally important)

Stored in:

entity_state_transitions_event
4.2 Transition Validity Rule

A transition is valid if:

state(t1) and state(t2) both exist as event facts
4.3 POV Constraint on Transitions

For Shay:

A transition may only exist if BOTH endpoints are prose-supported.

No gap-filling like:

others reacted → Shay must have escalated → insert transition

Not allowed.

5. Co-Regulation as Causal Layer

You’ve already separated:

transition = what changed
coregulation = who influenced it

Now we formalize interaction with POV constraints.

6. Correct Linkage (Finalized)
   6.1 Direction
   entity_coregulation_event
   → may cause →
   entity_state_transitions_event
   6.2 Schema
   entity_coregulation_event.caused_transition_id NULL
   6.3 Semantics
   Case	Meaning
   NULL	influence attempt / ambient regulation
   SET	contributed to this specific transition
7. Critical POV Interaction Rule

This is where your system becomes writer-aware, not just data-aware.

7.1 Asymmetry
* Shay → can cause transitions in others
* Others → cannot define Shay’s internal state without prose
7.2 Allowed Pattern
Shay (action/silence/presence)
→ coregulation_event
→ causes →
Other Character transition
7.3 Restricted Pattern
Other Character reacts to Shay
→ infer Shay limbic state
→ create transition

❌ DISALLOWED
8. Trigger Facts vs Co-Regulation vs Causality

From the limbic doc:

trigger = what induced state (fact-level)

Now:

Layer	Table	Meaning
State	entity_linked_facts_event	what is true
Trigger	entity_linked_facts_event	what contributed
Transition	entity_state_transitions_event	what changed
Co-regulation	entity_coregulation_event	who influenced
Causality link	FK	which influence caused which change
9. Writing-System Consequence (This is the big shift)

This model enforces:

9.1 Shay becomes a gravitational POV center
Her interiority is sparse and precise
Her external impact is rich and traceable
9.2 Other characters become readable systems
They can:
escalate
regulate
destabilize
mirror

in response to Shay—even when Shay remains opaque

9.3 Resulting Narrative Texture

You get:

high external emotional resolution
+
selective internal opacity

Which is exactly what strong POV-limited prose does.

10. What You Must Not Break

From limbic documentation:

Do not store derived transitions

Refinement (not contradiction):

Do not store ALL transitions by default
DO store transitions when:
- causality is being explicitly modeled
- narrative significance exists
11. Minimal Next Step (Concrete)

Implement:

ALTER TABLE entity_coregulation_event
ADD COLUMN caused_transition_id BIGINT NULL;

And add one enforcement rule in your pipeline:

IF subject = Shay
AND evidence_mode = inferred_external
THEN reject write

That alone will enforce the POV boundary across the system.

12. Why This Works

This design avoids three common failure modes:

1. Over-psychologizing the POV character

→ prevented by evidence constraint

2. Flattening causality

→ solved by explicit co-regulation → transition link

3. Treating all characters symmetrically

→ broken intentionally (correctly)