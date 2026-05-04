# Calendar Execution Contract (Week → Subevent)

## Hierarchy (fixed)

Week (calendar_layer_week)
→ Day (calendar_layer_day)
→ Time (calendar_layer_time)
→ Event (calendar_layer_event)
→ Subevent (calendar_layer_subevent)

### Attachment Rule (critical)

* All batch-created subevents MUST use a time-layer node as parent_event_entity_id
* Never attach subevents directly to week, day, or event layers

Parent Selection Procedure

* Locate target Week
* Select intended Day
* Select intended Time block
* Use that time-layer entity_id as the batch parent

Batch Invariants

* One non-empty prose line → exactly one subevent
* Order is preserved via sequence_index
* Idempotency enforced via client_id = plan_id:index
* Replays must not create duplicates

Coexistence Rule

* Pre-existing events (no client_id) are allowed
* Do not delete or “clean” the parent before batching

Anti-Patterns (forbidden)

* Attaching subevents to week/day/event layers
* Using createCalendarSubevent for batch creation
* Modifying prose between runs (breaks determinism)
* Writing directly to DB