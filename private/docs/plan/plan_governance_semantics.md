## Phase 1 — Highest Priority

These clearly represent semantic/narrative truth-bearing entities.

Core semantic entities
characters
entities
places
relationships
events
calendar_events
dreams
works
books
prose_drafts
prose_projections
calendar_event_projections
calendar_projections
Knowledge / inference structures
calendar_event_knowledge
relationship_knowledge_map
entity_linked_facts_event
entity_linked_facts_global
narrative_theme_observations
theme_transition_observations
dream_theme_instances
dream_tone_instances
idea_classifications
Semantic annotations / authored interpretation
entity_texts
entity_labels
semantic_aliases
prose_annotation_spans
character_profile_attributes
calendar_event_attributes
## Phase 2 — Strongly Recommended

These appear to contain generated or inferred semantic structures.

event_clusters
theme_clusters
theme_cluster_membership
segment_groups
segments
medleys
medley_segments
framework_applications
expression_constraint_outputs
expression_constraint_runs
## Phase 3 — Optional / Depends On Workflow

Only if these become cognition-driven.

figures
figure_transitions
calendar_relationships
calendar_choreography_links
choreography_relationships
residences
status_history
relationship_status_history
Tables To Avoid

These are operational/transport/system tables and should NOT receive governance semantics.

Examples:

request_context
system_procedure_registry
sql_snippets
repair_*
tmp_*
*_audit
*_history (usually)
entity_traversal_*
nl_query_parser_configs
team_*
company_*
auth/session/admin structures
Recommended First Migration Batch

This is the safest/highest-value starting set:

ALTER TABLE characters
ADD COLUMN epistemic_origin_classval_id VARCHAR(64) NULL,
ADD COLUMN adjudication_status_classval_id VARCHAR(64) NULL;

ALTER TABLE entities
ADD COLUMN epistemic_origin_classval_id VARCHAR(64) NULL,
ADD COLUMN adjudication_status_classval_id VARCHAR(64) NULL;

ALTER TABLE places
ADD COLUMN epistemic_origin_classval_id VARCHAR(64) NULL,
ADD COLUMN adjudication_status_classval_id VARCHAR(64) NULL;

ALTER TABLE relationships
ADD COLUMN epistemic_origin_classval_id VARCHAR(64) NULL,
ADD COLUMN adjudication_status_classval_id VARCHAR(64) NULL;

ALTER TABLE events
ADD COLUMN epistemic_origin_classval_id VARCHAR(64) NULL,
ADD COLUMN adjudication_status_classval_id VARCHAR(64) NULL;

ALTER TABLE calendar_events
ADD COLUMN epistemic_origin_classval_id VARCHAR(64) NULL,
ADD COLUMN adjudication_status_classval_id VARCHAR(64) NULL;

ALTER TABLE dreams
ADD COLUMN epistemic_origin_classval_id VARCHAR(64) NULL,
ADD COLUMN adjudication_status_classval_id VARCHAR(64) NULL;

ALTER TABLE prose_drafts
ADD COLUMN epistemic_origin_classval_id VARCHAR(64) NULL,
ADD COLUMN adjudication_status_classval_id VARCHAR(64) NULL;

ALTER TABLE prose_projections
ADD COLUMN epistemic_origin_classval_id VARCHAR(64) NULL,
ADD COLUMN adjudication_status_classval_id VARCHAR(64) NULL;

ALTER TABLE calendar_event_knowledge
ADD COLUMN epistemic_origin_classval_id VARCHAR(64) NULL,
ADD COLUMN adjudication_status_classval_id VARCHAR(64) NULL;

ALTER TABLE entity_linked_facts_event
ADD COLUMN epistemic_origin_classval_id VARCHAR(64) NULL,
ADD COLUMN adjudication_status_classval_id VARCHAR(64) NULL;

ALTER TABLE entity_linked_facts_global
ADD COLUMN epistemic_origin_classval_id VARCHAR(64) NULL,
ADD COLUMN adjudication_status_classval_id VARCHAR(64) NULL;

ALTER TABLE narrative_theme_observations
ADD COLUMN epistemic_origin_classval_id VARCHAR(64) NULL,
ADD COLUMN adjudication_status_classval_id VARCHAR(64) NULL;

ALTER TABLE semantic_aliases
ADD COLUMN epistemic_origin_classval_id VARCHAR(64) NULL,
ADD COLUMN adjudication_status_classval_id VARCHAR(64) NULL;
After This Migration

Next major step should be:

Automatic default assignment logic

Because otherwise all rows remain NULL forever.

You’ll want centralized creation semantics like:

manual author creation:
manual_author_entry + approved

parser extraction:
derived + pending

llm inference:
inferred + pending

projection synthesis:
synthetic + pending

That should likely live:

in entity factories/services
not triggers
not ad hoc controller logic
Important Architectural Point

Once these columns exist, you can begin building:

WHERE adjudication_status = approved

as the canonical query boundary.

That becomes the foundation for:

canon-safe exports
cognition filtering
inference suppression
continuity enforcement
POV-safe retrieval
contradiction management
review queues

You’re effectively introducing epistemology directly into the data model.


An author interview is exactly the correct mechanism before mass backfilling legacy semantic data, because you’re about to formalize:

what counts as authored truth
what counts as imported truth
what counts as inferred structure
what “approved” actually means historically
whether some existing records should intentionally remain epistemically unresolved

The interview should establish durable governance doctrine before the first global update runs.

Good next-chat targets:

legacy corpus classification rules
defaults by entity type
retroactive canon assumptions
treatment of imported/reference material
machine-generated legacy rows
ambiguity preservation policy
Shay-specific POV protections
contradiction handling doctrine
“approved by existence” vs “actively reviewed”
temporal canon semantics

That interview will let you generate deterministic backfill rules instead of arbitrary mass assignment.
Backfill should start with the Phase 1 governed semantic tables:

characters
entities
places
relationships
events
calendar_events
dreams
prose_drafts
prose_projections
calendar_event_knowledge
entity_linked_facts_event
entity_linked_facts_global
narrative_theme_observations
semantic_aliases

Likely default backfill, pending author interview:

epistemic_origin = manual_author_entry
adjudication_status = approved

But do not run that blindly. The author interview should first decide whether any tables contain legacy machine-generated, imported, inferred, or unresolved rows.