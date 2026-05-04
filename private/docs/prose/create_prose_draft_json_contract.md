## Postman Saved Request

Name:

Create Prose Draft — JSON

Method:

POST

URL:

https://antheapeche.com/pecherie/chill-api/index.php

Headers:

Content-Type: application/json
X-API-Key: <your key>

Body type:

raw → JSON

Canonical body:

```json
{
  "operation": "createProseDraft",
  "entity_id": "prose_draft:BOOK-001-W3-D1-T1-v2",
  "title": "Book 1 — Week 3 Sunday Early Morning",
  "prose_body": "Line 1\nLine 2\nLine 3",
  "draft_status_id": "prose_status_draft",
  "author_entity_id": null,
  "projection": {
    "projection_type_id": "projection_type_book",
    "target_entity_id": "calendar_event:322",
    "role_id": "primary",
    "projection_order": 1,
    "is_export_target": 1
  },
  "annotations": []
}
```
---

## Example — Dream Journal Projection (Deterministic Target)

When creating prose for a dream journal entry, the `target_entity_id` must follow the invariant:

```text
dream_journal:<CHARACTER_ENTITY_ID>
```
### Canonical body:

```json
{
"operation": "createProseDraft",
"entity_id": "prose_draft:8ef0615d9b49aed6ef336dc2b8314912",
"title": "The Boundaries Become Porous",
"prose_body": "I am walking through a space where the walls feel soft...",
"draft_status_id": "prose_status_draft",
"author_entity_id": null,
"projection": {
"projection_type_id": "projection_type_dream_journal",
"target_entity_id": "dream_journal:CHAR-MAIN-001",
"role_id": "primary",
"projection_order": 1,
"is_export_target": 1
},
"annotations": []
}
```

#### Invariants

target_entity_id MUST equal:
```
dream_journal:<CHAR-MAIN-*>
```
The referenced character must exist:
```
entities.id = CHAR-MAIN-001
entities.entity_type_id = entity_type_character
```
The journal entity must exist:
```
entities.id = dream_journal:CHAR-MAIN-001
entities.entity_type_id = dream_journal
```
No alternate forms are allowed:
```
❌ dream_journal:shay
❌ dream_journal:tm_*
```

---

## 🔁 Next Step: Generate Calendar Beats

After creating a prose draft, you may generate calendar subevents (beats):

POST /pecherie/chill-api/index.php

```json
{
  "operation": "executeCalendarBatchFromProse",
  "parent_event_entity_id": "calendar_event:322",
  "prose": "Line 1\nLine 2\nLine 3"
}
```

### Behavior

* Deterministic execution
* Safe to retry
* Same prose → same results

