# how_to_add_prose_to_an_existing_event

## Workflow

**Workflow ID**

```text
calendar_event_add_prose
```

**Intent**

```text
Add prose to an existing calendar_event
through prose-family-aware projection topology
```

---

## State Machine Overview

```text
await_calendar_event_entity_id
            │
            ▼
resolve_calendar_event_family
            │
            ▼
await_prose_text
            │
            ▼
persist_prose_draft
            │
            ▼
await_optional_reference_label
         ┌──┴──┐
         │     │
blank    │     │ label entered
         │     ▼
         │ persist_reference_label
         │     │
         └─────▼
      terminal_prose_created
```

Failure terminals:

```text
terminal_missing_entity_id

terminal_prose_persist_failed

terminal_reference_label_failed
```

---

## State 1: await_calendar_event_entity_id

Prompt:

```text
What is the existing calendar event?
You can enter:

calendar_event:5
5
1.2.1.3
...
```

Stores:

```text
input.calendar_event_entity_id
```

Examples:

```text
7
```

or

```text
calendar_event:7
```

Transition:

```text
resolve_calendar_event_family
```

---

## State 2: resolve_calendar_event_family

Action:

```text
driver = prose
operation = resolve_calendar_event_family
```

Payload:

```php
[
    'calendar_event_reference'
        => $input.calendar_event_entity_id
]
```

Purpose:

```text
calendar_event
    ↓
prose family
    ↓
projection
    ↓
published draft
```

Populates context such as:

```text
context.calendar_event
context.prose_family
```

Example observed output:

```text
calendar_event.id = 7

prose_family.id = 16

prose_projection_id = 24

published_prose_draft_id = 26
```

Transition:

```text
await_prose_text
```

---

## State 3: await_prose_text

Prompt:

```text
Enter the prose text.
```

Stores:

```text
input.prose
```

Transition:

```text
persist_prose_draft
```

---

## State 4: persist_prose_draft

This is the actual prose write step.

Action:

```text
driver = prose
operation = create_calendar_event_family_draft
```

Payload:

```php
[
    'entity_id' => 'prose:' . uniqid(),
    'calendar_event_entity_id'
        => $context.calendar_event.entity_id,
    'title'
        => 'Workflow prose draft',
    'prose_body'
        => $input.prose,
    'draft_status_id'
        => 'prose_status_draft',
    'projection_type_id'
        => 'projection_type_timeline_view',
    'role_id'
        => 'prose_projection_role_primary',
    'projection_order'
        => 1
]
```

Expected effects:

```text
INSERT prose_drafts

possibly create/update prose_projections

attach to existing prose_family
```

---

## State 5: await_optional_reference_label

Prompt:

```text
Optional:
enter a short lookup label

Example:

20250121-z
```

Stores:

```text
input.reference_label
```

Examples:

```text
20250120-a
20250120-ab
20250120-f
```

### Branch A

Blank input:

```text
terminal_prose_created
```

No reference label is written.

### Branch B

Reference label supplied:

```text
persist_reference_label
```

---

## State 6: persist_reference_label

Action:

```text
upsert_calendar_event_reference_label
```

Payload:

```php
[
    'calendar_event_id'
        => $context.calendar_event.id,

    'reference_label'
        => $input.reference_label
]
```

Purpose:

Writes:

```text
calendar_event_reference_labels.reference_label
```

This is the same field used later when rendering labels such as:

```text
20250120-a
```

---

## Terminal Success

```text
terminal_prose_created
```

Returns:

```text
Prose draft created successfully
within prose-family-aware projection topology.
```

Produces a handoff to:

```text
calendar_event_process_attached_prose
```

with canonical identifiers including:

```text
calendar_event_entity_id
prose_entity_id
prose_projection_id
prose_family_entity_id
```

---

## What Gets Written

### Prose

Canonical destination:

```text
prose_drafts.prose_body
```

Written via:

```text
create_calendar_event_family_draft
```

### Optional Reference Label

Canonical destination:

```text
calendar_event_reference_labels.reference_label
```

Written via:

```text
upsert_calendar_event_reference_label
```

---

## Relationship to Beat Derivation

This workflow does **not** derive:

```text
title
beat
beat_type_id
character tags
location tags
```

It only:

```text
creates a prose draft
optionally creates a reference label
```

The intended next workflow is:

```text
calendar_event_process_attached_prose
```

which begins the prose-derived metadata pipeline.
