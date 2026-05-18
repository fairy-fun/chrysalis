# Resolve Target Event

## Intent

Resolve the calendar event that subsequent prose operations should target.

This procedure exists because event resolution is shared across multiple operator workflows.

---

## Success Output

Successful completion must emit:

calendar_event_entity_id
calendar layer type
projection state if available
resolution confidence
next valid actions

---

## Resolution Order

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