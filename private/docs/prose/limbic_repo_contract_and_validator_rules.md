## repo contract

Limbic write boundary:

1. entity_linked_facts_event
   Canonical event-scoped facts only.

2. entity_limbic_state_suggestions_event
   Inferred/uncertain states only.
   Never treated as truth.

3. entity_state_transitions_event
   Materialised meaningful state changes only.
   Requires existing from/to facts.

4. entity_coregulation_event
   Regulatory interaction between characters.
   May point to a caused transition.

## validator rules

FACT INSERT
- reject if basis_type is inference
- require source_document
- require context_entity_id
- require object_entity_id
- if subject is POV-bounded, require prose-backed evidence

SUGGESTION INSERT
- allow inference
- require basis_type
- require confidence
- must not write fact rows

PROMOTION
- require explicit endpoint/action
- create fact
- mark suggestion promoted
- set promoted_to_fact_id
- no automatic promotion

TRANSITION INSERT
- require from_object_entity_id
- require to_object_entity_id
- verify both are existing facts for same subject/context trajectory
- do not invent missing states

COREGULATION INSERT
- source = regulator
- target = regulated subject
- if caused_transition_id exists, transition.subject_entity_id must equal target_entity_id