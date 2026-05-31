# Calendar Beat Resolution Model (LOCKED)

Canonical resolution path:

event
→ event_type_id
→ beat_classset_id (via calendar_event_type_classvals)
→ (set_id, code)
→ beat_type_id

Deprecated Models (FORBIDDEN)

calendar_beat_domain_map
calendar_domain_beat_classset_map

These tables must not be referenced anywhere in planner authority.

## Reason

The old model encoded:

domain → individual beat types

and later:

domain → classset → beat types

Both are wrong as beat-resolution authority.

The canonical model is:

event type → classset → beat types

which allows:

* multiple beat vocabularies
* deterministic `(set_id, code)` resolution
* user-defined human-readable domains without forcing beat semantics onto domain labels

## Enforcement

* CI audit validates event-type-to-classset references
* Identity classifier excludes deprecated beat-domain discovery paths
* Planner resolves via `calendar_event_type_classvals.beat_classset_id`

## Domain Model

Domains remain valid first-class entities.

They are human-readable categorizations such as:

* work
* social
* private

They are not the authority for beat classset selection.

## Rule

If you see `calendar_beat_domain_map` or `calendar_domain_beat_classset_map` used for beat resolution:

→ delete or refactor immediately
