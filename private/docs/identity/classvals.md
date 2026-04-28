# Classvals — Identity & Insertion Protocol

## Overview

Classvals are **entity-backed typed identity objects**.

They are not standalone rows. Every classval must exist across the full identity chain:

entities (base identity)
→ classvals (typed identity)
→ usage tables (e.g. expression_constraint_outputs)


Failure to satisfy this chain will result in either:
- CI audit failures (unresolved references)
- Database FK violations

---

## 🔒 Invariant

> A classval ID is a fully realised identity object, not just a string.

This means:

- It must exist in `entities`
- It must exist in `classvals`
- It must reference a valid `classval_type_id`
- Only then can it be used elsewhere

---

## ✅ Required Insertion Order

### 1. Ensure entity exists

```sql
INSERT INTO entities (
    id,
    entity_type_id
)
VALUES (
    'cval_example',
    'entity_type_classval'
)
ON DUPLICATE KEY UPDATE
    entity_type_id = VALUES(entity_type_id);
```

### 2. Ensure classval type exists in classval_type_classvals (classval-backed type system)
```sql

SELECT id
FROM classval_type_classvals
WHERE id = 'classval_type_example';
```
If no row is returned, the classval type must be created before proceeding.

### 3. Insert classval
```sql

INSERT INTO classvals (
    id,
    classval_type_id,
    code,
    label,
    created_at
)
VALUES (
    'cval_example',
    'classval_type_example',
    'cval_example',
    'Example label',
    NOW()
)
ON DUPLICATE KEY UPDATE
    classval_type_id = VALUES(classval_type_id),
    code = VALUES(code),
    label = VALUES(label);

```

## 🚫 Common Failure Modes

### FK failure: classvals.id → entities.id
```
#1452 - Cannot add or update a child row
```
Cause:

- Missing entity row

Fix:

- Run Step 1 (entities insert)

### Missing required column

```
#1364 - Field 'code' doesn't have a default value
```
Cause:

- Required identity field not supplied

Fix:

- Include all required columns (id, classval_type_id, code, label, created_at)

Unknown column errors
#1054 - Unknown column 'X'

Cause:

Assumed schema

Fix:

Always inspect first:
DESCRIBE classvals;
DESCRIBE entities;
CI Audit Failure: Unresolved Classval Reference

Example:

Classval reference integrity audit failed

Cause:

Classval referenced in usage tables but not seeded

Fix:

Complete full insertion chain (entities → classvals)
## 🧱 Schema Reference
entities
id                VARCHAR(64)  PK
entity_type_id    VARCHAR(64)
classvals
id                  VARCHAR(64)  PK / FK → entities.id
classval_type_id    VARCHAR(64)
code                VARCHAR(64)
label               VARCHAR(128)
created_at          DATETIME
## 🔧 Recommended Helper (PHP Layer)

To avoid multi-step failures, implement a helper:

ensureClassvalExists($id, $classvalTypeId, $label);

Expected behaviour:

Upsert into entities
Validate classval_type_id
Upsert into classvals

## 🧠 Design Principle

The database enforces existence and structure.
CI enforces meaning and completeness.

Together:

DB prevents invalid rows
CI prevents incomplete systems

Do not bypass either layer.

## ✅ Summary

To add a new classval:

- Insert into entities
- Ensure classval_type_id exists
- Insert into classvals
- Then reference it elsewhere

Never insert directly into usage tables without completing identity first.

## Related Enforcement

See:
- ../audits/classval_reference_integrity.md