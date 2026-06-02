How to View a Character
Purpose

A character in Chrysalis is not represented by a single row or a single JSON document.

Character information is intentionally distributed across specialized tables and resolved through views. As a result, inspecting only characters or character_profiles.profile_json will often produce an incomplete or misleading understanding of the character.

This document describes the preferred approach for viewing character data.

The Core Principle

Do not ask:

What is stored in this row?

Ask:

What information resolves for this character?

The database is designed around resolution rather than denormalized storage.

Character Data Exists in Layers
Character Identity

Primary entity:

characters

Provides the canonical character record.

Character Profiles

Primary table:

character_profiles

Provides profile containers such as:

profile_type_public_name
profile_type_legal_name
profile_type_biography
profile_type_appearance
profile_type_personality
profile_type_social_role

A profile row is not necessarily complete merely because it exists.

Likewise, a profile is not necessarily empty merely because:

{}

appears in profile_json.

Profile Attributes

Primary tables:

character_profile_attributes
character_profile_attribute_tags

These tables store structured profile facts.

Examples:

Hair Color
Eye Color
Skin Tone
Appearance Presence
Appearance Contrast

These values are often represented as classvals rather than free text.

Classvals

Primary table:

classvals

Many profile attributes resolve through classvals.

Example:

hair_brown
→ Brown

eye_hazel
→ Hazel

appearance_presence_balanced
→ Balanced Presence

The stored value is not necessarily the value intended for display.

Measurements

Primary table:

character_measurements

Stores objective measurements.

Examples:

Height
Weight
Future body measurements

A profile may depend on measurements without duplicating them in profile JSON.

Appearance Example

Consider:

CHAR-MAIN-001

The appearance profile JSON is:

{}

A superficial inspection suggests the profile is empty.

However:

character_profile_attributes

contains:

Hair Color → Brown
Eye Color → Hazel
Skin Tone → Fair
Appearance Contrast → Medium
Appearance Presence → Balanced

And:

character_measurements

contains:

Height → 64 inches

Therefore the appearance profile is structurally populated despite an empty JSON payload.

Profile Population Types
Physically Empty

No JSON content.

No linked attributes.

No linked measurements.

No linked profile data.

Structurally Populated

Profile information exists through linked systems.

Examples:

Attributes
Classvals
Measurements

JSON may still be empty.

Narratively Populated

Profile JSON contains meaningful descriptive content.

Examples:

Biography summary
Personality description
Social role notes
Appearance narrative
Fully Populated

Both structured and narrative information exist.

Example:

Structured:
Hair color
Eye color
Height

Narrative:
Appearance summary
Presentation notes
Distinguishing features
Preferred Consumption Pattern

Consumers should prefer resolved views over direct table inspection whenever available.

Examples include:

v_character_identity_resolved
v_character_identity_with_aliases

v_character_attribute_resolved
v_character_attribute_resolved_primary

v_character_appearance_resolved

Views provide a resolved representation that reflects the actual character state.

Character Profile JSON Is Not the Whole Character

A recurring mistake is treating:

character_profiles.profile_json

as the complete profile.

This is incorrect.

Profile JSON is one layer within a broader character-resolution system.

Many profile facts may be sourced from:

character_profile_attributes
classvals
character_measurements
identity resolution
future resolver views

A character should therefore be viewed through the resolver layer rather than through any individual storage table.

Working Rule

When evaluating a character:

Start with the character record.
Resolve identity.
Resolve profile attributes.
Resolve measurements.
Resolve profile JSON.
Combine all resolved sources.
Only then assess profile completeness.

A character is the resolved aggregate of all character-related data, not any single row in the database.