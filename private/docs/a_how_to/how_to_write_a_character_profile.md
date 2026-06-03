# How to Write a Character Profile

## Purpose

This guide describes the character-profile model currently represented in the live database.

A character profile is used to capture relatively stable information about a character.

Examples:

- names
- aliases
- appearance
- voice
- psychological framing
- limbic tendencies
- identity-related context

A character profile is not intended to capture event-specific states.

---

# Core Doctrine

Character profiles answer questions such as:

```text
Who is this character?
What do they look like?
How do they speak?
How do they tend to regulate?
What psychological structures shape them?
```

Character profiles describe the character.

They do not describe a specific event.

---

# Important Distinction

## Character Profile

Represents:

```text
Character
    ↓
Profile
    ↓
Attributes
```

Examples:

```text
appearance
voice
identity
psychology
limbic tendencies
```

Stored in:

```text
character_profiles
character_profile_attributes
```

But in the live system, those two tables are only the center of the profile model, not the entire model.

---

## Event-Scoped Limbic State

Represents:

```text
Character
    ↓
Event
    ↓
State During That Event
```

Examples:

```text
Threat Activated
Window Regulated
Hyperaroused
Hypoaroused
```

Stored separately as facts.

Not part of the profile system.

---

# Database Model

Verified live tables relevant to profile architecture include:

```text
character_profiles
character_profile_type_classvals
character_profile_attributes
character_profile_attribute_type_classvals
character_profile_attribute_classvals
character_profile_attribute_tags
```

Conceptual structure:

```text
Character
    ↓
Profile Container
    ↓
Structured Attributes
    ↓
Classval and Tag Resolution
```

A single character may have many profiles.

The profile system is classval-backed, not just profile rows plus free-text attributes.

## Profile Containers

Primary table:

```text
character_profiles
```

This table defines the profile row itself:

- which character the profile belongs to
- which profile type it represents
- any profile-level JSON or prose payload stored on that row

It should be treated as the profile container layer.

## Profile-Type Classval Layer

Live table:

```text
character_profile_type_classvals
```

This table links profile types to classval vocabulary.

That means profile semantics are not defined only by the existence of a row in `character_profiles`.
Some profile structure is governed by classval-backed definitions attached to the profile type.

## Structured Attribute Layer

Primary table:

```text
character_profile_attributes
```

This table stores structured facts attached to a profile.

Attributes may be represented as:

- text values
- classval-backed values

In particular, `character_profile_attributes.value_classval_id` is an actively used storage path in the live DB.

It should not be treated as an edge case.

## Attribute-Type Classval Layer

Live table:

```text
character_profile_attribute_type_classvals
```

This table defines the classval vocabulary expected or allowed for a given attribute type.

For many structured domains, this is the vocabulary layer behind the profile attribute system.

## Attribute/Classval Binding Layer

Live table:

```text
character_profile_attribute_classvals
```

This table participates in the attribute-to-classval architecture at the stored-value layer.

Together with `character_profile_attributes.value_classval_id`, it shows that many attributes are expected to resolve through controlled values rather than raw prose.

## Attribute Tag Layer

Live table:

```text
character_profile_attribute_tags
```

This table stores tags attached to specific profile-attribute rows.

These tags are part of the structured profile model.
They do not live only in profile JSON.

## Practical Consequence

Any description of the profile system that documents only:

```text
character_profiles
character_profile_attributes
```

is materially incomplete relative to the live schema.

As of the current database snapshot referenced for this update, `character_profile_attributes.value_classval_id` is used by 30 of 45 current attribute rows.

That usage level means classval-backed attribute storage is a normal path, not a special case.

---

# Profile Types

Verified examples from the live database include:

```text
profile_type_public_name
profile_type_legal_name
profile_type_former_name
profile_type_alias
profile_type_appearance
```

Additional profile types may be added over time.

---

# Public Name Profiles

Purpose:

```text
How the character is publicly known.
```

Typical attributes:

```text
profile_attr_full_name
profile_attr_first_name
profile_attr_last_name
```

Example:

```text
Shay Aurelia Vertue
```

---

# Legal Name Profiles

Purpose:

```text
Official or legal identity.
```

Typical attributes:

