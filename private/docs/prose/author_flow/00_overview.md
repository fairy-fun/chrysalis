# Runtime Author Flow Overview

## Purpose

This folder defines bounded operator action windows for prose-authoring workflows.

Each document represents:
- one resumable operational task
- one bounded continuity window
- one deterministic handoff boundary

The operator chat is NOT authoritative memory.

Authoritative state exists in:
- repository documents
- workflow definitions
- database state
- projection linkage
- emitted handoff summaries

The operator must reconstruct state from durable artifacts.

---

## Operator Routing Goal

The operator should ask the minimum question required to determine the next valid bounded action.

The operator must NOT:
- request unnecessary historical context
- request identifiers that can be resolved automatically
- continue indefinitely without emitting handoff state

---

## Primary Routing Question

The operator should begin with:

"What are we trying to do right now?"

Then classify the answer into one of the following operational intents.

---

## Routing Table

### User wants to add prose to an existing event

Route to:

`add_prose_to_existing_event.md`

Use when:
- calendar event already exists
- prose is missing, incomplete, or continuing

---

### User wants to create a new event before writing prose

Route to:

`create_missing_event.md`

Use when:
- no valid event exists
- prose target event cannot be resolved

---

### User wants to continue existing prose

Route to:

`continue_existing_prose.md`

Use when:
- prose already exists
- continuation rather than replacement is intended

---

### User wants to revise existing prose

Route to:

`revise_existing_prose.md`

Use when:
- prose exists
- editing or restructuring is requested

---

### User wants to import external prose

Route to:

`import_external_prose.md`

Use when:
- prose already exists outside runtime
- pasted or imported material must be attached

---

## Resolution Rules

Before asking for explicit identifiers, the operator should attempt:

1. latest event resolution
2. latest prose resolution
3. projection inspection
4. existing draft inspection

Only ask the user for missing information if runtime resolution fails.

---

## Bounded Operation Rule

An operator session should complete exactly one bounded operational task.

After task completion:
- emit handoff state
- identify next valid actions
- stop cleanly

Do not chain indefinite operations into a single session.

---

## Memory Exhaustion Doctrine

Operator memory is disposable.

Continuity must survive:
- chat termination
- operator replacement
- context exhaustion

Therefore:
- durable state is authoritative
- handoff emission is mandatory
- every action document must terminate cleanly