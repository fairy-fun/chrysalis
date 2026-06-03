==================================================
CHARACTER RESOLUTION WORKFLOW
==================================================

Step 1

Resolve Character

    SELECT *
    FROM v_character_resolved
    WHERE character_id = ?

Output:

    Character Resolution

--------------------------------------------------

Step 2

Resolve Relationships

    SELECT *
    FROM v_relationship_resolved
    WHERE entity_a_id = ?
       OR entity_b_id = ?

Output:

    Relationship Resolution

Includes:

    Relationship Metadata
    Relationship Fact Count
    Relationship Fact Packets

--------------------------------------------------

Step 3

Resolve Character Facts

    SELECT *
    FROM canonical_entity_linked_facts_global
    WHERE subject_entity_id = ?

Output:

    Character Fact Resolution

--------------------------------------------------

Step 4

Resolve Relationship Facts

Already surfaced through:

    v_relationship_resolved

Fact packets include:

    Fact Type
    Object Entity
    Qualifiers
    Governance Metadata

Example:

    First Met
        Age = 8

Output:

    Relationship Fact Resolution

--------------------------------------------------

Step 5

Build Character Knowledge Packet

Combine:

    Character Resolution
    Relationship Resolution
    Character Facts
    Existing Profile Content

Output:

    Character Knowledge Packet

--------------------------------------------------

Step 6

Gap Analysis

Ask:

What information already exists?

What information is actually missing?

Do NOT ask for information already present in:

    Attributes
    Measurements
    Relationships
    Relationship Facts
    Character Facts

Output:

    Gap Analysis

--------------------------------------------------

==================================================
HONORIFIC AND NOBILITY RESOLUTION
==================================================

Character identity is not limited to names.

Some characters also possess:

    Honorifics
    Noble Ranks
    Territorial Styles
    Spoken Titles
    Written Styles

These must be resolved separately.

--------------------------------------------------

Primary Sources

Base Character Record:

    characters

Supplemental Honorific Assignment:

    character_honorific_assignments

Honorific Registry:

    character_honorific

Nobility Rank Registry:

    honorific_nobility_ranks

--------------------------------------------------

Do Not Assume

A character's visible title is not necessarily stored in:

    characters.char_name_full

For example:

    Sebastian Bennett

may resolve as:

    Lord St George

depending on:

    honorific assignment
    nobility rank
    naming mode
    territorial association

--------------------------------------------------

Resolve Honorific Assignments

Query:

    SELECT *
    FROM character_honorific_assignments
    WHERE character_id = ?;

This provides:

    honorific_id
    nobility_rank_id
    naming_mode
    territorial_place_id
    territorial_label
    written configuration
    spoken configuration

Output:

    Character Honorific Resolution

--------------------------------------------------

Resolve Honorific Metadata

Query:

    SELECT *
    FROM character_honorific
    WHERE honorific_id = ?;

This provides:

    code
    label
    written_label
    spoken_label
    usage_type

Examples:

    LORD
    DUKE
    MARQUESS
    EARL
    VISCOUNT
    BARON
    SIR
    CAPTAIN

--------------------------------------------------

Resolve Noble Rank

Query:

    SELECT *
    FROM honorific_nobility_ranks
    WHERE rank_id = ?;

Current canonical ranks include:

    Duke
    Marquess
    Earl
    Viscount
    Baron

Output:

    Noble Rank Resolution

--------------------------------------------------

Territorial Naming

Some honorific assignments use:

    naming_mode = TERRITORIAL

When present, consumers should inspect:

    territorial_place_id
    territorial_label

Example:

    Character Name:
        Sebastian Bennett

    Honorific:
        Lord

    Territorial Label:
        St George

    Rendered Form:
        Lord St George

The rendered identity may differ substantially from
the underlying personal name.

--------------------------------------------------

Viewing a Noble Character

Recommended sequence:

    Resolve Character

        ↓

    Resolve Honorific Assignments

        ↓

    Resolve Honorific Metadata

        ↓

    Resolve Noble Rank

        ↓

    Resolve Relationships

        ↓

    Resolve Facts

        ↓

    Build Character Knowledge Packet

Without honorific resolution, a consumer may receive
an incomplete representation of the character's
social identity.

--------------------------------------------------

Working Rule

When viewing a character, resolve:

    character identity
    profile data
    measurements
    relationships
    facts
    honorific assignments
    noble ranks

before determining how the character should be
presented to readers.

