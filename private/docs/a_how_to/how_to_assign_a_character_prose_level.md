# Character Code Type Assignment Guide

Based on the verified live schema, `characters.character_code_type_id`
stores the selected value from `character_code_type_classvals.id`.

## Step 1 --- Verify Available Code Types

``` sql
SELECT
    id,
    code,
    label
FROM character_code_type_classvals
ORDER BY label;
```

Expected values currently include:

id                         code   label
  -------------------------- ------ --------------------------------------
character_code_type_main   MAIN   Main (or Primary if renamed)
character_code_type_sup    SUP    Supporting (or Secondary if renamed)

and potentially:

id                        code   label
  ------------------------- ------ ----------
character_code_type_ter   TER    Tertiary

if you have added it.

------------------------------------------------------------------------

## Step 2 --- Find the Character

Locate the target character:

``` sql
SELECT
    character_id,
    entity_id,
    char_name_full,
    character_code_type_id
FROM characters
ORDER BY char_name_full;
```

Or for a specific character:

``` sql
SELECT
    character_id,
    char_name_full,
    character_code_type_id
FROM characters
WHERE character_id = 'CHAR-MAIN-003';
```

------------------------------------------------------------------------

## Step 3 --- Assign the Code Type

Example: assign a character to the Primary category.

``` sql
UPDATE characters
SET character_code_type_id = 'character_code_type_main'
WHERE character_id = 'CHAR-MAIN-003';
```

Example: assign a character to the Secondary category.

``` sql
UPDATE characters
SET character_code_type_id = 'character_code_type_sup'
WHERE character_id = 'char_jorge_alvarez';
```

Example: assign a character to the Tertiary category.

``` sql
UPDATE characters
SET character_code_type_id = 'character_code_type_ter'
WHERE character_id = 'char_some_character';
```

------------------------------------------------------------------------

## Step 4 --- Verify the Change

``` sql
SELECT
    c.character_id,
    c.char_name_full,
    c.character_code_type_id,
    t.code,
    t.label
FROM characters c
LEFT JOIN character_code_type_classvals t
    ON t.id = c.character_code_type_id
WHERE c.character_id = 'CHAR-MAIN-003';
```

Expected result:

``` text
CHAR-MAIN-003
Kai Blackwood
character_code_type_main
MAIN
Primary
```

(or whatever code type was assigned).

------------------------------------------------------------------------

## Bulk Reclassification Example

Move all supporting characters to the new Tertiary category:

``` sql
UPDATE characters
SET character_code_type_id = 'character_code_type_ter'
WHERE character_code_type_id = 'character_code_type_sup';
```

Verify afterward:

``` sql
SELECT
    character_code_type_id,
    COUNT(*) AS character_count
FROM characters
GROUP BY character_code_type_id
ORDER BY character_code_type_id;
```

This is sufficient according to the verified live schema: the
character-to-code-type relationship is stored directly in
`characters.character_code_type_id`, which references entries in
`character_code_type_classvals`.
