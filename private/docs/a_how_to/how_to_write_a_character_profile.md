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
``

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

Verified tables:

```text
character_profiles
character_profile_attributes
```

Conceptual structure:

```text
Character
    ↓
Profile
    ↓
Attributes
```

A single character may have many profiles.

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
