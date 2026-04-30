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
