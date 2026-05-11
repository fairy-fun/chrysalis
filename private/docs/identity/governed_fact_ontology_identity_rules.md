# Ontology Identity Rules
## 1. Canonical Rule

Identity-bearing ontology references in governed writes must use canonical UUID-backed identifiers.

Examples:

* epistemic origin
* adjudication status
* governed classification references

Semantic aliases are not permitted for newly-written governed facts unless explicitly grandfathered.

## 2. Grandfathered Historical State

Historical rows may contain legacy semantic identifiers such as:

* epistemic_origin_legacy_imported
* adjudication_status_grandfathered_canon
* contradiction_state_none

These are accepted historical states and are not currently CI violations.

New writes must not introduce additional semantic canonical IDs.

## 3. Resolver and Canonical Semantics

Resolvers consume governed canonical projection layers rather than
reconstructing canonicality ad hoc.

Canonical semantics are defined by:

* governed projection views
* accepted supersession state
* immutable lineage traversal

A fact remains canonical unless superseded by an ACCEPTED successor.

Canonicality is therefore governance-aware rather than purely structural.

Timestamp ordering is not authoritative.

## 4. Supersession Rules

Corrections create new rows rather than mutating existing governed facts.

Required behavior:

* preserve historical lineage
* maintain immutable fact history
* resolve active truth through supersession traversal
## 5. Write-Path Enforcement

All governed writes must flow through:

* `applyGovernedGlobalFact`
* `applyGovernedEventFact`

Direct table writes are non-compliant.

## 6. Contradiction State Exception

`contradiction_state_*` currently behaves differently from UUID-backed ontology references.

Document explicitly whether:

* it is transitional
* canonical semantic
* or pending UUID migration

This is the one area future developers are most likely to misunderstand.

The system effectively has:

| Layer	        | Meaning                      |
|---------------|------------------------------|
| supersession	 | historical succession        |
| adjudication	| governance acceptance        | 
|contradiction state	| unresolved semantic conflict |
|canonical projection	| governed operational truth   |

## 7. CI Authority

CI is the enforcement authority for ontology identity compliance.

Developer assumptions do not override:

* audit classification
* governance checks
* resolver invariants

If CI passes, the ontology state is considered policy-compliant.
## 8. Projection Authority

Canonical truth semantics are centralized in projection views.

Application resolvers should consume:

* canonical_entity_linked_facts_global
* canonical_entity_linked_facts_event

rather than recomputing supersession semantics independently.

This prevents semantic drift between write-path governance,
resolver behavior, and CI enforcement.

## 10. Lineage Doctrine

* immutable provenance chains
* recursive traversal expectations
* ancestry semantics
* historical archaeology

## 11. Migration Guidance

Future migrations should:

* preserve supersession chains
* avoid mutating historical canonical rows
* prefer additive correction rows
* avoid introducing new semantic aliases

be aligned to:
*   governed projections
*   accepted supersession semantics
*   centralized canonicality
*   lineage traversal doctrine
*   contradiction separation semantics