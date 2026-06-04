# How to Write Works and Character Work Influences

## Purpose

This guide describes the live work-influence model currently represented in the Chrysalis database.

The system separates:

```text
The work itself
        from
The character's interpretation of the work
```

A work may influence many characters.

Different characters may derive different meanings from the same work.

---

# Core Doctrine

The work model answers two different questions:

```text
What is the work?

and

How did the work affect a character?
```

These questions are stored separately.

Conceptually:

```text
Work
    ↓
Character Exposure
    ↓
Interpretation
    ↓
Internalisation
    ↓
Behaviour
```

The work layer is relatively objective.

The influence layer is character-specific.

---

# Database Model

Verified live tables relevant to the work-influence architecture include:

```text
works
character_work_influences
work_source_type_classvals
character_exposure_phase_classvals
```

Conceptual structure:

```text
Work
    ↓
Character Exposure
    ↓
Character Interpretation
    ↓
Character Internalisation
    ↓
Character Behaviour
```

The system separates:

```text
Work Metadata
        from
Character Influence Data
```

A work may influence many characters.

A character may be influenced by many works.

---

# Work Records

Primary table:

```text
works
```

Verified schema:

```text
work_id
work_title
source_type
creator_name
publication_year
canonical_description
source_document
created_at
updated_at
```

A row in this table represents the work itself.

The work record should describe:

- what the work is
- who created it
- when it was published
- what it broadly contains

The work record should not contain character-specific interpretation.

---

# Canonical Description

Field:

```text
canonical_description
```

This field should describe the work from an external perspective.

Good:

```text
A political novel examining surveillance,
institutional power,
and social control.
```

Avoid:

```text
This book taught Kai to distrust governments.
```

That belongs in a character influence record.

---

# Source Type

Field:

```text
source_type
```

Vocabulary is governed through:

```text
work_source_type_classvals
```

When documentation and live classvals disagree, prefer the live classval vocabulary.

Do not invent source types.

---

# Character Work Influences

Primary table:

```text
character_work_influences
```

Verified schema:

```text
character_work_influence_id
character_id
work_id
exposure_phase_id
core_message
internalised_rule
behavioural_effects
tonal_effects
contradiction_flags
notes
source_document
created_at
updated_at
```

This table represents how a specific work affected a specific character.

---

# Important Distinction

A work existing does not imply anyone has been influenced by it.

Likewise:

```text
Work Exists
    ≠
Character Has Read It
```

and

```text
Character Has Read It
    ≠
Character Interpreted It Correctly
```

and

```text
Character Has Read It
    ≠
Character Was Changed By It
```

The influence record documents actual impact.

---

# Influence Is Not Agreement

Encountering a work does not imply acceptance.

A character may:

```text
Accept the work

Reject the work

Misunderstand the work

Partially internalize the work

Develop a reaction against the work
```

The influence record documents the actual effect.

It does not assume approval.

A rejected work may still produce substantial influence.

---

# Exposure Phase

Vocabulary layer:

```text
character_exposure_phase_classvals
```

Verified schema:

```text
id
code
label
created_at
```

Field:

```text
character_work_influences.exposure_phase_id
```

references the phase during which the character encountered the work.

Examples might include:

```text
Childhood
Adolescence
University
Military Service
Early Career
Midlife
```

The exact vocabulary should always come from the live classval table.

Exposure phase provides important interpretive context.

The same work encountered at different stages of life may produce very different influence records.

---

# Core Message

Field:

```text
core_message
```

Represents the message the character believes the work communicates.

This is not necessarily author intent.

This is not necessarily objective truth.

It is the character's interpretation.

---

# Internalised Rule

Field:

```text
internalised_rule
```

Represents what the character adopted as a personal operating principle.

Examples:

```text
Never reveal all available information.

Always maintain leverage.

Trust incentives more than promises.

Prepare for betrayal before cooperation.
```

---

# Behavioural Effects

Field:

```text
behavioural_effects
```

Describes observable behavioural consequences.

Examples:

```text
Plans extensively.

Avoids impulsive decisions.

Maintains redundant escape options.

Investigates motives before acting.
```

---

# Tonal Effects

Field:

```text
tonal_effects
```

Describes shifts in emotional tone or worldview.

Examples:

```text
More idealistic.

More cynical.

More hopeful.

More suspicious.
```

---

# Contradiction Flags

Field:

```text
contradiction_flags
```

Documents tensions created by the influence.

Examples:

```text
Advocates honesty while practicing deception.

Values freedom while supporting coercive systems.

Promotes empathy while remaining emotionally distant.
```

---

# Notes

Field:

```text
notes
```

Stores supporting context.

Examples:

```text
Assigned by a mentor.

Studied during rehabilitation.

Read repeatedly across several years.

Associated with a major life transition.
```

---

# Example

## Work

```text
Title:
    The Architecture of Power

Creator:
    Helena Voss

Publication Year:
    1998

Canonical Description:
    A political treatise examining legitimacy,
    authority,
    and institutional behavior.
```

## Character Influence

```text
Character:
    Shay

Core Message:
    Institutions rarely act against their own interests.

Internalised Rule:
    Always identify incentives before trusting claims.

Behavioural Effects:
    Investigates motives before accepting information.

Tonal Effects:
    Moderate institutional skepticism.

Contradiction Flags:
    Still seeks approval from authority figures.

Notes:
    Studied extensively during professional training.
```

---

# Writing Guidance

## Describe the Work Objectively

Focus on the work itself.

Do not embed character interpretation in the work record.

---

## Record Interpretation Separately

Capture:

```text
What message was perceived?

What rule was internalized?

What changed?
```

The influence record is the character-facing layer.

---

## Focus on Change

A strong influence record explains:

```text
Behavior changed.

Beliefs changed.

Expectations changed.

Emotional tone changed.
```

---

## Capture Contradictions

Characters often internalize lessons imperfectly.

Document those tensions.

Contradictions are useful narrative information.

---

# Common Mistakes

## Mistake 1

Confusing the work with the influence.

## Mistake 2

Writing a plot summary instead of documenting impact.

## Mistake 3

Recording behaviour without the underlying lesson.

---

# Completion Checklist

A strong work record should generally answer:

```text
What is the work?
Who created it?
What type of work is it?
When was it published?
What is its canonical description?
```

A strong influence record should generally answer:

```text
When did the character encounter it?
What message did they take from it?
What rule did they internalize?
How did behavior change?
How did worldview change?
What contradictions emerged?
```

---

# Scope Boundary

This guide documents:

- works
- work metadata
- character_work_influences
- character_exposure_phase_classvals
- influence doctrine
- interpretation doctrine

This guide does not document:

- event knowledge
- character profiles
- relationship knowledge
- calendar events
- prose workflows
- API endpoints

Those concerns belong to separate systems.
