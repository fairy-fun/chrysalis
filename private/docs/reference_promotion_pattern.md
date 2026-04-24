# Reference Promotion Pattern

This framework promotes references from audit-only validation to database-enforced constraints using a fixed pipeline.

## Rule

Do not promote a reference directly to a foreign key just because it appears to point at another table.

Every reference must move through this sequence:

1. Start audit-only
2. Prove existing data is clean
3. Classify the reference
4. Promote stable structural references to FK
5. Keep CI for semantic correctness

## Enforcement Split

Database:

- enforces existence
- enforces stable structural relationships

CI audits:

- enforce meaning
- enforce type correctness
- enforce classification coverage
- protect dynamic or evolving semantic surfaces

## Current Examples

### Domain references

`attribute_domain_map.domain_id`

- DB enforces: `domain_id → entities.id`
- CI enforces: `entities.entity_type_id = 'entity_type_domain'`

### Classval references

Stable classval references may be FK-backed to `classvals.id`.

Expression constraint references remain audit-only until their semantics stabilise.

## Locked Principle

Database enforces stable structure.

CI enforces semantic correctness.

No triggers.