A character's title, rank, and territorial style are
part of the resolved character model and should not
be inferred solely from character names.

Step 7

Generate Profiles

Generate:

    Biography
    Appearance Narrative
    Personality
    Social Role

From:

    Character Resolution
    Relationship Resolution
    Relationship Facts
    Character Facts
    Human Input

Output:

    Profile Generation

==================================================
RELATIONSHIP RESOLUTION PRINCIPLE
==================================================

Relationships define connections.

Facts define assertions.

Qualifiers define structured details.

Example:

    Relationship:
        Sebastian ↔ Kai

    Fact:
        First Met

    Qualifier:
        Age = 8

Profiles summarize resolved information.

Profiles are not the authoritative source of canon.

==================================================
CURRENT RESOLVER ENTRY POINTS
==================================================

Character:

    v_character_resolved

Relationship:

    v_relationship_resolved

Relationship Fact:

    v_relationship_fact_resolved

Canonical Facts:

    canonical_entity_linked_facts_global

==================================================
CURRENT CANONICAL PROOF OF CONCEPT
==================================================

Relationship:

    rel_seb_kai_foundational

Fact:

    fact_type_first_met

Object:

    entity_event_seb_kai_first_meeting

Qualifier:

    qualifier_type_age = 8

Resolved through:

    v_relationship_fact_resolved
    v_relationship_resolved

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

Appearance Tags

Primary tables:

appearance_tags
character_profile_attribute_tags

These tags are real structured appearance data.

They are attached to specific character profile attributes rather than living only in profile JSON.

Example chain:

character_id
→ character_profiles.profile_id
→ character_profile_attributes.attribute_id
→ character_profile_attribute_tags.tag_id
→ appearance_tags

This means appearance tags must be resolved from linked tables.

They should not be assumed absent just because profile_json is empty.

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
Appearance Tags

JSON may still be empty.

Narratively Populated

Profile JSON contains meaningful descriptive content.

Examples:

Biography summary
Appearance narrative
Personality description
Social role notes
Fully Populated

Both structured and narrative information exist.

Example:

Structured:
Hair color
Eye color
Height
Appearance tags

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
appearance_tags
identity resolution
future resolver views

A character should therefore be viewed through the resolver layer rather than through any individual storage table.

Working Rule

When evaluating a character:

Start with the character record.
Resolve identity.
Resolve profile attributes.
Resolve appearance tags.
Resolve measurements.
Resolve profile JSON.
Combine all resolved sources.
Only then assess profile completeness.

A character is the resolved aggregate of all character-related data, not any single row in the database.

Current State

Character resolution is currently manual.

Future State

Consumers should query:

    v_character_resolved

as the primary entry point for character inspection.

Underlying tables remain authoritative,
but the resolver view becomes the canonical
read model for characters.

Viewing a Character Through the Resolver
Why the Resolver Exists

Character information in Chrysalis is not stored in a single place.

A character may have information spread across:

characters
character_profiles
character_profile_attributes
classvals
character_measurements
appearance_tags

As a result, inspecting only one table can produce an incorrect understanding of the character.

For example:

{}

in a profile's profile_json does not necessarily mean the profile is empty.

The character may still have substantial structured data stored elsewhere.

The Problem

Consider CHAR-MAIN-001 (Shay).

Looking only at the appearance profile:

{}

suggests:

Shay has no appearance data.

This conclusion is incorrect.

The appearance profile is linked to structured appearance attributes:

Hair Color
Eye Color
Skin Tone
Appearance Contrast
Appearance Presence

And height is stored separately in:

character_measurements

Without resolving these relationships, the profile appears empty even though meaningful information exists.

The Resolver View

To solve this problem, Chrysalis provides:

v_character_resolved

The purpose of this view is to assemble information from multiple character-related tables into a single consumable representation.

Instead of manually traversing relationships, consumers can query:

SELECT *
FROM v_character_resolved
WHERE character_id = 'CHAR-MAIN-001';

and receive a consolidated view of the character.

Example

For Shay, the resolver returns:

Public Name:
Shay Aurelia Vertue

Legal Name:
Shay Aurelia Vertue Young

Hair Color:
Brown

Eye Color:
Hazel

Skin Tone:
Fair

Appearance Contrast:
Medium Contrast

Appearance Presence:
Balanced Presence

Height:
5'4"

Even though:

appearance_json = {}

the character clearly has appearance information.

Structural vs Narrative Population

The resolver helps distinguish between two different kinds of profile content.

