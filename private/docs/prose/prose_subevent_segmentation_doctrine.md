# Prose Subevent Segmentation Doctrine
**Chrysalis Story-World Database — Narrative Ontology Layer**
`private/docs/prose/prose_subevent_segmentation_doctrine.md`
Version 1.1 — May 2026

---

## 1. Purpose

This document governs how prose narrative is decomposed into runtime calendar subevents in `sxnzlfun_chrysalis`.

This is not merely a note about segmentation. It is an ontology contract for deterministic experiential chronology decomposition.

It defines:
- what a subevent *is*
- where its boundaries lie
- how chronology hierarchy behaves
- how prose becomes executable runtime chronology
- what constitutes valid versus invalid segmentation

It is the authoritative reference for any session or runtime process that maps prose into `calendar_events`.

Without this doctrine, segmentation becomes ad hoc. Ad hoc segmentation produces non-deterministic replay — the same prose passage will be segmented differently in different sessions, breaking the calendar's integrity as a queryable experiential chronology record.

---

## 2. Runtime Ontology Position

`calendar_events` is the authoritative executable chronology hierarchy.

Runtime chronology traversal, parent-child hierarchy, chronological decomposition, and replay semantics are all defined within `calendar_events`.

Subevents are not screenplay fragments.

They are deterministic experiential chronology units attached beneath concrete runtime calendar hierarchy nodes.

The runtime doctrine is:

```text
raw prose
→ segmentation proposal
→ deterministic boundary validation
→ canonical subevent generation
→ runtime persistence
```

Segmentation itself is therefore a first-class runtime operation.

---

## 3. What a Subevent Is

A subevent is one coherent experiential unit within a parent event's runtime chronology slot.

Formally: a subevent represents a stretch of narrative time during which:
- The subject's primary attention, action, or inner state is stable or continuously evolving toward a single outcome
- The social configuration (who is present and in what relational mode) does not fundamentally shift
- The location does not change in a way that constitutes a scene break
- No new structural event supersedes the framing established at the subevent's opening

A subevent ends when one of the above conditions breaks.

A subevent is not a beat-level fragment.

It is a deterministic experiential chronology segment suitable for:
- replay-safe traversal
- stable runtime chronology
- canonical hierarchy generation
- deterministic queryability
- stable client identity generation

### Minimum unit

A subevent must be narratively substantial enough to produce at least one sentence of prose summary.

Do not create subevents for beats that can be absorbed into adjacent continuity without structural loss.

### Maximum unit

A subevent must not span two distinct experiential states that the narrative treats as separate moments.

If the prose marks a transition with:
- paragraph break
- tonal shift
- location transition
- social reconfiguration
- structural activity change
- threshold experiential shift

then the transition is a candidate segmentation boundary.

---

## 4. Valid Segmentation Boundaries

A boundary between subevents is valid when at least one of the following is true.

### 4.1 Structural Event Transition

The formal purpose of the activity changes.

Examples:
- warm-up ends; drilling begins
- drilling ends; full run-through begins
- social meal becomes negotiation
- debrief becomes confrontation

### 4.2 Location Transition

The narrative follows the subject into a new physical scene-space.

Minor movement inside the same active scene does not qualify.

### 4.3 Social Configuration Shift

The active relational structure of the scene changes materially.

Examples:
- group becomes dyad
- private exchange begins
- third-party arrival changes scene register

### 4.4 Experiential State Transition

The subject's experiential orientation changes categorically, not incrementally.

Examples:
- observation → participation
- anxiety → flow state
- confusion → understanding
- social masking → private resolve

### 4.5 Chronological Gap

Narrative time advances discontinuously.

Explicit scene breaks, skipped time, or summary jumps create segmentation boundaries.

---

## 5. Invalid Fragmentation Patterns

### 5.1 Beat-Level Fragmentation

Do not create standalone subevents for isolated observations, reactions, gestures, or dialogue beats embedded within continuous activity.

### 5.2 Emotion-as-Boundary

Emotional texture belongs inside subevent description unless the full experiential transition criteria are met.

### 5.3 Dialogue-as-Boundary

Continuous conversation inside a stable activity remains one subevent unless the conversation itself structurally transforms.

