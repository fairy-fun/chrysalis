# Registry Type Model — Entity-Backed Registries

## 1. Purpose

Define a unified, typed registry system where:

* All registry values are entities
* All references are type-safe
* No string-based pseudo-registries remain

This replaces implicit string registries currently spread across the schema.

---

## 2. Core Principles

### 2.1 Entity as Identity

All registry values MUST exist in:

```
entities.id
```

Each must declare:

```
entities.entity_type_id
```

---

### 2.2 Registry = Entity Type

Each registry is defined by a distinct `entity_type_id`.

There is no generic “classval system”.

---

### 2.3 Strong Typing

Every reference must satisfy:

```
value exists in entities.id
AND
entities.entity_type_id matches expected type
```

---

### 2.4 No Implicit Registries

String values without entity backing are invalid.

---

## 3. Registry Types

Initial registry types:

```
entity_type_classval
entity_type_profile_type
entity_type_intent
entity_type_status
entity_type_figure
entity_type_segment_group
entity_type_team_domain
entity_type_projection_type
```

Each registry is:

* closed set (controlled)
* explicitly seeded
* CI-validated

---

## 4. Mapping Rules

### 4.1 Legacy → Entity Mapping

| Legacy Pattern    | Target Entity Type          |
| ----------------- | --------------------------- |
| classvals.id      | entity_type_classval        |
| profile_type_*    | entity_type_profile_type    |
| intent_*          | entity_type_intent          |
| status_*          | entity_type_status          |
| FIG-* / tango_*   | entity_type_figure          |
| segment_group_*   | entity_type_segment_group   |
| team_domain_*     | entity_type_team_domain     |
| projection_type_* | entity_type_projection_type |

---

### 4.2 Column Semantics

Columns must imply type:

| Column Name                    | Expected Entity Type      |
| ------------------------------ | ------------------------- |
| profile_type_id                | entity_type_profile_type  |
| intent_classval_id             | entity_type_intent        |
| status_id / status_classval_id | entity_type_status        |
| group_classval_id              | entity_type_segment_group |
| classval_id                    | entity_type_classval      |
| figure (currently misnamed)    | entity_type_figure        |

---

## 5. Migration Strategy

### Phase 1 — Identity Mirror (DONE for classvals)

* classvals → entities
* enforce identity parity

---

### Phase 2 — Expand Entity Types

Create entity types for all registries.

Seed entities for:

* profile types
* intents
* statuses
* figures
* etc.

---

### Phase 3 — Mirror Legacy Registries

For each registry:

* ensure all values exist in `entities`
* enforce via audit

---

### Phase 4 — Typed Reference Audits

Replace generic checks with:

```
assert_entity_type(value, expected_type)
```

Applied per column.

---

### Phase 5 — API Migration

Resolvers must:

* read from entities
* validate entity type
* stop relying on classvals

---

### Phase 6 — Column Renaming (Optional, Late)

Example:

```
profile_type_id → profile_type_entity_id
```

Only after full migration.

---

### Phase 7 — Legacy Retirement

* freeze classvals
* remove or archive legacy tables

---

## 6. Audit Strategy

### 6.1 Identity Audits

Ensure mirror integrity:

```
audit_*_entity_mirror
```

---

### 6.2 Reference Integrity (Typed)

For each column:

```
value exists in entities
AND entity_type matches expected
```

---

### 6.3 Fail Closed

All violations:

* detected in CI
* block deploy

---

## 7. Non-Goals

* Do NOT store registry metadata in entity_linked_facts
* Do NOT rely on DB constraints
* Do NOT allow fallback or silent coercion

---

## 8. Outcome

After migration:

* All registries are explicit
* All IDs are typed
* All references are validated
* No hidden string-based systems remain

System becomes:

deterministic
auditable
extensible without ambiguity
