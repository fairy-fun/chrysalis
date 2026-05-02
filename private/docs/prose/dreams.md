# Dream Projection + Entity Pattern (Chrysalis)

## Purpose

This document defines the **correct, current-safe way** to implement dream journaling using the existing schema without introducing architectural drift.

It reflects:

* current database structure
* classval constraints
* entity system behavior
* projection system limitations

---

# 🧠 Core Principles

```text
Entities = identity
Prose = canonical text
Projections = placement/grouping
Dreams = domain structure (sequence, recurrence)
Annotations = meaning
```

---

# 🧱 Current Schema Reality

## entities

```sql
CREATE TABLE sxnzlfun_chrysalis.entities (
  id VARCHAR(64) PRIMARY KEY,
  entity_type_id VARCHAR(64)
);
```

* No label column
* Identity is entirely encoded in `id`
* `entity_type_id` must match `entity_type_classvals.id`

---

## entity_type_classvals

```text
dream
dream_journal
```

Already exists and is valid.

---

## projection_type_classvals

Now includes:

```text
projection_type_dream_journal
```

This is a **temporary compatibility layer**, not final architecture.

---

# 🎯 Entity ID Convention (IMPORTANT)

We are using:

```text
<domain>:<identifier>
```

Examples:

```text
dream_journal:shay
dream:812
calendar_event:325
```

This is already established in the system and should be followed consistently.

---

# ✅ Required Inserts

## 1. Create Dream Journal Entity

```sql
INSERT IGNORE INTO entities (id, entity_type_id)
VALUES ('dream_journal:shay', 'dream_journal');
```

Represents:

```text
Shay's dream journal (grouping target)
```

---

## 2. Create Dream Entity (per dream)

```sql
INSERT IGNORE INTO entities (id, entity_type_id)
VALUES ('dream:812', 'dream');
```

Represents:

```text
A single dream as a domain object
```

---

# 🔗 Projection Usage

Attach a dream (via prose) to the journal:

```text
projection_type_id = projection_type_dream_journal
target_entity_id   = dream_journal:shay
```

This goes in:

```text
prose_projections
```

---

# 🧱 Dream System Structure

```text
dreams table
  → structural (sequence, recurrence, ownership)

prose_drafts
  → raw dream text

entities
  → identity layer

prose_projections
  → journal membership

prose_annotation_spans
  → meaning (symbols, tone, etc.)
```

---

# 🚫 What NOT to Do

```text
❌ Do NOT create projection_types table yet
❌ Do NOT remove projection_type_classvals
❌ Do NOT add dream semantics to dreams table
❌ Do NOT use raw strings (e.g. "mirror", "manual")
❌ Do NOT create dream_classvals
```

---

# ⚠️ Transitional Notes

This system currently uses:

```text
*_classvals tables as pseudo-domain tables
```

So:

```text
projection_type_classvals
entity_type_classvals
annotation_type_classvals
```

are **not pure classification systems anymore**.

They are **transitional structures**.

---

# 🔮 Future Direction (NOT NOW)

Eventually:

```text
projection_type_classvals → projection_types
entity_type_classvals     → entity_types
annotation_type_classvals → annotation_types
```

But this must be done as a **separate migration**, not during feature work.

---

# ✅ Summary

To implement dream journaling correctly right now:

1. Use existing classval systems (do not replace them)
2. Use entity IDs with namespacing (`dream:812`)
3. Use projections for grouping (`dream_journal:shay`)
4. Keep dreams table structural only
5. Keep meaning in annotations

---

# ✔️ Minimal Working Pattern

```text
Entity:
  dream:812

Prose:
  prose_draft:812

Projection:
  projection_type_dream_journal → dream_journal:shay

Meaning:
  annotation spans

Structure:
  dreams table
```

---

This approach:

* works with current schema
* avoids classval explosion
* prevents projection system drift
* keeps future migration clean

## Dream Journal Identity

Dream journals are anchored to **canonical character entities**, not team membership entities or free-form identifiers.

### Resolution Rule

If a character appears via team membership:

tm_* → team_memberships.member_id → CHAR-MAIN-*

The `CHAR-MAIN-*` entity is the **true identity**.

### Correct Usage

dream_journal:CHAR-MAIN-012

