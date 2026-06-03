# Prose Character Role Tagging Contract

This contract defines how prose-derived character participation and event-relative role tagging are separated inside the calendar prose workflow.

## Current Boundary

Identity resolution and role assignment are distinct steps.

1. `calendar_event_suggest_characters`
   Produces advisory identity suggestions from attached prose.

2. `calendar_event_approve_character_tags`
   Approves identity links first, then optionally approves event-relative participant roles.

3. `apply_*`
   Is the only persistence boundary.

## Identity Versus Role

Character identity answers:

- who appears in the prose

Participant role answers:

- what role that character plays relative to the event

The workflow must not collapse those into a single implicit step.

## Canonical Role Vocabulary

Approved participant roles must resolve through:

- `calendar_relation_role_classvals`

Suggested roles are advisory only until the apply step writes them.

## Persistence Target

The current prose workflow persists approved roles to:

- `calendar_event_participants.role_id`

Approved identity links remain participant-centered:

- one participant row per approved event/character link

## Deferred Relationship Layer

`calendar_relationships.relation_role_id` is intentionally not part of the first role-tagging patch.

That table may eventually participate in the contract, but only after its authority boundary is confirmed against the live schema and downstream readers.

## Unresolved Roles

If character identity is approved but no role is approved:

- the participant link should still be created
- `role_id` may remain empty

The workflow should prefer explicit approved roles over speculative defaults.
