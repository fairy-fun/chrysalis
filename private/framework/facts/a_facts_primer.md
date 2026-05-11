# Chrysalis Fact Resolver Architecture

## Purpose

`fact_resolver.php` is the read-side companion to:

```text
private/framework/facts/apply_fact.php
```

Its purpose is:

* canonical-head resolution
* lineage-aware fact lookup
* adjudication-aware canon retrieval
* centralized epistemic read policy

It does NOT:

* write facts
* adjudicate truth
* mutate governance state
* overwrite historical assertions

The resolver exists to answer:

> “Given the historical lineage of assertions, what is the current canonical head?”

---

# Architectural Separation

The system now has a clean epistemic split.

## Write Side

```text
apply_fact.php
```

Responsibilities:

* insert assertions
* validate lineage targets
* persist governance metadata
* enforce ontology-local lineage

This layer creates history.

---

## Read Side

```text
fact_resolver.php
```

Responsibilities:

* canonical lookup
* lineage traversal
* governance-aware retrieval
* future canon policy enforcement

This layer interprets history.

---

# Core Canonical Principle

Canonical truth is NOT:

* newest row
* highest ID
* latest insert
* highest confidence

Canonical truth is:

> an unsuperseded lineage head under governance policy

Current canonical-head logic:

```sql
AND NOT EXISTS (
    SELECT 1
    FROM entity_linked_facts_global newer
    WHERE newer.supersedes_linked_fact_id = f.linked_fact_id
)
```

Meaning:

> “Return only facts that are unsuperseded lineage heads.”

---

# Global Resolver

## Function

```php
resolve_canonical_global_fact(...)
```

## Surface

```text
entity_linked_facts_global
```

## Intended Use

Context-independent facts.

Examples:

* character alive/dead
* faction membership
* permanent relationships
* stable world-state assertions

---

## Example

```php
resolve_canonical_global_fact(
    $pdo,
    'entity:shay',
    'fact_type:has_role'
);
```

This asks:

> “What is the current canonical role assertion for Shay?”

---

# Event Resolver

## Function

```php
resolve_canonical_event_fact(...)
```

## Surface

```text
entity_linked_facts_event
```

## Intended Use

Contextualized assertions.

Examples:

* appearance during scene
* actions during event
* event-local interactions
* scene-scoped observations

---

## Example

```php
resolve_canonical_event_fact(
    $pdo,
    'entity:shay',
    'calendar_event:42611',
    'fact_type:appears_at'
);
```

This asks:

> “What is the canonical appearance assertion for Shay during this event?”

---

# Why Event and Global Remain Separate

Lineage is ontology-local.

This is a critical system invariant.

The system must NEVER allow:

```text
event fact
    supersedes
global fact
```

or:

```text
global fact
    supersedes
event fact
```

These represent fundamentally different epistemic surfaces.

Global facts model:

* persistent canon
* stable world-state

Event facts model:

* contextual truth
* local narrative state

Keeping them separate prevents ontology corruption.

---

# Optional Object Filtering

Resolvers optionally accept:

```php
?string $objectEntityId = null
```

This allows two distinct query modes.

---

## Mode 1 — Current Fact Type Head

```php
resolve_canonical_global_fact(
    $pdo,
    'entity:shay',
    'fact_type:has_role'
);
```

Meaning:

> “What is the current role assertion?”

---

## Mode 2 — Specific Assertion Validation

```php
resolve_canonical_global_fact(
    $pdo,
    'entity:shay',
    'fact_type:has_role',
    'role:follow_8'
);
```

Meaning:

> “Is this specific assertion still canonical?”

This distinction becomes important for:

* contradiction review
* AI memory retrieval
* speculative inference validation
* gameplay/roleplay systems
* ontology audits

---

# Accepted Canon Filtering

Resolvers optionally support:

```php
bool $acceptedOnly = false
```

This is extremely important.

---

## acceptedOnly = false

Meaning:

> “Return the current unsuperseded lineage head regardless of governance state.”

This includes:

* unreviewed assertions
* speculative AI suggestions
* provisional ontology
* unresolved contradictions

Useful for:

* governance tooling
* review dashboards
* author inspection

---

## acceptedOnly = true

Meaning:

> “Return only accepted canonical truth.”

This applies the framework’s canonical accepted-governance filter.

Runtime code must not hardcode adjudication classval IDs directly. Accepted-canon filtering resolves through:

```text
private/framework/facts/fact_governance.php
```

The resolver should use the governance default returned by:

`governance_default_adjudication_status()`

rather than embedding a literal such as:

`adjudication_status_accepted`

or:

`adjudication_status_approved
`
#### Important:

Accepted canon is not created merely because a fact exists.

Accepted canon should remain:

* author-governed
* adjudicated
* explicitly accepted by the governance layer
* 
---

# Example Lineage

## Historical State

```text
Fact 10:
Shay is Follow #7

Fact 22:
Shay is Follow #8
supersedes Fact 10
```

Result:

* Fact 10 remains historical
* Fact 10 is no longer canonical-head
* Fact 22 becomes current canonical-head

Nothing is deleted.

This preserves:

* canon evolution
* historical auditability
* semantic succession
* provenance continuity

---

# Why This Matters

Without lineage-aware resolution:

the system becomes:

* overwrite-driven
* historically destructive
* incapable of adjudication
* incapable of competing interpretations

With lineage-aware resolution:

the system can support:

* historical truth evolution
* unresolved contradictions
* speculative inference
* adjudicated canon
* POV-relative cognition
* AI-assisted memory systems
* provenance-aware ontology evolution

This transitions the architecture from:

```text
fact storage
```

to:

```text
epistemic governance infrastructure
```

---

# Future Resolver Expansion

The resolver layer is expected to evolve into policy-aware canon resolution.

Likely future concepts:

```php
[
    'accepted_only' => true,
    'allow_provisional' => false,
    'respect_authority_domains' => true,
    'timeline_scope' => 'current',
]
```

Future canonical truth may become:

> policy-valid lineage head under policy constraints

rather than merely:

> unsuperseded lineage head

---

# Important Future Work

## Semantic Compatibility Validation

Future invariant:

superseding facts should share:

* subject
* fact type
* context (for event facts)

Potentially later:

* compatible object ontology

---

## Cycle Prevention

Future invariant:

facts cannot supersede themselves

and eventually:

no lineage cycles

---

## Temporal Truth Semantics

Eventually the system may require:

```text
effective_from
effective_until
timeline_position
canon_epoch
```

because some facts are:

temporally distinct truths

rather than contradictions.

Example:

```text
Alice alive
Alice dead
```

These are not contradictory across timeline space.

They are sequential truths.

---

# Strategic Importance

The resolver layer is the beginning of:

* adjudicated machine memory
* governed narrative cognition
* provenance-aware semantic retrieval
* canon-safe AI reasoning

This is not merely a convenience abstraction.

It is the foundation of the system’s future epistemic architecture.