### 5.4 Micro-Location Changes

Small physical repositioning inside the same scene-space does not create a new subevent.

### 5.5 Participant Flicker

Brief entrance or exit without relational restructuring does not qualify.

---

## 6. Continuity Pressure Doctrine

When uncertain, merge rather than split.

This doctrine exists because:

1. Subevents are deterministic runtime chronology records.
2. The calendar is a story record, not a screenplay breakdown.
3. Over-segmentation damages replay fidelity and query coherence.
4. Fragmentation introduces unstable chronology identity.
5. Runtime chronology should reveal the shape of experiential flow, not every beat.

### Compression heuristic

If removing the boundary between two candidate subevents would produce a single coherent summary sentence without structural loss, merge them.

### Exception

If the narrative explicitly marks the transition as significant, preserve the boundary.

Authorial structural signalling is ontology data.

---

## 7. Experiential State Transition Rules

Experiential transitions are the most subtle segmentation trigger and the most commonly over-applied.

### 7.1 Valid experiential transition

A valid experiential transition must satisfy all conditions:
- the shift is categorical, not incremental
- the prose marks the threshold
- the resulting state persists structurally

### 7.2 Invalid experiential transition

The following do not qualify:
- emotional intensification
- passing observation
- transient reaction
- unresolved flicker

### 7.3 Compound states

Multiple simultaneous emotional registers inside one continuous activity remain one subevent.

---

## 8. Dialogue Handling Rules

### 8.1 Embedded dialogue

Dialogue embedded inside continuous activity belongs to the parent subevent.

### 8.2 Dialogue as primary activity

If conversation itself is the activity, the conversation is the subevent.

### 8.3 Structural conversational phases

A conversation may contain distinct structural phases.

Separate subevents are warranted only if:
- phases are narratively substantial
- transitions are structurally marked
- continuity pressure does not collapse them cleanly

### 8.4 Dialogue across location transition

If conversation continues across a scene-space transition, the location change defines the segmentation boundary.

---

## 9. Emotional Transition Handling

Emotional transitions belong inside subevent description unless they satisfy the full experiential transition doctrine.

When a valid experiential transition occurs:
- the boundary falls at the transition threshold
- the prior state closes the previous subevent
- the new state opens the next subevent

---

## 10. Chronological Transition Handling

### 10.1 Explicit time gaps

Clear scene jumps create segmentation boundaries.

### 10.2 Compressed time

Compressed repetition inside continuous activity remains one subevent.

### 10.3 Summary-then-scene

Summary opening plus zoomed scene remains one subevent unless a later structural transition occurs.

### 10.4 Real date alignment

Subevents inherit chronology compatibility from the parent runtime chronology node.

Subevents do not cross midnight unless the parent chronology node explicitly spans multiple dates.

---

## 11. Runtime Schema Doctrine

### 11.1 Runtime chronology authority

`calendar_events` is the authoritative executable chronology hierarchy.

Hierarchy traversal, replay semantics, chronology decomposition, and runtime chronology reconstruction all occur inside `calendar_events`.

Legacy or compatibility structures must not supersede runtime chronology authority.

### 11.2 Layer semantics

Rows where:

```text
layer_id = calendar_layer_event
```

MUST contain:

```text
subevent_index IS NULL
```

Rows where:

```text
layer_id = calendar_layer_subevent
```

MUST contain:

```text
subevent_index >= 1
```

`subevent_index` has meaning only inside the subevent layer.

### 11.3 Subevent indexing

Subevents are:
- 1-based
- consecutive
- chronological
- gapless within parent chronology scope

### 11.4 Parent hierarchy

`parent_event_id` references `calendar_events.id`.

Subevents attach beneath concrete runtime chronology nodes inside the executable `calendar_events` hierarchy.

### 11.5 Summary semantics

Every subevent requires a non-null prose `summary`.

The summary describes what structurally occurred within the experiential chronology unit.

It is not merely:
- a label
- a planner note
- a beat tag
- an export fragment

#### 11.5.5 Runtime Identity Stability

Subevent chronology identity must remain stable across deterministic replay.

The canonical runtime identity surface for replay-safe subevent generation is:

