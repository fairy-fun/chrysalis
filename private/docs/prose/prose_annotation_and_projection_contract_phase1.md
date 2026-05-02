# Prose Annotation & Projection Contract (Phase 1)
## Purpose

This document defines the **currently implemented and supported schema surface** for:

* prose attachment to entities (calendar events)
* projection types
* annotation system (types, values, and constraints)

This is the **authoritative reference** for repository interactions.
Do not infer or invent additional classvals or tables beyond what is defined here.

---

## 1. Calendar Event Targets

Prose is attached to entities of type:

* `entity_type_calendar_event`

Example IDs:

* `calendar_event:325` (Late night)
* `calendar_event:323` (Morning)
* `calendar_event:297` (Lunch)

### Important

Calendar events currently represent **temporal slices**, not fully descriptive scenes.
Narrative meaning must be carried by prose, not event metadata.

---

## 2. Characters

Characters are stored as entities:

* `entity_type_character`

Primary POV character:

* `CHAR-MAIN-001` → Shay Aurelia Vertue Young

Other available characters include:

* `CHAR-MAIN-002` → Sebastian Bennett
* `CHAR-MAIN-003` → Kai Blackwood
* `CHAR-MAIN-012` → Tiffany Rose
* `char_serena_galloway` → Serena Galloway
* `char_jorge_alvarez` → Jorge Alvarez

---

## 3. Prose Draft Status

Valid values from `prose_draft_status_classvals`:

* `prose_status_draft`
* `prose_status_revised`
* `prose_status_approved`
* `prose_status_superseded`

### Usage

All new prose entries should default to:

* `prose_status_draft`

---

## 4. Projection Types

Valid values from `projection_type_classvals`:

* `projection_type_book`
* `projection_type_journal`
* `projection_type_dream_journal`
* `projection_type_timeline_view`

### Critical Constraint

There is **no** `projection_type_calendar_event`.

Calendar events are **targets**, not projection types.

### Recommended Default

* `projection_type_book` for narrative prose

---

## 5. Annotation System

### 5.1 Tables

The following tables exist:

* `annotation_type_classvals`
* `annotation_value_classvals`
* `prose_annotation_spans`

### Important

The following tables do **not** exist and must not be queried:

* `prose_annotation_type_classvals` ❌
* `prose_annotation_value_classvals` ❌
* `prose_annotation_source_type_classvals` ❌

---

## 5.2 Annotation Types

From `annotation_type_classvals`:

* `annotation_type_voice` → Narrative POV
* `annotation_type_limbic` → Internal emotional state
* `annotation_type_expression` → External behavioral expression
* `annotation_type_theme` → Defined but currently unused
* `annotation_type_presence` → Defined but no values available

---

## 5.3 Annotation Values

From `annotation_value_classvals`:

### Voice

* `voice_shay`

### Limbic

* `limbic_neutral`
* `limbic_stressed`

### Expression

* `expression_contained`
* `expression_expressive`

---

## 5.4 Constraints

* Each annotation value must map to a valid `annotation_type_id`
* Only values present in `annotation_value_classvals` may be used
* Do not invent new annotation values in payloads

### Current Limitations

* No character presence values exist
* No theme values exist
* Limbic states are binary (neutral / stressed)
* Voice is currently limited to Shay

---

## 5.5 Annotation Model

Annotations are **span-based**, using:

* `prose_annotation_spans`

### Implication

Annotations attach to:

* specific text ranges (start/end offsets), or
* the full prose body

### Phase 1 Guidance

Until granularity is needed:

* apply annotations to the **full text span**

---

## 6. Minimal Valid Annotation Set

Each prose entry should include:

### Required

* 1 × Voice

    * `voice_shay`

* 1 × Limbic

    * `limbic_neutral` OR `limbic_stressed`

### Optional (recommended)

* 1 × Expression

    * `expression_contained` OR `expression_expressive`

---

## 7. Narrative Encoding Model

The system encodes narrative state via:

| Layer      | Meaning           |
|------------|-------------------|
| Voice      | Who is perceiving |
| Limbic     | Internal state    |
| Expression | External behavior |

### Example

Shay under pressure but composed:

* `voice_shay`
* `limbic_stressed`
* `expression_contained`

---

## 8. Non-Goals (Phase 1)

The following are **not supported** and should not be attempted:

* Character presence tagging
* Multi-character POV encoding
* Rich emotional taxonomy
* Theme tagging
* Annotation source tracking

---

## 9. Implementation Guidance

When inserting prose:

1. Attach to:

    * a valid `calendar_event` entity

2. Use:

    * `projection_type_book`
    * `prose_status_draft`

3. Apply annotations:

    * using only valid types and values
    * across the full text span

---

## 10. Future Expansion (Phase 2+)

Planned areas for extension:

* annotation_value_classvals:

    * expanded limbic states
    * theme values
    * presence values

* additional voice types

* optional annotation source typing

These must be added explicitly to classval tables before use.

---

### Target Entity Domain Validation & Identity Constraints

All writes to `sxnzlfun_chrysalis.prose_projections` MUST go through:

`private/framework/prose/prose_projection_writer.php`

Specifically:
`prose_projection_guard_target_if_needed(PDO $pdo, string $targetEntityId)`

#### Domain Rules

- `calendar_event:*`
    - MUST pass `require_calendar_event_projection_target_node(...)`
    - Validation includes:
        - existence (event_id or entity_id)
        - correct layer (`calendar_layer_event`)
        - structural integrity
    - MUST fail before insert if invalid

- `dream_journal:*`
    - MUST NOT invoke calendar validation
    - MUST be allowed as a valid projection target

- Any other prefix
    - MUST be rejected
    - Throws `Unsupported target_entity_id domain`

- `dream_journal:*` targets require a pre-existing entity of type `dream_journal`
- These entities are deterministic: `dream_journal:<character_id>`

Note: For `dream_journal:*` targets, the target must match the dreamer's identity exactly.

Format:
dream_journal:<character_id>

Example:
dream_journal:CHAR-MAIN-012

This is enforced at creation time:
dreamer_entity_id = X
journal_entity_id MUST equal dream_journal:X

See: `dreams.md` → Dream Journal Identity (Enforced)

#### Design Principle

Target validation is **domain-scoped, not table-scoped**.

This allows prose projections to support multiple domains (calendar, dream journal, future types) without coupling all writes to a single domain's rules.

#### Adding a New Target Domain

To introduce a new projection target type:

1. Define a new prefix:
   example_domain:*


2. Extend `prose_projection_guard_target_if_needed`:
- Add prefix to allowed list
- Add domain-specific guard if needed

3. DO NOT:
- Modify existing domain guards
- Introduce cross-domain validation
- Bypass the shared writer

4. Ensure:
- Validation happens before insert
- Non-applicable domains are not validated

#### Invariant

No code may write directly to `prose_projections`.

All writes must flow through the shared writer to guarantee:
- domain validation
- export target uniqueness
- consistent behavior across domains

## Final Rule

If a value or table is not present in the schema,
**it is not part of the system. Do not assume it exists.**
