# Calendar Beat Resolution Model (LOCKED)

Canonical resolution path:

event → domain_id
→ classset_id (via calendar_domain_beat_classset_map)
→ (set_id, code)
→ beat_type_id
Deprecated Model (FORBIDDEN)
calendar_beat_domain_map

This table must not be referenced anywhere in code.

Reason

The old model encoded:

domain → individual beat types

This is incorrect and non-extensible.

The new model enforces:

domain → classset → beat types

which allows:

* multiple beat vocabularies
* domain-specific behaviour
* deterministic (set_id, code) resolution

## Enforcement
* CI audit: deprecated calendar beat-domain usage
* Identity classifier: excludes deprecated table from discovery
* Planner: resolves via classset only

## Rule

If you see calendar_beat_domain_map in code:

→ delete or refactor immediately