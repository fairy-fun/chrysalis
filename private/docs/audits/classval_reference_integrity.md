# CI Audit — Classval Seed Integrity

## Purpose

This audit ensures every classval reference used by framework tables resolves to a real classval identity.

A valid classval must exist in:

```text
entities
→ classvals
```

Usage tables must never introduce new cval_* IDs without first seeding the identity layer.

## Audit Rule

Any column classified as classval-backed must satisfy:

```text
referenced_value IN classvals.id
```

If not, CI must fail.

## Required Failure Output

The audit should report:

```text
table_name
column_name
unresolved_classval_id
reference_count
enforcement
```

Example:
```json
{
"table_name": "expression_constraint_outputs",
"column_name": "output_value_classval_id",
"unresolved_classval_id": "cval_control_self_calibration",
"reference_count": 1,
"enforcement": "AUDIT"
}
```

## Fix Pattern

For every unresolved classval:

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

Then:

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
## Architectural Boundary

This audit belongs in CI/PHP.

It must not be implemented as:

- trigger
- stored procedure
- DB-side business logic
- automatic mutation

The audit detects.
The seed patch repairs.