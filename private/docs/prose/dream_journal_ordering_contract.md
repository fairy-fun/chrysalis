# Dream Journal Ordering Contract

## Purpose

Define the **canonical, non-negotiable ordering** for all dream journal reads.

This ensures:

* consistent timelines
* deterministic query results
* no drift between backend and UI behavior

---

# 🧠 Core Rule

```text
Dream ordering is a READ concern, not a stored property.
```

Therefore:

```text
❌ Do NOT store ordering flags in the dreams table
❌ Do NOT let UI layers re-sort results
❌ Do NOT create alternate ordering logic in different queries

✔ ALL dream journal reads MUST use the same ORDER BY contract
```

---

# 📜 Canonical ORDER BY

```sql
ORDER BY
  CASE
    WHEN d.dreamed_at IS NOT NULL THEN 0
    ELSE 1
  END ASC,
  d.dreamed_at ASC,
  d.sequence_index IS NULL ASC,
  d.sequence_index ASC,
  d.id ASC
```

---

# 🔍 Ordering Semantics

This enforces:

### 1. Dated dreams first

```text
dreamed_at IS NOT NULL → comes first
dreamed_at IS NULL     → comes after
```

---

### 2. Dated dreams sorted chronologically

```text
ORDER BY dreamed_at ASC
```

---

### 3. Undated dreams come after

Within undated dreams:

```text
sequence_index IS NULL ASC
```

Meaning:

```text
sequenced dreams → first
unsequenced dreams → last
```

---

### 4. Sequence ordering

```text
ORDER BY sequence_index ASC
```

---

### 5. Stable tie-breaker

```text
ORDER BY id ASC
```

Guarantees:

```text
deterministic results across all environments
no flickering order
```

---

# 🧱 Where This Lives

This logic MUST exist in the **reader/query layer**, e.g.:

```text
private/framework/dreams/dream_journal_reader.php
```

Example conceptual flow:

```text
getDreamJournalEntries(dreamer_entity_id)
  → SELECT ...
  → JOIN dreams d
  → JOIN prose_drafts p
  → (optional) JOIN prose_projections
  → APPLY ORDER BY (this contract)
  → return results
```

---

# 🚫 Anti-Patterns (Do Not Do These)

```text
❌ Adding "sort_order" column to dreams
❌ Sorting in frontend/UI
❌ Using different ORDER BY in different endpoints
❌ Omitting id as final tie-breaker
❌ Relying on default DB ordering
```

---

# ⚠️ Why This Matters

Without a single enforced ordering:

```text
- timelines become inconsistent
- pagination breaks subtly
- UI shows different order than API
- debugging becomes painful ("nothing is wrong, but it looks wrong")
```

---

# ✅ Contract Summary

```text
The ORDER BY clause is part of the definition of "a dream journal list".
It must be applied consistently everywhere.
It is not optional.
```

---

# 🔒 Final Rule

```text
If a query returns dream journal entries, it MUST use this ORDER BY.
No exceptions.
```
