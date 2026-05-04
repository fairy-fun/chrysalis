# Calendar Execution Contract (Week → Subevent)

## Hierarchy (fixed)

Week (calendar_layer_week)
→ Day (calendar_layer_day)
→ Time (calendar_layer_time)
→ Event (calendar_layer_event)
→ Subevent (calendar_layer_subevent)

---

## Attachment Rule (critical)

* All batch-created subevents MUST use an **event-layer node** as `parent_event_entity_id`
* Never attach subevents directly to week, day, or time layers

---

## Parent Selection Procedure

* Locate target Week
* Select intended Day
* Select intended Time block
* Select intended Event
* Use that **event-layer entity_id** as the batch parent

---

## Batch Invariants

* One non-empty prose segment → exactly one subevent
* Order is preserved via `order_index`
* Idempotency enforced via **stable client_id (slot-based identity)**
* Replays must not create duplicates

---

## Idempotency Model (v2)

* `client_id` MUST be stable across runs
* `client_id` MUST NOT be derived from plan_id or content
* Recommended format:

```
calendar_event:{parent_event_entity_id}:slot:{index}
```

* `beat_hash` is diagnostic only (not identity)

---

## Coexistence Rule

* Pre-existing events (no client_id) are allowed
* Do not delete or “clean” the parent before batching

---

## Anti-Patterns (forbidden)

* Attaching subevents to week/day/time layers
* Deriving identity from plan_id or prose content
* Treating batch execution as a destructive sync
* Writing directly to DB
