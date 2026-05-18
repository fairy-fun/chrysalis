# Add Prose To Existing Event

## Intent

Add new prose to an already-existing calendar event while preserving
projection integrity, prose lineage, and resumable operator continuity.

This document defines a bounded operator action window.

The operator must:
- resolve the target event
- determine existing prose state
- perform exactly one prose operation
- emit resumable handoff state

The operator must NOT:
- redesign runtime behavior
- perform unrelated authoring operations
- continue indefinitely without emitting handoff state

Operator memory is not authoritative.
Durable runtime state is authoritative.