```text
profile_attr_full_name
profile_attr_first_name
profile_attr_last_name
profile_attr_notes
```

Example:

```text
Shay Aurelia Vertue Young
```

---

# Former Name Profiles

Purpose:

```text
Names used prior to identity changes.
```

Typical attributes:

```text
profile_attr_full_name
profile_attr_notes
```

Example:

```text
Gemma Hartwell
```

---

# Alias Profiles

Purpose:

```text
Nicknames
Code names
Operational identities
Known aliases
```

Typical attributes:

```text
profile_attr_full_name
attr_alias_type
```

Example:

```text
Raven
```

Alias type:

```text
alias_type_codename
```

---

# Appearance Profiles

Purpose:

```text
How the character appears to others.
```

Verified attribute families:

```text
attr_hair_color
attr_eye_color
attr_skin_tone
attr_appearance_contrast
attr_appearance_presence
```

Example:

```text
Hair: Brown
Eyes: Hazel
Skin: Fair
Contrast: Medium
Presence: Balanced
```

Many appearance values resolve through classvals rather than free text.

Appearance-oriented profile attributes may also carry tags through:

```text
character_profile_attribute_tags
```

So appearance should be treated as structured resolved data, not only prose.

---

# Voice Profiles

Verified attribute types:

```text
attr_voice_tone
attr_voice_cadence
attr_voice_rhythm
attr_voice_vocab
attr_voice_quirk
attr_voice_emotional_baseline
```

Questions to answer:

```text
What does the character sound like?
How formal are they?
What vocabulary do they use?
What emotional tone do they project?
```

---

# Psychological Profiles

Verified attribute families:

```text
attr_psych_origin_mother
attr_psych_origin_father
attr_psych_god_filter
attr_psych_family_bind
attr_psych_naming_block
attr_psych_community_enforcement
attr_psych_religious_context
attr_psych_kaiju_model
```

Questions to answer:

```text
What shaped this character?
What systems do they inhabit?
What beliefs govern interpretation?
What fears and loyalties constrain them?
```

---

# Limbic Profile Attributes

Verified attribute families:

```text
attr_limbic_window
attr_limbic_threat
attr_limbic_safety
attr_limbic_attachment
attr_limbic_coreg
attr_limbic_discharge
attr_limbic_suppression
attr_limbic_arc
```

These represent enduring patterns and tendencies.

Examples:

```text
What tends to trigger threat activation?
What conditions produce safety?
How does co-regulation occur?
What is the attachment pattern?
What happens under suppression?
```

These are profile-level characteristics.

They are not event-level state records.

---

# Writing Guidance

When writing a profile:

## Focus on Stability

Capture information that remains true across many scenes and events.

Examples:

```text
Appearance
Voice
Identity
Attachment patterns
Threat signatures
Safety conditions
```

---

## Resolve Structured Values Before Writing Prose

Before drafting narrative text:

```text
Read the profile container
Read the profile attributes
Check whether each attribute is text-backed or classval-backed
Resolve value_classval_id values to display labels
Check for attribute-level tags that affect interpretation
```

Do not assume:

```text
profile_json contains the whole profile
free text is the default storage format
an empty JSON payload means the profile is empty
```

---

## Avoid Event Narration

Do not use profiles to record:

```text
What happened during a specific event
Temporary emotional states
Momentary reactions
Scene-specific behavior
```

Those belong elsewhere.

---

## Separate Tendencies from States

Good profile content:

```text
Tends to become threat activated when publicly challenged.
```

Poor profile content:

```text
Was threat activated during Event 33.
```

The first describes a tendency.

The second describes a historical event fact.

---

# Profile Completion Checklist

A strong profile should generally answer:

```text
Identity
Appearance
Voice
Psychology
Limbic tendencies
Aliases
Relevant notes
```

while avoiding scene-by-scene event history.

It should also respect the structured layer already present in the database.

---

# Scope Boundary

This guide documents:

- character-profile doctrine
- profile categories
- attribute families
- database-backed profile structure

This guide does not document:

- event-scoped limbic facts
- calendar relationships
- event participation
- event roles
- workflow implementation
- API endpoints

Those concerns belong to separate systems.