```text
client_id
```

`client_id` exists to preserve deterministic chronology identity independently of:

* prose revisions
* planner regeneration
* insertion order
* database row ids
* mutable rendering changes

The canonical runtime client identity format is:

calendar_event:{parent_event_entity_id}:slot:{subevent_index}

Example:
```text
calendar_event:322:slot:1
calendar_event:322:slot:2
calendar_event:322:slot:3
```

The runtime doctrine is:
```text
same chronology structure
→ same segmentation
→ same slot ordering
→ same client identities
```
client_id is therefore derived from:

validated parent runtime chronology identity
canonical chronological slot position

It MUST NOT be derived from:

* prose text
* beat text
* summaries
* prose hashes
* planner ids
* mutable chronology metadata

`beat_hash` is diagnostic only and MUST NOT define runtime identity.

Segmentation instability causes chronology identity instability.

Therefore continuity pressure doctrine is also a runtime identity preservation mechanism, not merely a narrative heuristic.

Over-fragmentation increases:

* replay instability
* chronology churn
* identity mutation
* deterministic execution drift

Stable segmentation preserves:

* replay fidelity
* deterministic chronology reconstruction
* canonical hierarchy traversal
* runtime idempotency
* query coherence

### 11.6 Runtime persistence doctrine

Segmentation decisions become canonical runtime chronology once persisted.

The same prose passage should always produce the same segmentation output under the same chronology context.

---

## 12. Segmentation Execution Pipeline

Segmentation is a deterministic runtime transformation phase.

Canonical flow:

```text
raw prose
→ segmentation proposal
→ deterministic boundary validation
→ canonical subevent generation
→ runtime persistence
```

### 12.1 Segmentation proposal

Candidate boundaries are generated from structural, social, locational, experiential, and chronological transitions.

### 12.2 Deterministic validation

Continuity pressure doctrine is applied.

Boundary candidates that fail structural significance collapse into adjacent chronology continuity.

### 12.3 Canonical generation

Validated boundaries become canonical runtime subevent decomposition.

### 12.4 Runtime persistence

Persistence occurs only after validation.

Runtime chronology writes should never occur directly from unvalidated beat-level decomposition.

---

## 13. Planner Heuristics

1. Read the full prose passage before assigning boundaries.
2. Identify the structural chronology skeleton.
3. Apply valid boundary doctrine.
4. Apply continuity pressure.
5. Assign surviving subevents chronologically.
6. Write summaries before persistence.
7. Validate chronology integrity.
8. Verify runtime hierarchy references before insertion.

Do not segment line-by-line.

---

## 14. Deterministic Replay Doctrine

The calendar is a deterministic experiential chronology record.

Therefore:
- identical prose should produce identical segmentation
- segmentation decisions become replay-significant runtime ontology
- future reinterpretation must be resolved explicitly
- runtime chronology identity stability matters

### Replay fidelity test

Given only persisted subevent summaries, a reader should be able to reconstruct:
- structural flow
- social configuration
- chronology progression
- experiential transitions
- narrative shape

without reading the underlying prose.

---

## 15. Governing Principles Summary

| Principle | Rule |
|---|---|
| Default | Merge rather than split |
| Calendar ontology | Calendar is a story record, not screenplay decomposition |
| Runtime authority | `calendar_events` is authoritative chronology hierarchy |
| Boundary trigger | Structural, locational, social, experiential, or chronological transition |
| Fragmentation test | If one coherent summary sentence preserves structure, merge |
| Emotional handling | Embedded unless categorical experiential transition occurs |
| Dialogue handling | Embedded unless conversation itself becomes the structural activity |
| Replay fidelity | Same prose should always produce same segmentation |
| Runtime persistence | Segmentation becomes canonical runtime chronology |
| Layer semantics | Event rows use `subevent_index = NULL`; subevent rows require `subevent_index >= 1` |
| Hierarchy doctrine | `parent_event_id` references `calendar_events.id` |

---

*This document governs runtime chronology decomposition in sxnzlfun_chrysalis. All future segmentation systems, replay systems, chronology compilers, prose ingestion runtimes, and calendar persistence infrastructure must conform to this doctrine.*
