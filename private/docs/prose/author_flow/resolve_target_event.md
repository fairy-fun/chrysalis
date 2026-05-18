# Resolve Target Event

## Intent

Resolve the calendar event that subsequent prose operations should target.

This procedure exists because event resolution is shared across multiple operator workflows.

---

## Direct Lookup Keys (Strict Fast Path)

If the input matches the pattern:

calendar_event:<integer>

This is a DIRECT RESOLUTION KEY.

Example:
calendar_event:7
calendar_event:42

The operator MUST:

1. Extract integer ID
2. Treat it as a canonical calendar_event primary key lookup
3. Skip all natural-language interpretation steps
4. Skip "latest event" heuristics entirely

This input bypasses:
- inspect latest event handoff
- inspect recent calendar activity
- inspect projection linkage fallback heuristics

It maps directly to:

calendar_event_entity_id = resolve_by_primary_key(<integer>)

## Success Output

Successful completion must emit:

calendar_event_entity_id
calendar layer type
projection state if available
resolution confidence
next valid actions

---

## Resolution Order

0. check for direct lookup key (calendar_event:<id>)
1. inspect latest event handoff
2. inspect latest prose linkage
3. inspect recent calendar activity
4. inspect projection linkage
5. ask user only if durable resolution fails
---

## Allowed Questions

If resolution fails:

“Which event should we work with?”

If partial resolution exists:

“Should we continue with the latest resolved event?”

---

## Stop Conditions

Stop immediately after:
- one event is resolved
  or
- resolution fails cleanly

Do not continue into prose operations.

## Direct Resolution Output

When resolved via direct lookup key, emit:

calendar_event_entity_id: cal_evt_<resolved_id>
resolution_method: direct_primary_key
resolution_confidence: absolute