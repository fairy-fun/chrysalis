# how_to_view_prose

## Goal

Determine where prose is stored and where labels such as:

``` text
20250120-a
20250120-ab
20250120-b
20250120-f
```

are retrieved when viewing prose through the calendar workflows.

------------------------------------------------------------------------

## Canonical Prose Storage

The actual narrative text is stored in:

``` text
prose_drafts.prose_body
```

Repository evidence from:

``` text
private/framework/prose/resolve_calendar_event_attached_prose.php
```

The resolver loads prose with:

``` sql
SELECT
    pd.id,
    pd.entity_id,
    pd.title,
    pd.prose_body
FROM prose_drafts pd
WHERE pd.prose_family_id = :prose_family_id
ORDER BY pd.id DESC
LIMIT 1
```

and returns:

``` php
'prose_body' => $draft['prose_body']
```

Therefore:

``` text
Beat derivation
Character extraction
Ontology workflows
Title derivation
```

ultimately operate on:

``` text
prose_drafts.prose_body
```

------------------------------------------------------------------------

## How Calendar Events Point to Prose

For example:

``` text
calendar_event:7
```

Database inspection shows:

``` text
calendar_events.prose_body = "prose_family:16"
```

This field is not storing prose text.

Instead it stores a pointer:

``` text
prose_family:16
```

The resolver explicitly recognizes this pattern:

``` php
if (preg_match('/^prose_family:(\d+)$/', $semanticSurface))
{
    $proseFamilyId = (int)$matches[1];
}
```

------------------------------------------------------------------------

## Event 7 Resolution Chain

``` text
calendar_event:7
    ↓
calendar_events.prose_body
    = "prose_family:16"
    ↓
prose_family_id = 16
    ↓
prose_drafts.id = 26
    ↓
prose_drafts.prose_body
```

Database evidence:

``` text
prose_drafts.id = 26
title = "Workflow prose draft"
body length = 6729
```

The canonical prose body for Event 7 is therefore:

``` text
prose_drafts.id 26
→ prose_drafts.prose_body
```

------------------------------------------------------------------------

## Important Doctrine

The attached-prose resolver intentionally follows:

``` text
calendar_event
    → prose_family
    → prose_drafts
```

and not:

``` text
prose_projections
    → published_prose_draft_id
```

for ontology and derivation workflows.

This means export state is not required for:

-   beat derivation
-   title derivation
-   character tagging
-   ontology extraction

provided the prose family attachment exists.

------------------------------------------------------------------------

# Reference Labels (e.g. 20250120-a)

## Storage Location

Values such as:

``` text
20250120-a
20250120-ab
20250120-b
20250120-f
```

are stored in:

``` text
calendar_event_reference_labels.reference_label
```

Database evidence:

``` text
calendar_event_reference_labels.reference_label = '20250120-a'
calendar_event_id = 2
```

Relationship:

``` text
calendar_event_reference_labels.calendar_event_id
    → calendar_events.id
```

------------------------------------------------------------------------

## Query Path

Repository file:

``` text
private/framework/calendar/calendar_week_prose_query.php
```

loads:

``` sql
LEFT JOIN calendar_event_reference_labels cerl
    ON cerl.calendar_event_id = e.id
```

and selects:

``` sql
cerl.reference_label
```

which becomes:

``` php
$event['reference_label']
```

------------------------------------------------------------------------

## Rendering Path

Repository file:

``` text
private/framework/calendar/calendar_week_prose_label_formatter.php
```

reads:

``` php
$displayReference = trim(
    (string)($event['reference_label'] ?? '')
);
```

and injects it into the label:

``` php
return sprintf(
    '%s — Week %d • Day %d • %s • %s •(%s)',
    ...
    $timeLabel,
    $displayReference,
    $event['entity_id']
);
```

------------------------------------------------------------------------

## Canonical Label Assembly

Storage chain:

``` text
calendar_events.id = 2
        ↓
calendar_event_reference_labels.calendar_event_id = 2
        ↓
calendar_event_reference_labels.reference_label = '20250120-a'
        ↓
query_calendar_time_events()
        ↓
event['reference_label']
        ↓
format_calendar_week_prose_label()
        ↓
canonical_label
```

Result:

``` text
1.2.1.1 — Week 1 • Day 2 • Morning • 20250120-a •(calendar_event:2)
```

------------------------------------------------------------------------

## Summary

### Canonical prose text

``` text
prose_drafts.prose_body
```

### Calendar event → prose linkage

``` text
calendar_events.prose_body
    = "prose_family:<id>"
```

### Reference label storage

``` text
calendar_event_reference_labels.reference_label
```

### Label rendering path

``` text
calendar_event_reference_labels.reference_label
    → query_calendar_time_events()
    → event['reference_label']
    → format_calendar_week_prose_label()
    → canonical_label
```
