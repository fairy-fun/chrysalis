# Figure Realization Runtime Boundary

## Status

Active doctrine.

This document defines the semantic boundary between:

- reusable dance ontology
- reusable realization entities
- runtime choreography occurrence semantics

This doctrine governs normalization decisions for:

- `figure_concepts`
- `figure_realizations`
- `figures`
- traversal/choreography systems
- future choreography occurrence systems

---

# Core Distinction

A dance figure realization is NOT the same thing as a performed occurrence of that figure inside choreography.

This distinction is mandatory.

The choreography system must distinguish:

1. reusable ontology identity
2. performed temporal occurrence

Failure to separate these layers causes ontology collapse.

---

# Canonical Layering

## 1. figure_concepts

Abstract dance ontology concepts.

Examples:

- Contra Check
- Natural Turn
- Closed Promenade

Concepts are dance-agnostic semantic abstractions.

A concept may have multiple realizations across dance systems.

Examples:

| concept | dance |
|---|---|
| Contra Check | Tango |
| Contra Check | Viennese Waltz |

Concept identity is independent of choreography usage.

---

## 2. figure_realizations

Dance-context realizations of abstract concepts.

A realization defines:

- dance system context
- realization-specific semantics
- realization ontology identity

Examples:

| realization |
|---|
| tango_contra_check |
| vwaltz_contra_check |

A realization is reusable.

A realization is NOT a choreography occurrence.

A realization may appear:

- repeatedly
- simultaneously
- in multiple branches
- in multiple routines
- in multiple segments

without creating new realization ontology.

---

## 3. figures

`figures` represents reusable runtime/entity anchors for figure realizations.

Doctrine:

```text
figures are reusable realization anchors,
not performed choreography occurrences
```

Expected relationship:
```text
figure_realizations 1:1 figures
```

Therefore:
```text
UNIQUE(figures.figure_realization_id)
```
is semantically correct.

The `figures` table exists to support:

* traversal identity
* reusable graph identity
* choreography references
* entity-oriented runtime systems

without duplicating realization ontology.

## Repetition Semantics

Repeated choreography usage does NOT imply duplicated figure ontology.

Example:
```text
Natural Fleckerl
Natural Fleckerl
Natural Fleckerl
Natural Fleckerl
```
means:

* one reusable realization
* referenced four times temporally

NOT:

* four different figure realizations
* four different ontology entities

This is equivalent to:
```text
play note C four times
```
which does not create four ontology notes.

## Branching Semantics

Choreography branching operates on performed occurrences, not realization ontology.

Example:
```text
Group A + Group B:
Contra Check

then:

Group A:
Open Reverse Turn

Group B:
Closed Promenade
```
This represents:

* simultaneous performed occurrences
* choreography-instance branching
* temporal/runtime graph behavior

NOT ontology decomposition.

Therefore traversal branching semantics belong to choreography/runtime layers, not figure realization ontology.

## Runtime Occurrence Doctrine

The choreography system conceptually requires occurrence-level semantics.

Examples include:

* repeated usage
* simultaneity
* branching
* rejoining
* temporal ordering
* group divergence

These are runtime/performance semantics.

They are NOT realization ontology semantics.

Future systems may introduce explicit occurrence layers such as:

* choreography_occurrences
* routine_figure_occurrences
* performance_nodes
* occurrence_transitions

or equivalent structures.

Those systems represent performed usage.

They do not replace realization ontology.

## Current Transitional State

The current schema still contains legacy overlap between:

* reusable realization identity
* traversal identity
* choreography runtime semantics

The decomposition is intentionally incremental.

Current bridge state:

* figures.classval_id
* figures.dance_id
* figures.canonical_name

remain temporarily for compatibility.

These are legacy compatibility surfaces.

They must not be treated as final ontology authority.

## Canonical Authority

Canonical ontology authority is now layered:

|Layer	|Authority|
|-------|---------|
|abstract semantic concept	|figure_concepts|
|dance realization ontology	|figure_realizations|
|reusable runtime/entity anchor	|figures|
|choreography/performance occurrence	|runtime choreography systems|

## Prohibited Collapses

The system must NOT collapse:

### prohibited
```text
realization ontology
==
performed occurrence
```
### prohibited
```text
repeated performance
==
new ontology identity
```
### prohibited
```text
branching choreography
==
ontology branching
```
## Migration Guidance

During migration:

* figures.classval_id remains legacy compatibility state
* realization identity authority moves to:
` figure_realizations.realization_classval_id
` 
* choreography semantics must not be normalized into realization ontology
## Enforcement Intent

This doctrine exists to prevent future ontology collapse between:

* abstract dance semantics
* dance-context realizations
* reusable traversal identity
* choreography runtime occurrence semantics

All future normalization work must preserve these separations.