### Incorrect Usage

dream_journal:tm_tiffany_rose  
dream_journal:tiffany_rose

### Invariant

Every `dream_journal:*` must correspond to an existing `entity_type_character`.

Team membership implies character existence, but does not replace it.

## Dream Journal Identity (Enforced)

Dream journals are **deterministically derived from character identity**.

### Rule

For any dream:

- `dreamer_entity_id = X`
- `journal_entity_id MUST equal dream_journal:X`

### Example

Correct:

dreamer_entity_id: CHAR-MAIN-012
journal_entity_id: dream_journal:CHAR-MAIN-012


Incorrect:

dream_journal:tm_tiffany_rose
dream_journal:tiffany_rose
dream_journal:CHAR-MAIN-999


### Invariant

A dream journal **cannot exist independently** of its character.

It is a 1:1 mapping:

character ↔ dream_journal


### Enforcement

This is enforced at creation time in:

create_dream_journal_entry(...)

## Dream Journal Entity Model (Schema Clarification — May 2026)

## Dream Journal System — Schema Clarification (May 2026)

### Entity Type Resolution

Dream journals are **first-class entities**, not derived or polymorphic subtypes.

They are registered in:

```text
sxnzlfun_chrysalis.entity_type_classvals
```

Confirmed entry:

```text
id: dream_journal
label: Dream Journal
```

---

### Important Correction

There is **no `entity_types` table** in the schema.

All entity typing is handled via:

```text
entity_type_classvals
```

This is consistent with the broader system pattern:

* `*_classvals` tables define canonical type registries
* Type enforcement may be:

    * soft (application-level), or
    * relational (via FK to classvals)

---

### Dream Journal Invariant (Authoritative)

Dream journals are deterministic functions of character identity:

```text
journal_entity_id = 'dream_journal:' + dreamer_entity_id
```

Example:

```text
dream_journal:CHAR-MAIN-012
```

---

### Example: Tiffany Rose

Canonical character:

```
CHAR-MAIN-012
```

Deterministic dream journal:

```
dream_journal:CHAR-MAIN-012
```

Creation:

```sql
INSERT INTO sxnzlfun_chrysalis.entities (id, entity_type_id)
VALUES ('dream_journal:CHAR-MAIN-012', 'dream_journal');
```

Verification:

```sql
SELECT *
FROM sxnzlfun_chrysalis.entities
WHERE id = 'dream_journal:CHAR-MAIN-012';
```

Result:

* entity exists
* correctly typed as `dream_journal`

This entity is now the required target for:

* `create_dream_journal_entry(...)`
* `insert_prose_projection(...)` (dream journal targets)

---

### Notes

* This entity must exist before any dream entries can be created
* The ID is deterministic and must not vary
* No alternate journal IDs are allowed for the same character



### Creation Rule

A dream journal must exist as an entity:

```sql
INSERT INTO sxnzlfun_chrysalis.entities (id, entity_type_id)
VALUES ('dream_journal:<CHAR_ID>', 'dream_journal');
```

---

### Validation Rule (Enforced in Code)

In `create_dream_journal_entry(...)`:

* `dreamer_entity_id` must be a valid `entity_type_character`
* `journal_entity_id` must EXACTLY equal:

```text
dream_journal:<dreamer_entity_id>
```

No alternate journal IDs are permitted.

---

### Projection Behavior

In `prose_projection_writer.php`:

* `calendar_event:*` → calendar guard enforced
* `dream_journal:*` → calendar guard bypassed

However:

* entity must exist
* entity must be correctly typed (`dream_journal`)

---

### System State Requirements

For a character to support dream journaling:

* Character entity exists ✅
* `dream_journal` type exists in `entity_type_classvals` ✅
* Deterministic journal entity exists ❗ (must be created if missing)

---

### Notes

* `journal_type_classvals` exists but is **not used** for dream journals
* Dream journals are modeled as **entity types, not journal subtypes**
* This design enables:

    * direct projection targeting
    * simplified invariant enforcement
    * type-level routing in projection systems

---

### Summary

Dream journals are:

* concrete entities
* deterministically named
* type-backed via `entity_type_classvals`
* required for all dream entry operations

Failure to materialize the entity results in a **valid but incomplete system state**
