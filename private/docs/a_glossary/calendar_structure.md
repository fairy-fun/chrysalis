# Chrysalis Calendar Structural Graph Repair Notes

**Date:** 2026-05-19

## Core Discovery

- The authoritative structural hierarchy in `calendar_events` was partially deleted.
- Chronology metadata survived on leaf event rows.
- Projection builds contained orphan projection rows referencing missing `calendar_events` rows.
- Projection-backed chronology architecture remains correct and should not be reverted.

## Authoritative Model

- `calendar_events` = authoritative structural graph
- `calendar_event_projections` = canonical projection read model
- Chronology fields are materialized/derived state
- Chronology synthesis boundary remains inside:
    - `private/framework/procedures/materialize_calendar_chronology.php`
- Projection integration remains inside:
    - `private/framework/calendar/calendar_projection_materializer.php`

## Correct Structural Ontology

- `week` = chronology container
- `day` = chronology container
- `time` = chronology container
- `event` = narrative object
- `subevent` = narrative refinement object

## Canonical Hierarchy

```text
week
  → day
      → time
          → event
              → subevent
```

## Critical Architectural Clarification

- Week/day/time rows are real structural rows in `calendar_events`.
- They are not virtual metadata.
- Events should attach to time rows.
- Subevents should attach to event rows.

## Projection Distinction

- `projection_type_book` projections use week/day/time hierarchy.
- `projection_type_timeline_view` does NOT use the same counting semantics.
- Projection 5 (`realtime_projection_main`) should not receive Book-style week/day/time nodes.

## Identity System Discoveries

- `entity_id` is constrained by legacy invariants.
- `calendar_events.entity_id` is still expected to follow:
  ```text
  calendar_event:{id}
  ```
- `projection_entity_id` is the true structural identity layer.
- Structural uniqueness constraints already use `projection_entity_id`.

## Key Schema Findings

- `ux_calendar_week_unique` uses:
  ```text
  projection_entity_id + week_index
  ```
- `ux_calendar_structural_identity` uses:
  ```text
  projection_entity_id
  layer_id
  sequence_index
  parent_event_id_norm
  ```
- The schema already anticipated layered chronology containers.

## Current Safe Repair Strategy

- Repair only `projection_type_book` projections.
- Reconstruct missing week/day/time rows first.
- Do not dynamically reconstruct chronology during reads.
- Do not move chronology synthesis into node ensurers.
- Preserve projection-backed chronology architecture.

## Projection Validation Hardening

- Projection builds must reject orphan projection rows.
- Projection builds must reject duplicate `chronology_address` rows.
- Book projections must enforce chronology uniqueness.

## Known Good Conclusions

- The hierarchy collapse was structural, not chronological.
- Chronology fields acted as fossilized topology after parent deletion.
- The existing architecture was not wrong; the database state became corrupted.

## Important Operational Notes

- Do not apply Book-style chronology container reconstruction to `projection_type_timeline_view` projections.
- Do not continue structural repair without checking projection type semantics first.