Structural Population

Information exists through linked systems.

Examples:

Hair Color
Eye Color
Skin Tone
Height
Classified Attributes
Appearance Tags

These facts may be stored outside the profile JSON.

Narrative Population

Information exists as authored descriptive content.

Examples:

Biography Summary
Appearance Description
Personality Description
Social Role Notes

This content is typically stored in profile_json.

Why This Matters

Without resolution:

Appearance Profile = {}

appears empty.

With resolution:

Hair Color = Brown
Eye Color = Hazel
Height = 5'4"
Appearance Tags = [ ... ]

the profile is recognized as populated.

This distinction is critical when auditing character completeness.

Appearance Tag Operational Note

`v_character_appearance_resolved` is a materialized read model.

It is not the canonical source of appearance tag assignments.

Canonical source data lives in:

character_profiles
character_profile_attributes
character_profile_attribute_tags
appearance_tags

If `v_character_appearance_resolved` is empty or stale, consumers can receive an incomplete appearance payload even though source-table data exists.

The resolver stack now treats this materialized table as a convenience surface with source-table fallback.

That means:

- rebuild the materialized table when needed
- do not treat an empty `v_character_appearance_resolved` table as proof that no appearance tags exist
- when auditing, verify both the source chain and the materialized read model

Recommended Workflow

When evaluating a character:

Query v_character_resolved.
Review the resolved character representation.
Resolve appearance tags.
Determine which information already exists.
Identify genuine gaps.
Author new profile content only where information is truly missing.

Avoid evaluating profile completeness from profile_json alone.

Rule of Thumb

A character is not defined by a single table.

A character is the resolved aggregate of:

characters
+
character_profiles
+
character_profile_attributes
+
character_profile_attribute_tags
+
appearance_tags
+
classvals
+
character_measurements
+
other resolver-backed sources

v_character_resolved is the primary entry point for viewing that aggregate.

The key is to stop thinking of profile population as a process that starts with profiles.

Instead, it should start with resolution.

Character Resolution Workflow
Goal

Before writing or editing a character profile:

Resolve existing character knowledge.
Identify authoritative sources.
Identify gaps.
Write only the information that is not already represented elsewhere.
Step 1 — Resolve Character

Start with:

SELECT *
FROM v_character_resolved
WHERE character_id = ?;

This provides:

Identity
Existing profile JSON
Structured appearance
Measurements
Population indicators

Output:

Character Resolution
Step 2 — Resolve Relationships

Query:

SELECT *
FROM v_relationship_resolver
WHERE entity_a_id = ?
OR entity_b_id = ?;

Collect:

Family relationships
Friendships
Romantic relationships
Authority structures
Team structures
Memberships
Affiliations

Output:

Relationship Resolution
Step 3 — Resolve Facts

Query relationship-linked and character-linked facts.

Goal:

What facts are already known?

Examples:

Met Kai at age 8

Attended Oxford

Attended Sandhurst

Member of RBDS

Former roommate of Jorge

Output:

Fact Resolution
Step 4 — Build Character Knowledge Packet

Aggregate:

Identity
Appearance
Relationships
Facts
Existing Profile Content

into:

Character Knowledge Packet

This becomes the canonical source for profile generation.

Step 5 — Gap Analysis

Ask:

Biography

Do we already know:

origin
education
profession
major life events

from facts?

If yes:

Don't ask again.

Social Role

Do we already know:

leadership
family role
membership
authority position

from relationships?

If yes:

Don't ask again.

Appearance

Do we already know:

height
hair
eyes
skin
appearance tags

from attributes?

If yes:

Don't ask again.

Personality

Usually requires authored content.

This is often the first place where human input is genuinely needed.

Step 6 — Generate Profile Drafts

Only after resolution.

Generate:

Biography
Appearance Narrative
Personality
Social Role

using:

Resolved Facts
+
Resolved Relationships
+
Resolved Attributes
+
Human Input
Result

The workflow becomes:

Resolve
↓

Review
↓

Identify Gaps
↓

Author

instead of:

Ask Questions
↓

Hope The Answers Aren't Already In The Database

For Sebastian, this workflow has already proven its value:

Appearance came from attributes and measurements.
Leadership role came from relationships.
Oxford and Sandhurst came from relationships.
The "met at Oxford" statement was identified as incorrect because it conflicted with a deeper story fact.

That's exactly the kind of discrepancy the workflow should surface before profile authoring begins.
