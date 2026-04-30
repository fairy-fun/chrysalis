# 📦 Prose Draft Creation — JSON Contract (Canonical)
## Endpoint
POST /pecherie/chill-api/index.php

Headers:

Content-Type: application/json
X-API-Key: <required>

## ✅ Minimal Working Payload (JSON)

{
"operation": "createProseDraft",
"entity_id": "prose_draft:BOOK-001-W3-D1-T1-v1",
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

🔑 Critical Rules (DO NOT REDERIVE)
1. JSON only (unless uploading file)
   Use raw JSON
   Do not use multipart unless sending prose_file
2. prose_body formatting
   Use \n for line breaks
   No need to escape single quotes
   Escape double quotes (\") if present
3. draft_status_id
   Must exist in:
   entities.id
   WHERE entity_type_id = 'entity_type_status'
   Example:
   "draft_status_id": "prose_status_draft"
   ❌ NOT in classvals
4. projection.target_entity_id
   Must be a valid calendar_events.entity_id
   Example:
   "target_entity_id": "calendar_event:322"
5. projection.role_id
   Currently free text
   Example:
   "role_id": "primary"
   ❌ Not validated
   ❌ No foreign key constraint
6. projection_type_id
   Must exist in classvals
   Example:
   "projection_type_id": "projection_type_book"
7. annotations
   Must be an array
   Can be empty:
   "annotations": []
   ⚠️ Database Constraints (IMPORTANT)
   prose_drafts
   draft_status_id → FK → entities(id)
   prose_projections
   role_id → NO FK (free text)
   🧪 Verification Sequence
   Create
   operation: createProseDraft

↓

Read training view
operation: getProseTrainingView

Expected:

"annotations": []

↓

Read annotations
operation: getProseAnnotations
🧠 Known Failure Modes
Error	Cause
Invalid draft_status_id	Validator still pointing to classvals
FK 1216 on draft_status_id	FK not migrated to entities
FK 1216 on role_id	FK still exists on prose_projections
Hanging request	PHP error before response
projection.role_id invalid	Old validator not removed
🧩 Design Notes (LOCKED)
Prose = canonical source
Annotations = curated facts
Training view = derived (read-only)
No suggestion persistence
No SQL business logic