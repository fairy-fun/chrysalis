# How to Tag a Character in an Event

## Purpose

This guide explains how to tag a character in a calendar event using the prose-processing workflow.

In the current repo contract, character tagging is a two-step approval process:

1. suggest character identities from prose
2. approve participant roles relative to the event

The workflow persists approved results to `calendar_event_participants`.

## When to Use This

Use this flow when:

- a calendar event already exists
- prose is already attached to that event
- you want the event to record which characters participate
- you optionally want to record the participant's role in the event

Do not use this flow to create a new character.

Do not use this flow if the event has no attached prose yet.

## Current Workflow Path

The current workflow path is:

`calendar_event_suggest_characters`

followed by:

`calendar_event_approve_character_tags`

The suggest workflow is advisory only.

The approve workflow is the persistence boundary.

## Required Input

You need the calendar event entity ID.

Example:

`calendar_event:142`

or the bare event entity id if the workflow surface accepts it.

## Step 1: Generate Character Suggestions

Run:

`calendar_event_suggest_characters`

This workflow:

- validates the event
- loads the primary attached prose
- detects character surface forms
- resolves those surface forms to canonical character entities

Important:

The suggest step only produces identity suggestions.

It does **not**:

- apply participant links
- apply participant roles
- mutate character ontology

## Step 2: Review Identity Suggestions

The suggestion output should be treated as advisory.

Review:

- `resolved_entity_id`
- `candidate_label`
- `surface_forms`

At this stage you are deciding:

- which suggested characters really belong in the event

You are **not yet** deciding persistence.

## Step 3: Approve Character Tags

Run:

`calendar_event_approve_character_tags`

This workflow re-validates the event and prepares the resolved character suggestions for approval.

You can approve:

- all resolved suggestions
- a subset of resolved suggestions
- all except a named entity

Examples:

- `yes`
- `CHAR-MAIN-1004`
- `all except CHAR-MAIN-1004`
- `no`

If identity approval is rejected, no participant links are written.

## Step 4: Approve Event-Relative Roles

After identity approval, the workflow may prepare suggested participant roles.

These roles are event-relative, not global character attributes.

Examples of role vocabulary come from:

- `calendar_relation_role_classvals`

Example role codes:

- `role_observer`
- `role_initiator`
- `role_instructor`
- `role_student`
- `role_subject`

You can respond with:

- `yes`
- `apply identities only`
- `reject all role assignments`
- `CHAR-MAIN-1004 as role_observer`

If you approve identities but do not approve a role, the participant link can still be written.

## Step 5: Persistence

Approved character tagging writes to:

- `calendar_event_participants`

The current workflow persists:

- participant identity
- approved `role_id` when one is confirmed

If no role is approved:

- the participant row may still be created
- `role_id` may remain empty

## What This Flow Does Not Do

This flow does not currently persist:

- `calendar_relationships.relation_role_id`

That relationship layer remains separate until its authority boundary is explicitly adopted by the workflow.

## Operator Guidance

Use the flow in this order:

1. ensure the event exists
2. ensure prose is attached
3. run character suggestion
4. approve only the identities that clearly belong in the event
5. approve roles only when the prose gives enough evidence

If the prose only proves presence, approve the identity and skip the role.

Do not force a role assignment just to fill the field.

## Practical Example

Suppose attached prose clearly shows:

- Avery enters the room
- Mina watches from the doorway

The workflow may suggest:

- Avery
- Mina

You might approve identities first, then approve:

- Avery as `role_initiator`
- Mina as `role_observer`

If Mina is clearly present but her role is ambiguous, approve Mina as a participant and use:

- `apply identities only`

or approve only Avery's role explicitly.

## Canonical Rule

`suggest_*` is advisory.

`apply_*` is authoritative.

Character tagging becomes real only when the approval workflow writes the participant link.
