# How to Add a Secondary Character

This guide documents the canonical workflow for adding a supporting/secondary character into the live Chrysalis ontology and semantic-resolution system.

---

# Canonical doctrine

Character identity authority is:

```text
entities.id
```

Example:

```text
CHAR-SUP-997
```

Do NOT synthesize identifiers from prose.

Character resolution should ultimately succeed through semantic surfaces such as:

- `entity_labels.label`
- `semantic_aliases.alias`
- `entity_texts.canonical_label`
- `characters.search_name`

---

# Step 1 — Create the canonical entity

```sql
INSERT INTO entities (
    id,
    entity_type_id
)
VALUES (
    'CHAR-SUP-997',
    'entity_type_character'
);
```

---

# Step 2 — Create the character row

Verified live requirements:

- `character_code_type_id`
- `character_number`

Supporting characters use:

```text
character_code_type_sup
```

Example:

```sql
INSERT INTO characters (
    character_id,
    character_code_type_id,
    character_number,
    char_name_full,
    char_name_last,
    search_name,
    entity_id
)
VALUES (
    'CHAR-SUP-997',
    'character_code_type_sup',
    997,
    'Mrs Higgins',
    'Higgins',
    'mrs higgins',
    'CHAR-SUP-997'
);
```

Important:

Do NOT misuse:

```text
char_name_first
```

for honorifics like:

```text
Mrs
Sir
Duke
```

Those belong in the honorific system.

---

# Step 3 — Add canonical semantic resolution surfaces

Without these rows, extraction/tagging workflows may not resolve the character.

## 3a. Add canonical label

```sql
INSERT INTO entity_labels (
    entity_id,
    label
)
VALUES (
    'CHAR-SUP-997',
    'Mrs Higgins'
);
```

---

## 3b. Add canonical searchable entity text

IMPORTANT:

`canonical_label_normalized`
is a generated column.

Do NOT insert into it directly.

Correct insert:

```sql
INSERT INTO entity_texts (
    entity_id,
    canonical_label,
    search_text,
    entity_type_id,
    created_at,
    updated_at
)
VALUES (
    'CHAR-SUP-997',
    'Mrs Higgins',
    'Mrs Higgins Higgins',
    'entity_type_character',
    NOW(),
    NOW()
);
```

---

## 3c. Add semantic aliases

This enables prose extraction workflows to resolve alternate surface forms.

```sql
INSERT INTO semantic_aliases (
    id,
    alias_type_id,
    alias_scope_id,
    alias,
    resolution_priority,
    entity_id,
    created_at
)
VALUES
    (
        'alias_char_sup_997_mrs_higgins',
        'sat_synonym',
        'sasc_entity_name',
        'mrs higgins',
        100,
        'CHAR-SUP-997',
        NOW()
    ),
    (
        'alias_char_sup_997_mrs_dot_higgins',
        'sat_synonym',
        'sasc_entity_name',
        'mrs. higgins',
        100,
        'CHAR-SUP-997',
        NOW()
    ),
    (
        'alias_char_sup_997_higgins',
        'sat_synonym',
        'sasc_entity_name',
        'higgins',
        80,
        'CHAR-SUP-997',
        NOW()
    );
```

---

# Optional — Honorific ontology hardening

Current live schema uses:

```text
character_honorific
```

Future direction:

```text
classval_type_classvals
→ classvals
→ character_honorific bridge
```

Recommended reusable ontology ids:

```text
CLASSVAL_TYPE_HONORIFIC
CLASSVAL_HONORIFIC_MRS
CLASSVAL_HONORIFIC_SIR
```

---

# Verification queries

## Verify entity exists

```sql
SELECT *
FROM entities
WHERE id = 'CHAR-SUP-997';
```

---

## Verify character row

```sql
SELECT *
FROM characters
WHERE entity_id = 'CHAR-SUP-997';
```

---

## Verify semantic resolution surfaces

```sql
SELECT entity_id, label
FROM entity_labels
WHERE entity_id = 'CHAR-SUP-997'

UNION ALL

SELECT entity_id, canonical_label
FROM entity_texts
WHERE entity_id = 'CHAR-SUP-997'

UNION ALL

SELECT entity_id, alias
FROM semantic_aliases
WHERE entity_id = 'CHAR-SUP-997';
```

---

# Expected workflow behavior

After these inserts:

- prose extraction workflows should resolve:
    - `Mrs Higgins`
    - `Mrs. Higgins`
    - `Higgins`

to:

```text
CHAR-SUP-997
```

without synthesizing identifiers.

No ontology mutation should occur automatically from extraction alone.

### Organizational roles are institutional ontology, not honorific ontology

Institutional titles MUST be modeled through:

- `company_roles`
- `company_assignments`
- `company_assignment_roles`

Do NOT place organizational titles into:

- `entity_labels`
- `semantic_aliases`
- honorific systems
- character canonical labels

Example:

`Executive Administrator`

belongs in:

`company_roles.role_label`

NOT:

```text
Mrs Executive Administrator Higgins
```

or:

`entity_labels.label
`

Recommended canonical organizational role insertion pattern:

```sql
INSERT INTO company_roles (
    role_id,
    role_code,
    role_label
)
SELECT
    'ROLE-EXEC-ADMIN',
    'executive_administrator',
    'Executive Administrator'
WHERE NOT EXISTS (
    SELECT 1
    FROM company_roles
    WHERE role_id = 'ROLE-EXEC-ADMIN'
);
```

Recommended canonical company affiliation:

```sql
INSERT INTO company_assignments (
    company_assignment_id,
    member_entity_id,
    company_id,
    status_id
)
SELECT
    'COMPASSIGN-CHAR-SUP-997-RBDS',
    'CHAR-SUP-997',
    'COMP-001',
    'status_active'
WHERE NOT EXISTS (
    SELECT 1
    FROM company_assignments
    WHERE company_assignment_id = 'COMPASSIGN-CHAR-SUP-997-RBDS'
);
```

Recommended assignment-role linkage:

```sql
INSERT INTO company_assignment_roles (
    company_assignment_id,
    role_id,
    is_primary
)
SELECT
    'COMPASSIGN-CHAR-SUP-997-RBDS',
    'ROLE-EXEC-ADMIN',
    1
WHERE NOT EXISTS (
    SELECT 1
    FROM company_assignment_roles
    WHERE company_assignment_id = 'COMPASSIGN-CHAR-SUP-997-RBDS'
      AND role_id = 'ROLE-EXEC-ADMIN'
);
```

Recommended canonical lookup authority:

- `company_roles.role_id`
- `company_roles.role_code`

Avoid joining on:


`role_label
`

because labels are mutable presentation surfaces.