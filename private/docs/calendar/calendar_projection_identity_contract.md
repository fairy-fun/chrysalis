# Calendar Projection Identity Contract

## Purpose

Defines the canonical identity model for calendar projections and the
runtime boundary rules governing:

- `projection_id`
- `projection_entity_id`

This contract exists to prevent identity drift, duplicate runtime
identity systems, and compatibility leakage into framework internals.

---

# Canonical Runtime Identity

## Rule

`projection_id` is the sole canonical runtime identity for calendar
projections.

All runtime systems MUST operate exclusively on:

```php
projection_id
```
This includes:

* framework orchestration
* runtime traversal
* event propagation
* expression systems
* materialization
* scheduling
* runtime caches/maps
* internal APIs
* runtime joins
* query bindings
## Runtime Requirements
Runtime systems MUST
1. Query by projection_id

Allowed:

WHERE projection_id = :projection_id

Forbidden:

WHERE projection_entity_id = :projection_entity_id
2. Propagate projection_id

Allowed:

ensure_calendar_day(
    $pdo,
    [
        'projection_id' => $projectionId,
    ]
);

Forbidden:

ensure_calendar_day(
    $pdo,
    [
        'projection_entity_id' => $projectionEntityId,
    ]
);
3. Normalize external identities immediately at ingress

External callers MAY still provide:

projection_entity_id

However, ingress layers MUST immediately normalize to:

projection_id

before entering runtime orchestration.

Allowed ingress pattern:

$projectionId = require_calendar_projection_id(
    $pdo,
    $projectionEntityId,
);

After normalization, runtime systems MUST NOT continue using
projection_entity_id.

4. Key runtime collections by projection_id

Allowed:

$eventsByProjectionId[$projectionId]

Forbidden:

$eventsByProjectionEntityId[$projectionEntityId]
Forbidden Runtime Usage

The following are prohibited within runtime internals.

Forbidden query patterns
projection_entity_id

inside:

joins
WHERE clauses
runtime filters
traversal queries
orchestration queries
Forbidden bindings
:projection_entity_id
Forbidden runtime access
$row['projection_entity_id']
$parent['projection_entity_id']
$object->projection_entity_id
Forbidden runtime propagation
'projection_entity_id' => ...

inside runtime orchestration payloads.

Allowed Compatibility Boundaries

projection_entity_id is now compatibility-only.

It is allowed exclusively for:

ingress compatibility
persistence compatibility
outbound API shaping
outbound view shaping
legacy interoperability
Approved Compatibility Infrastructure

The following files are approved compatibility boundaries.

Resolver compatibility
private/framework/calendar/calendar_projection_resolver.php

Responsible for:

entity ↔ internal ID normalization
ingress compatibility conversion
Persistence compatibility
private/framework/calendar/calendar_node_ensurer.php

Responsible for:

compatibility persistence fields
transitional compatibility writes
Outbound compatibility shaping
private/framework/expression/character_next_beat_suggester.php

Approved compatibility conversion:

$projectionEntityId = calendar_projection_entity_id(
    $pdo,
    $projectionId,
);

This is permitted because it is outbound compatibility shaping only.

Remaining Compatibility-Only References

The following files may still reference
projection_entity_id strictly for compatibility/output purposes.

private/framework/calendar/calendar_projection_resolver.php
private/framework/calendar/calendar_node_ensurer.php
private/framework/expression/character_theme_progression_builder.php
private/framework/expression/author_beat_view_builder.php
private/framework/expression/character_beat_label_suggester.php
private/framework/expression/character_next_beat_suggester.php

These files MUST NOT reintroduce runtime identity semantics based on
projection_entity_id.

CI Enforcement
Runtime violation grep
grep -RInE "\['projection_entity_id'\]|->projection_entity_id|nto[0-9]*\.projection_entity_id|:projection_entity_id" private/framework \
  | grep -v "calendar_node_ensurer.php"

Expected result:

(no output)

This enforces that runtime internals do not operate on
projection_entity_id.

Compatibility boundary grep
grep -RIn "projection_entity_id" private/framework \
  | grep -v "calendar_projection_resolver.php" \
  | grep -v "calendar_node_ensurer.php" \
  | grep -v "'projection_entity_id' =>" \
  | grep -v "projection_entity_id still exists"

Expected remaining result:

private/framework/expression/character_next_beat_suggester.php

Specifically:

$projectionEntityId = calendar_projection_entity_id(
    $pdo,
    $projectionId,
);

No additional runtime references are permitted.

Migration Completion Criteria

The migration is considered complete when:

runtime systems operate exclusively on projection_id
ingress normalization is universal
no runtime joins/filtering use projection_entity_id
no runtime maps are keyed by projection_entity_id
CI enforcement passes cleanly
projection_entity_id exists only at compatibility boundaries
Long-Term Goal

The long-term target architecture is complete removal of:

projection_entity_id
compatibility resolver infrastructure
compatibility persistence fields
outbound compatibility shaping

At that point:

projection_id

will be the only remaining projection identity in the system.