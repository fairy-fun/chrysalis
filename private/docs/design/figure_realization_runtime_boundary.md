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

### Repetition Identity

Repeated choreography references do not create new ontology identity.

Example:

```text
1. Contra Check
2. Open Reverse Turn
3. Contra Check
```
Both Contra Check references point to the same reusable figure realization anchor.

The distinction between the two usages is choreography position/context
(e.g. sequence ordering inside a segment or routine),
not new figure ontology identity.

Therefore:
```text
repeated usage
!=
new figure identity
```
Ontology identity changes only when the realization itself changes.

Example:
```text
tango_contra_check
!=
vwaltz_contra_check
```
because those are distinct dance-context realizations.

## Segment Sequencing Semantics

`segment_figures` represents choreography sequencing over reusable figure anchors.

Repeated figure usage inside a segment does NOT create:

* new figure ontology
* new figure realizations
* new reusable figure anchors

Example:

```text
Natural Fleckerl
Natural Fleckerl
Natural Fleckerl
```

is represented as:

* multiple choreography sequence positions
* referencing the same reusable figure anchor

where:
```text
sequence_index
```
carries the repetition distinction.

Therefore:
```text
segment sequencing
!=
ontology multiplication
```
segment_figures should be interpreted as choreography sequencing structure,


### Sequence Index Semantics

`sequence_index` represents choreography temporal order within its container.

It does not represent ontology decomposition order.

For repeated figure usage:

```text
Natural Fleckerl
Natural Fleckerl
Natural Fleckerl
```
the repeated rows may reference the same reusable figure anchor, while sequence_index distinguishes their ordered choreographic placement.

Therefore:

sequence_index
=
ordered choreography position
```text
sequence_index
!=
ontology identity
```
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

### Group Divergence Semantics

Group divergence represents choreography/runtime structure, not ontology decomposition.

Example:

```text
Group A:
Open Reverse Turn

Group B:
Closed Promenade
```

This expresses:

* choreography partitioning
* simultaneous execution structure
* runtime group coordination

It does NOT express:

* figure ontology branching
* realization ontology branching
* ontology decomposition

Therefore:
```text
segment_group_figures
```

should be interpreted as choreography grouping/sequencing semantics operating over reusable figure anchors.

The same reusable figure anchor may participate in multiple choreography group contexts without creating new ontology identity.

Repeated figure references across choreography groups do not create new ontology identity.

Multiple `segment_group_figures` rows may intentionally reference:

* the same `figure_id`
* at the same `sequence_index`

when separate choreography groups simultaneously execute the same reusable figure anchor.

Example:

```text
Group A   -> Contra Check
Group B -> Contra Check
```
This represents choreography coordination semantics, not ontology multiplication.

Therefore:
```text
group participation
!=
new figure identity
```

### Group Identity Semantics

Choreography groups represent role/execution partitions within choreography structure.

Groups do NOT represent ontology subtype partitioning.

Examples include:

* leader/follower partitions
* team partitions
* simultaneous choreography roles
* coordinated execution contexts

Therefore:

```text
group context
!=
ontology specialization
```

`segment_group_figures` expresses choreography coordination semantics operating over reusable figure anchors.

A figure does not become a new ontology entity merely because it appears in multiple choreography groups.

## Figure Transition Doctrine

`figure_transitions` does NOT represent choreography branching.

It represents a reusable directed legality graph between reusable figure anchors.

Meaning:

```text
Figure A
    -> Figure B
```
expresses:
```text
Figure B may legally follow Figure A
```
within a given dance/syllabus context.

The transition edge is directional.

Therefore:
```text
A -> B
```
does NOT imply:
```text
B -> A
```
unless explicitly represented separately.

This structure simultaneously supports:

* legal successor queries
* legal predecessor queries

by traversing the directed edge in opposite directions.

Examples:
```text
legal followers of Figure A
```
are transitions where:
```text
predecessor_figure_id = Figure A
```
while:
```text
legal predecessors of Figure B
```
are transitions where:
```text
successor_figure_id = Figure B
```
These are reusable legality semantics.

They are NOT choreography occurrence semantics.

They do NOT represent:

* performed temporal branching
* choreography-instance divergence
* simultaneous runtime execution
* occurrence graph semantics

Therefore:
```text
figure_transitions
!=
runtime choreography branching
```
Branching choreography remains a runtime/performance concern separate from realization ontology and legality graph structure.

### Transitional Identity State

`figure_transitions` currently contains both:

* numeric figure references
* figure entity references

This appears to be transitional migration overlap between:

* relational figure identity
* entity-oriented traversal identity

Current live usage indicates that numeric `figure_id` references remain the primary authoritative transition linkage.

The entity reference columns should be treated as transitional compatibility surfaces until traversal identity migration is finalized.

## Syllabus Semantics

Syllabus structure is independent of choreography runtime sequencing.

A syllabus does not primarily represent pedagogy.

Instead, syllabus structure represents:

* recognized figure inventory
* figure availability within a dance system
* reusable compositional figure structure
* dance-system legality/context

Therefore:

```text
syllabus
!=
runtime choreography
``` 
and:
```text
syllabus
!=
performed sequence execution
``` 
`syllabus_maps` should be interpreted as reusable figure composition/availability structure operating over reusable figure anchors.

It does not represent choreography occurrence sequencing.

| concept      | meaning                               |
|--------------|---------------------------------------|
| choreography | temporal execution structure          |
| syllabus     | recognized figure structure/inventory |

Current live syllabus mappings do not presently contain repeated atomic figure references within the same syllabus figure composition.

The model should not assume that repetition semantics are prohibited unless explicitly enforced by future choreography or syllabus doctrine.

