# How to Create an Idea and Link It to a Character Through Facts

## Purpose

This guide documents the currently verified database pattern for creating an Idea entity and linking it to a Character using `entity_linked_facts`.

---

# Verified Facts

The following have been verified from the live schema:

## Ideas are entities

Ideas are stored in:

```text
entity_texts
```

with:

```text
entity_type_id = 'idea'
```

Verified examples include:

```text
idea_shay_truth_over_comfort
IDEA-001
IDEA-SHAY-001
```

---

## Ideas can be classified

Classification table:

```text
idea_classifications
```

Columns:

```text
idea_entity_id
category_id
domain_id
```

Examples:

```text
idea_category_independent
idea_category_relational

idea_domain_truth
idea_domain_identity
idea_domain_loyalty
idea_domain_partnered_relationships
```

---

## Ideas can be scoped

Scope table:

```text
idea_scopes
```

Columns:

```text
idea_entity_id
subject_scope_id
```

Verified scope values:

```text
subject_scope_self
subject_scope_team_v2
subject_scope_family
subject_scope_community
```

---

## Entity relationships

Relationships are stored in:

```text
entity_linked_facts
```

Schema:

```text
linked_fact_id
subject_entity_id
context_entity_id
fact_type_id
object_entity_id
source_document
notes
created_at
updated_at
```

This mechanism is verified and already connects:

- Character → Song
- Song → Artist
- Event → Theme
- Relationship → Event
- Character → Event-scoped Limbic State

---

# Step 1: Create the Idea Entity

Example:

```sql
INSERT INTO entity_texts (
    entity_id,
    canonical_label,
    summary,
    description,
    created_at,
    updated_at,
    entity_type_id
)
VALUES (
           'IDEA-002',
           'Truth should be spoken even when costly',
           'A principle prioritizing truth over comfort',
           'The belief that truth should not be suppressed merely to avoid discomfort.',
           NOW(),
           NOW(),
           'idea'
       );
```

---

# Step 2: Classify the Idea

Example:

```sql
INSERT INTO idea_classifications (
    idea_entity_id,
    category_id,
    domain_id
)
VALUES (
    'IDEA-002',
    'idea_category_independent',
    'idea_domain_truth'
);
```

---

# Step 3: Assign Scope

Example:

```sql
INSERT INTO idea_scopes (
    idea_entity_id,
    subject_scope_id,
    created_at,
    updated_at
)
VALUES (
           'IDEA-002',
           'subject_scope_self',
           NOW(),
           NOW()
       );
```

or

```sql
INSERT INTO idea_scopes (
    idea_entity_id,
    subject_scope_id,
    created_at,
    updated_at
)
VALUES (
           'IDEA-002',
           'subject_scope_team_v2',
           NOW(),
           NOW()
       );
```

---

# Step 4: Create Fact Types

If these fact types do not already exist, add them to `classvals`:

```sql
INSERT INTO classvals (
    id,
    classval_type_id,
    code,
    label,
    created_at
)
VALUES
(
    'fact_type_character_holds_idea',
    'fact_type',
    'character_holds_idea',
    'Character Holds Idea',
    NOW()
),
(
    'fact_type_event_expresses_idea',
    'fact_type',
    'event_expresses_idea',
    'Event Expresses Idea',
    NOW()
)
ON CONFLICT (id) DO UPDATE
SET
    code = EXCLUDED.code,
    label = EXCLUDED.label;
```

---

# Step 5: Link Character to Idea

Proposed relationship:

```text
Character
    ↓
fact_type_character_holds_idea
    ↓
Idea
```

Example:

```sql
INSERT INTO entity_linked_facts (
    linked_fact_id,
    subject_entity_id,
    fact_type_id,
    object_entity_id,
    created_at,
    updated_at
)
VALUES (
    10001,
    'CHAR-MAIN-001',
    'fact_type_character_holds_idea',
    'IDEA-002',
    NOW(),
    NOW()
);
```

Meaning:

```text
CHAR-MAIN-001 holds IDEA-002
```

---

# Optional: Event-Scoped Character Idea

Pattern:

```text
Character
    ↓
Event
    ↓
Idea
```

Example:

```sql
INSERT INTO entity_linked_facts (
    linked_fact_id,
    subject_entity_id,
    context_entity_id,
    fact_type_id,
    object_entity_id,
    created_at,
    updated_at
)
VALUES (
    10002,
    'CHAR-MAIN-001',
    'calendar_event:140',
    'fact_type_event_character_idea',
    'IDEA-002',
    NOW(),
    NOW()
);
```

Meaning:

```text
During calendar_event:140,
the character was operating from IDEA-002.
```
