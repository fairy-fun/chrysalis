📘 SYSTEM DOCUMENT — Calendar ↔ Projection ↔ Expression Mapping
Purpose

Define how narrative time (calendar), narrative scope (projections/books), and internal state (expression pipeline) interact.

This ensures that:

Structural events remain canonical
Narrative interpretation remains scoped and consistent
Emotional inference remains grounded and character-specific
1. Core Structural Model
   1.1 Calendar Events = Objective Timeline

Table: calendar_events

Represents what happens
Fully canonical
Hierarchical via chronology index

Example:

1.6.2.1  → Event
1.6.2.1.1 → Sub-event

Rules:

Events must not be duplicated
Events do not contain interpretation
Events do not contain character state
1.2 Chronology Index (Critical)

Format:

W.D.T.E(.S)

Where:

W = Week
D = Day
T = Time Layer
E = Event
S = Sub-event (optional, recursive)

Examples:

1.6.2.1       → Event
1.6.2.1.1     → Sub-event
1.6.2.1.2     → Sub-event
Key Behaviour
Sub-events represent intra-event progression
Sub-events allow state change tracking within a single event

👉 This is essential for emotional modelling

2. Projection Layer (View Context)
   2.1 Book Projections

Table: calendar_projections

Represents:

Narrative lens (e.g. a specific book)
Selective inclusion of events
2.2 Event ↔ Projection Link

Table:
calendar_event_projection_membership

calendar_event_id → projection_entity_id

Rules:

Many-to-many
Purely structural
No interpretation
3. Expression Pipeline (Interpretation Layer)
   3.1 Constraint Runs

Table: expression_constraint_runs

Defines:

Character POV
Context (event-level)

Example:

character_id = CHAR-MAIN-001
context_entity_id = calendar_event:135
3.2 Constraint Outputs

Table: expression_constraint_outputs

Represents:

Input State → Transformed State (via constraint)

This is where meaning is constructed

4. CRITICAL RULE — POV LOCK

All expression outputs must be tied to a single character POV.

Implications:
No “group emotion”
No “room mood” unless explicitly perceived by a character
No omniscient interpretation
Correct:
Shay perceives the room as evaluative
→ valid
Incorrect:
The room is tense
→ invalid (no POV anchor)
5. Event vs Sub-Event Expression Mapping
   5.1 Event-Level (e.g. 1.6.2.1)

Use when:

State is stable across the scene
You are capturing a dominant behavioural mode
5.2 Sub-Event Level (e.g. 1.6.2.1.1, .2, .3)

Use when:

Internal state shifts
New stimulus causes recalibration
Control strategy changes

👉 This is the preferred level for emotional tracking

6. Expression Construction Rules
   6.1 Grounding Rule
   Every signal must be traceable to prose
   No invented emotion
   No inferred backstory unless explicitly activated
   6.2 Classval Mapping Rule
   Use existing classvals if accurate
   If not:
   Create new classval
   Do not distort meaning to fit existing ones

Pattern:

cval_<domain>_<specific_meaning>
6.3 One Output Rule

Per sub-event:

Only one high-signal constraint output

Avoid:

Bundling multiple transformations
Redundant outputs
6.4 Transformation Rule

Each output must encode:

Input → Output (with mechanism)

Examples:

activation → regulation
evaluation awareness → calibration
authority frame → compliance alignment
compliance → synchronised execution
7. Recommended Workflow
   Step 1 — Select Event/Sub-Event

Example:

1.6.2.1.3
Step 2 — Extract Signals

From prose:

affect (if present)
cognition
control strategy
access level
governing rule
Step 3 — Map to Classvals
Reuse where valid
Define where necessary
Step 4 — Insert Constraint Output

Single transformation only.

Step 5 — Run Suggestion Pipeline
suggestEntityEventThemeLink

Validate:

Themes align with behaviour
Themes escalate across sequence
No generic outputs
8. System Insight (Non-Negotiable)

You are not tagging emotions.

You are modelling:

Stimulus → Internal Processing → Behavioural Strategy

Across time.

9. Failure Conditions (Watch For These)

If you see:

Vague themes (“growth”, “challenge”)
Repeated outputs across beats
Emotion without evidence
Group-level descriptions

Then the pipeline is misaligned upstream

10. Design Principle

Events are static.
Meaning is dynamic.
POV is the bridge.