### Syllabus Mapping Identity

`syllabus_maps.atomic_figure_id` should reference reusable figure anchors.

It should not reference choreography occurrence/runtime nodes.

Syllabus mappings describe reusable figure composition and recognized figure structure within a dance system.

They do not represent runtime choreography execution semantics.

Therefore:

```text
syllabus composition
!=
runtime occurrence identity
```
The same reusable figure anchor may participate in:

* syllabus composition
* choreography sequencing
* choreography grouping
* legality transition graphs

without creating new ontology identity.


---

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

The distinction between reusable choreography structure and runtime execution
may depend on dance-domain semantics.

Ordered choreography reuse inside segments does not itself imply new ontology identity.

Future occurrence/runtime layers should only be introduced if choreography execution
requires independent execution-state identity beyond ordered segment composition.

Future systems may introduce explicit occurrence layers such as:

* choreography_occurrences
* routine_figure_occurrences
* performance_nodes
* occurrence_transitions

or equivalent structures.

Those systems represent performed usage.

### Existing Choreography Runtime Structures

The schema already contains explicit choreography/runtime composition structures, including:

* choreography_relationships
* choreography_hierarchy
* choreography_entity_map
* segments
* performance_routines

These systems operate above reusable figure ontology and reusable figure anchors.

In particular:

```text
choreography_relationships
```
already represents entity-level choreography graph semantics, including:

* choreography ordering
* parent/child choreography structure
* choreography relationship semantics
* sequence coordination

Therefore, choreography/runtime semantics are not absent from the schema.

The primary ontology boundary remains:
```text
figure ontology
!=
choreography composition/runtime structure
```
Reusable figure anchors may participate in choreography graphs without creating new ontology identity.

They do not replace realization ontology. 

### Choreography Entity vs Choreography Content

The term choreography in the schema does not exclusively mean
runtime sequencing or figure traversal semantics.

A choreography entity may represent:

* authored choreography work
* choreography ownership/authorship
* choreography attribution
* choreography organizational structure
* choreography metadata relationships

rather than figure sequencing itself.

In particular:

```text
segments
```

represent the atomic choreography content units.

A segment becomes choreography because it composes and sequences
multiple reusable figure anchors.

Therefore:
```text
figure
!=
choreography
```
while:
```text
segment
=
atomic choreography unit
```
`segment_figures` represents the internal figure composition
of choreography content.

By contrast:
```text
choreography_relationships
```
should not automatically be interpreted as runtime traversal edges.

The structure may instead represent choreography entity relationships such as:

* choreographer attribution
* organizational ownership
* entity relationship semantics
* choreography metadata associations

including relationships involving entities sourced from:
```text
characters
```
Therefore:
```text
choreography_relationships
!=
necessarily runtime sequencing graph semantics
```
The choreography content/runtime boundary should therefore distinguish:

| layer	   | meaning              |
|----------|----------------------|
| figures	 | reusable dance units |
|segment_figures	|figure composition inside choreography|
|segments	|atomic choreography content|
|choreography_relationships	|relationships ABOUT choreography entities|
|performance_routines	|assembled performance/routine structures|

This preserves the distinction between:

* choreography content
* choreography metadata
* choreography authorship
* choreography runtime composition

### Routine / Performance Boundary

The live schema currently contains `performance_routines`, not separate `routines` or `performances` tables.

`performance_routines` is not classval identity.

Its primary identity is:

```text
performance_routines.routine_id
```
Classval references on this table, such as:
```text
choreography_type_id
status_classval_id
```
represent ontology/type/status classification, not routine identity.

Therefore, choreography routine identity is runtime/entity-oriented, while classvals remain classification atoms.

This supports the broader boundary:
```text
figure_concepts / figure_realizations
= ontology identity

figures
= reusable figure anchor identity

segment_figures / segment_group_figures
= choreography sequencing over reusable figure anchors

performance_routines
= higher-level choreography routine/program identity

classvals
= type/status/classification atoms
```
Do not treat routines as classvals unless a future migration explicitly introduces a routine ontology registry.

Additional occurrence-layer structures should only be introduced if future runtime systems require identity beyond existing choreography routine/program structures.

## Segment Reuse Doctrine

`segments` represent reusable choreography composition units.

A segment is not merely a runtime occurrence container.

A segment represents reusable authored choreography structure composed from reusable figure anchors.

Therefore:

```text
segment
=
reusable choreography module
```
A segment becomes choreography through the composition and sequencing of multiple reusable figure anchors.

Examples include:

reusable competition passages
reusable medley components
reusable thematic choreography sections
reusable team choreography modules

segment_figures defines the internal figure composition of a segment.

The same segment may potentially participate in:

multiple routines
multiple performances
multiple choreography assemblies
future choreography revisions
alternate program structures

without creating new choreography ontology identity.

Therefore:
```text
segment reuse
!=
new choreography identity
```
The current schema structure supports this interpretation.

In particular:

* `segments` does not presently contain direct routine ownership
* `performance_routines` exists as a higher-level choreography/program structure
* choreography assembly appears intentionally separated from choreography composition

This establishes the boundary:

| layer	   | meaning              |
|----------|----------------------|
| figures	 | reusable dance units |
|segment_figures	|figure composition inside a segment|
|segments	|reusable choreography composition modules|
|performance_routines	|assembled performance/program structures|

Therefore:
```text
performance_routines
```
should be interpreted as higher-level choreography assembly/program identity operating over reusable choreography modules.

A routine may assemble and reuse segments without collapsing segment identity into routine-specific occurrence identity.

This preserves:

* choreography modularity
* reusable composition structure
* routine recombination
* choreography library semantics
* future choreography versioning
* medley/program assembly flexibility

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