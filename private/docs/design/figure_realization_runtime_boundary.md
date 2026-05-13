figures are reusable realization anchors,
not performed choreography occurrences

figure_concepts
abstract dance ontology concepts

figure_realizations
dance-specific realizations

figures
reusable graph/runtime anchors
1:1 with realizations

performed occurrences / choreography placements
temporal uses inside routines/groups/segments

It should also explicitly document:

repetition semantics
branching semantics
simultaneity semantics
why choreography transitions are occurrence-level
why realization identity is reusable
why repeated performance does NOT imply duplicated realization ontology

Most importantly, this doctrine should become the authoritative reason for:

UNIQUE(figures.figure_realization_id)

so future-you does not accidentally undo the decomposition.

I would NOT put this under:

identity/
plan/
audits/
framework_contracts.md

because this is not framework-global doctrine.

It is a domain ontology decomposition document specific to choreography/figure