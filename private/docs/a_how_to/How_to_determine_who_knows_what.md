# How to Determine Who Knows What

## Purpose

This guide describes the knowledge-tracking model currently represented in the live database.

It explains:

- what a character knows
- how they learned it
- whether that knowledge is private or shared
- how knowledge differs from facts

A fact existing in the database does not imply that every character knows that fact.

Knowledge is modeled separately.

---

# Core Doctrine

Knowledge answers questions such as:

```text
Who knows this?
How did they learn it?
When did they learn it?
Do they know the private truth?
Is the knowledge direct, inferred, or overheard?
```

Knowledge should be treated as a first-class modeled object.

---

# Important Distinction

## Facts

Represent:

```text
Reality
    ↓
Fact
```

Examples:

```text
A relationship exists.
A role assignment exists.
An event occurred.
```

A fact may exist even if nobody knows it.

---

## Knowledge

Represents:

```text
Character
    ↓
Knowledge Record
    ↓
Awareness Of A Fact
```

Examples:

```text
Kai knows.
Shay does not know.

Lenore knows.
The team does not know.
```

Knowledge is not assumed.

Knowledge must be recorded.

---

# Database Model

Verified live tables relevant to knowledge include:

```text
calendar_event_knowledge
knowledge_type_classvals
relationship_knowledge_map
```

Conceptual structure:

```text
Reality
    ↓
Fact
    ↓
Knowledge Record
    ↓
Character Awareness
```

---

# Knowledge Records

Primary table:

```text
calendar_event_knowledge
```

Important fields:

```text
learner_id
knowledge_type_id
relationship_id
target_record_type
target_record_id
calendar_event_id
notes
```

Interpretation:

```text
learner_id
    ↓
Who knows something

knowledge_type_id
    ↓
How they learned it

relationship_id
or
target_record_type + target_record_id
    ↓
What they know

calendar_event_id
    ↓
When they learned it
```

---

# Knowledge Types

Vocabulary comes from:

```text
knowledge_type_classvals
```

Verified current values:

```text
KNOWN_INITIAL
    Initially Known

TOLD
    Told Directly

OVERHEARD
    Overheard

INFERRED
    Inferred
```

These answer:

```text
How did the learner acquire the knowledge?
```

---

# Relationship Knowledge

Some knowledge records point to:

```text
relationship_id
```

Example doctrine:

```text
A relationship may exist.

One participant may know.

The other participant may not know.
```

Therefore:

```text
Relationship Exists
    ≠
Mutual Awareness
```

Never assume both parties understand the same truth.

---

# Record-Based Knowledge

Knowledge records may also point to:

```text
target_record_type
target_record_id
```

Example:

```text
team_admin_assignments
```

This allows the system to track awareness of:

```text
Assignments
Memberships
Administrative Decisions
Facts
Records
```

not only interpersonal relationships.

---

# Determining What A Character Knows

To review all knowledge associated with a learner:

```sql
SELECT *
FROM calendar_event_knowledge
WHERE learner_id = 'CHARACTER_ID';
```

Review:

```text
knowledge_type_id
relationship_id
target_record_type
target_record_id
notes
```

---

# Determining Who Knows A Relationship

```sql
SELECT *
FROM calendar_event_knowledge
WHERE relationship_id = 'REL-XXX';
```

This reveals which learners have explicit knowledge records associated with that relationship.

---

# Determining Who Knows A Specific Record

```sql
SELECT *
FROM calendar_event_knowledge
WHERE target_record_type = 'some_table'
  AND target_record_id = 'some_record';
```

This reveals all recorded awareness of that object.

---

# Private Truth

The system also contains:

```text
relationship_knowledge_map
```

Important fields:

```text
observer_entity_id
knows_private_truth
```

This layer answers:

```text
Does this observer know the underlying truth?
```

This is distinct from merely knowing that a relationship exists.

---

# Writing Guidance

When evaluating knowledge:

## Do Not Assume Universal Awareness

Avoid assumptions such as:

```text
Everyone knows.
Both characters know.
The audience knows.
```

Instead verify knowledge records.

---

## Check Acquisition Method

Determine whether the learner:

```text
Knew initially
Was told
Overheard
Inferred
```

The acquisition path may affect reliability and interpretation.

---

## Separate Facts From Awareness

Good reasoning:

```text
The relationship exists.
Kai knows.
Shay does not know.
```

Poor reasoning:

```text
The relationship exists.
Therefore everybody knows.
```

The existence of a fact does not imply awareness.

---

# Live Examples

The live database currently contains explicit knowledge records that demonstrate asymmetric awareness.

## Example: One-Sided Relationship Knowledge

Knowledge record:

```text
learner_id = CHAR-MAIN-003
knowledge_type_id = kt_known_initial
relationship_id = REL-002
```

Notes:

```text
Kai is aware of his attraction to Shay at their first meeting.
Shay is not aware of it.
```

Interpretation:

```text
Relationship exists.

Kai knows.

Shay does not know.
```

This demonstrates that relationship existence and relationship awareness are separate concerns.

---

## Example: Hidden Administrative Knowledge

Knowledge record:

```text
learner_id = CHAR-SUP-998
knowledge_type_id = kt_known_initial
target_record_type = team_admin_assignments
target_record_id = TAA-002
```

Notes:

```text
Lenore knows she has assigned Shay the narrative consultant role.
```

A separate knowledge record exists for Shay:

```text
learner_id = CHAR-MAIN-001
knowledge_type_id = kt_known_initial
target_record_type = team_admin_assignments
target_record_id = TAA-002
```

Notes:

```text
Shay knows she has been given the narrative consultant responsibility.
The team does not know.
```

Interpretation:

```text
Assignment exists.

Lenore knows.

Shay knows.

Other characters may not know.
```

Knowledge must therefore be evaluated per learner rather than inferred from the existence of the underlying record.

---

## Doctrine Derived From These Examples

Never assume:

```text
A relationship is mutually understood.

An assignment is publicly known.

A participant knows what another participant knows.
```

Instead:

```text
Find the knowledge record.

Identify the learner.

Determine how the knowledge was acquired.

Determine whether other learners possess equivalent records.
```

Knowledge is explicit.

Awareness must be demonstrated through knowledge records rather than inferred from the underlying fact.

---

# Scope Boundary

This guide documents:

- character knowledge
- knowledge acquisition
- relationship awareness
- private-truth awareness
- knowledge-related database structures

This guide does not document:

- fact creation workflows
- relationship creation workflows
- event authoring
- prose workflows
- API endpoints

Those concerns belong to separate systems.
