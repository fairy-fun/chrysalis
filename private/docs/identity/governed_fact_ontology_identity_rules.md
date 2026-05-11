Ontology Identity Rules
1. Canonical Rule

Identity-bearing ontology references in governed writes must use canonical UUID-backed identifiers.

Examples:

epistemic origin
adjudication status
governed classification references

Semantic aliases are not permitted for newly-written governed facts unless explicitly grandfathered.

2. Grandfathered Historical State

Historical rows may contain legacy semantic identifiers such as:

epistemic_origin_legacy_imported
adjudication_status_grandfathered_canon
contradiction_state_none

These are accepted historical states and are not currently CI violations.

New writes must not introduce additional semantic canonical IDs.

3. Resolver Semantics

Resolvers operate on canonical-head resolution.

A fact is canonical iff:

no newer row supersedes it through supersedes_linked_fact_id

Canonical resolution uses anti-join semantics rather than timestamp ordering.

4. Supersession Rules

Corrections create new rows rather than mutating existing governed facts.

Required behavior:

preserve historical lineage
maintain immutable fact history
resolve active truth through supersession traversal
5. Write-Path Enforcement

All governed writes must flow through:

applyGovernedGlobalFact
applyGovernedEventFact

Direct table writes are non-compliant.

6. Contradiction State Exception

contradiction_state_* currently behaves differently from UUID-backed ontology references.

Document explicitly whether:

it is transitional
canonical semantic
or pending UUID migration

This is the one area future developers are most likely to misunderstand.

7. CI Authority

CI is the enforcement authority for ontology identity compliance.

Developer assumptions do not override:

audit classification
governance checks
resolver invariants

If CI passes, the ontology state is considered policy-compliant.

8. Migration Guidance

Future migrations should:

preserve supersession chains
avoid mutating historical canonical rows
prefer additive correction rows
avoid introducing new semantic aliases