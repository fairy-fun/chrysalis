# Character Knowledge Packet Resolver Index

## Purpose

This document is the maintained index for the Character Knowledge Packet read surface.

It exists to prevent resolver-helper sprawl.

If a new helper file, bridge reader, or API surface is introduced for the knowledge packet,
it must be listed here.

---

## Canonical Rule

The Character Knowledge Packet is:

- a resolver-assembled read model
- not a source of canon
- not a write surface
- not a replacement for underlying resolver views

Profiles are not canon.

Relationships and facts are canon.

The knowledge packet consumes resolved and canonical surfaces.
It does not redefine them.

---

## Current Entry Points

### Public API endpoint

- `public_html/pecherie/chill-api/character/resolve_knowledge_packet.php`

### Framework resolver

- `private/framework/character/resolve_knowledge_packet.php`

---

## Current Resolver Assembly Order

1. Character
2. Appearance
3. Relationships
4. Relationship Facts
5. Character Facts
6. Knowledge Packet
7. Gap Analysis
8. Profile Generation

This order must remain resolver-first.

Do not invert it into profile-first authoring.

---

## Identity Bridge

### Character-facing identity

- `v_character_resolved.character_id`

### Canonical graph bridge

- `characters.entity_id`

### Rule

`character_id` is the public character identity.

`entity_id` is the canonical downstream graph identity used for:

- relationships
- relationship facts
- character facts

The resolver must bridge:

`character_id -> characters.entity_id`

before traversing canon-bearing downstream surfaces.

---

## Upstream Sources

### Character block

Source:

- `v_character_resolved`

Current resolver function:

- `fetch_resolved_character_row(...)`

Authority:

- resolved character read model

Notes:

- this is the character-facing aggregate surface
- it does not expose `entity_id`
- it must not be expanded to absorb knowledge-packet payloads

### Character/entity bridge

Source:

- `characters`

Current resolver function:

- `fetch_character_entity_bridge(...)`

Authority:

- canonical bridge from character identity to graph identity

### Appearance block

Primary source tables:

- `character_profiles`
- `character_profile_attributes`
- `character_profile_attribute_tags`
- `appearance_tags`

Materialized read model:

- `v_character_appearance_resolved`

Current resolver functions:

- `resolve_character_appearance(...)`
- `fetch_materialized_character_appearance_rows(...)`
- `derive_character_appearance_rows_from_source(...)`

Authority:

- source-table appearance tag assignments with materialized-table convenience

Notes:

- the materialized table is a read model, not the canonical source
- when `v_character_appearance_resolved` is empty for a character, the resolver falls back to source-table derivation
- rebuilds are handled by `rebuild_character_appearance_resolved(...)`

### Relationships block

Source:

- `v_character_relationship_packet`

Current resolver function:

- `fetch_character_relationship_packets(...)`

Authority:

- relationship packet read surface for the bridged character entity

Notes:

- prefer this packet surface for relationship payloads
- do not reconstruct relationship logic in PHP when the view already resolves it

### Relationship facts block

Source:

- `v_relationship_fact_resolved`
- joined through `relationships`

Current resolver function:

- `fetch_character_relationship_facts(...)`

Authority:

- flattened resolved relationship-attached fact surface

Notes:

- relationship-attached facts remain relationship canon
- the knowledge packet only flattens them for consumption

### Character facts block

Source:

- `canonical_entity_linked_facts_global`

Current resolver function:

- `fetch_character_facts(...)`

Authority:

- canonical global fact surface keyed by `subject_entity_id`

Notes:

- this is direct canon, not profile content
- if a more resolved character-fact view is introduced later, add it here before switching the resolver

---

## Current Response Shape

```json
{
  "character_id": "...",
  "entity_id": "...",
  "character": { "...": "..." },
  "appearance": [
    {
      "character_id": "...",
      "attribute_id": 0,
      "attribute_type_id": "...",
      "value_classval_id": "...",
      "value_classval_code": "...",
      "value_classval_label": "...",
      "tag_id": "...",
      "tag_code": "...",
      "tag_label": "..."
    }
  ],
  "relationships": [ "..." ],
  "relationship_facts": [ "..." ],
  "character_facts": [ "..." ]
}
```

The outer API endpoint currently wraps this as:

```json
{
  "ok": true,
  "data": { "...": "..." }
}
```

---

## Helper File Inventory

The following files currently feed the knowledge packet directly:

- `private/framework/character/resolve_character_appearance.php`
- `private/framework/character/resolve_knowledge_packet.php`
- `public_html/pecherie/chill-api/character/resolve_character_appearance.php`
- `public_html/pecherie/chill-api/character/resolve_knowledge_packet.php`
- `public_html/pecherie/chill-api/character/rebuild_character_appearance_resolved.php`

The following database surfaces are consumed directly by that resolver stack:

- `v_character_resolved`
- `characters`
- `v_character_appearance_resolved`
- `character_profiles`
- `character_profile_attributes`
- `character_profile_attribute_tags`
- `appearance_tags`
- `classvals`
- `v_character_relationship_packet`
- `v_relationship_fact_resolved`
- `relationships`
- `canonical_entity_linked_facts_global`

If any new helper file is introduced for:

- bridge resolution
- packet flattening
- payload shaping
- relationship read fallback
- fact read fallback
- appearance read fallback

it must be added to this inventory.

---

## Source Classification

### Source of truth

- `characters.entity_id`
- `relationships`
- `canonical_entity_linked_facts_global`
- `character_profiles`
- `character_profile_attributes`
- `character_profile_attribute_tags`
- `appearance_tags`
- canonical relationship fact lineage as surfaced through `v_relationship_fact_resolved`

### Resolver convenience surfaces

- `v_character_resolved`
- `v_character_appearance_resolved`
- `v_character_relationship_packet`
- `v_relationship_fact_resolved`

### Consumption-only payloads

- knowledge packet JSON returned by the API

The knowledge packet is always consumption-only.

---

## Change Checklist

When modifying the Character Knowledge Packet system:

1. Update this index if a new helper file or upstream surface is added.
2. Preserve `character_id -> entity_id` bridge discipline.
3. Prefer existing resolved SQL views over PHP reconstruction.
4. Do not move relationship or fact canon into profile payloads.
5. Do not extend `v_character_resolved` just to carry knowledge-packet data.
6. Keep the endpoint thin and the resolver procedural.
7. Treat `v_character_appearance_resolved` as a materialized convenience surface, not the canonical source of appearance tag assignments.

---

## Anti-Patterns

Do not:

- treat the knowledge packet as canon
- copy relationship logic already present in SQL views into PHP
- use profile JSON as the canonical fact source
- bypass `characters.entity_id` when resolving downstream relationship/fact surfaces
- treat `v_character_appearance_resolved` as authoritative when source tables disagree
- add helper files without indexing them here
