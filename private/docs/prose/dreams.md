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
entities (
  id VARCHAR(64) PRIMARY KEY,
  entity_type_id VARCHAR(64)
)
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
