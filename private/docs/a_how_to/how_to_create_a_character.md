# How To Create a Character in the Database

## Canonical Doctrine

Canonical character identity begins in:

```text
characters
```

This is the authoritative registry for character existence.

Do NOT begin character creation from:

```text
entity_labels
semantic_aliases
calendar_event_participants
```

Those are downstream ontology/support structures.

---

# Canonical Creation Order

```text
characters
    ->
entities
    ->
entity_labels
    ->
semantic_aliases
    ->
participant attachment
```

---

# Step 1 — Create the Canonical Character Row

## Table

```text
characters
```

## Minimum Required Fields

Recommended minimum insert:

```sql
INSERT INTO characters (
    character_id,
    character_code_type_id,
    character_number,
    entity_id,
    char_name_full,
    char_name_first,
    char_name_last
) VALUES (
             'CHAR-MAIN-006',
             'character_code_type_main',
             6,
             'CHAR-MAIN-006',
             'Kai Lysander Blackwood',
             'Kai',
             'Blackwood'
         );
```

---

# Canonical ID Doctrine

Recommended canonical identity format:

```text
CHAR-MAIN-###
```

Examples:

```text
CHAR-MAIN-001
CHAR-MAIN-002
CHAR-MAIN-003
```

`character_id` and `entity_id` should remain aligned unless a future ontology split becomes necessary.

---

# Step 2 — Create the Entity Row

## Table

```text
entities
```

## Insert

```sql
INSERT INTO entities (
    id,
    entity_type_id
) VALUES (
    'CHAR-MAIN-003',
    'entity_type_character'
);
```

This exposes the character to broader ontology resolution systems.

---

# Step 3 — Create Canonical Entity Labels

## Table

```text
entity_labels
```

Recommended inserts:

```sql
INSERT INTO entity_labels (
    entity_id,
    label
) VALUES
(
    'CHAR-MAIN-003',
    'Kai Lysander Blackwood'
),
(
    'CHAR-MAIN-003',
    'Kai Blackwood'
),
(
    'CHAR-MAIN-003',
    'Kai'
),
(
    'CHAR-MAIN-003',
    'Blackwood'
);
```

---

# Step 4 — Optional Alias Expansion

## Table

```text
semantic_aliases
```

Examples:

```sql
INSERT INTO semantic_aliases (
    id,
    alias_type_id,
    alias_scope_id,
    alias,
    entity_id,
    created_at
) VALUES
      (
          'alias_char_main_003_mr_blackwood',
          'sat_synonym',
          'sasc_entity_name',
          'Mr Blackwood',
          'CHAR-MAIN-003',
          NOW()
      ),
      (
          'alias_char_main_003_kai_l_blackwood',
          'sat_synonym',
          'sasc_entity_name',
          'Kai L Blackwood',
          'CHAR-MAIN-003',
          NOW()
      );
```

Use aliases for:

- abbreviations
- nicknames
- honorifics
- alternate spellings
- OCR variants
- prose shorthand

---

# Step 5 — Attach Character to Events

## Table

```text
calendar_event_participants
```

Example:

```sql
INSERT INTO calendar_event_participants (
    calendar_event_id,
    entity_id,
    role_id,
    subsequence_index
) VALUES (
             7,
             'CHAR-MAIN-003',
             '',
             0
         );
```

This creates participation linkage only.

It does NOT create ontology.

---

# Important Doctrine

## Character existence != event participation

These are separate concepts.

Correct topology:

```text
characters
    ->
ontology surfaces
    ->
event attachment
```

NOT:

```text
event mention
    ->
implicit ontology existence
```

---

# Recommended Future Repair

Character suggestion workflows should source canonical surfaces directly from:

```text
characters
```

instead of relying exclusively on:

```text
entity_labels
semantic_aliases
```

Otherwise, canonical characters may remain invisible to prose scanning systems.

