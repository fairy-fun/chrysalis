# Prose Subevent Segmentation Doctrine
**Chrysalis Story-World Database — Narrative Ontology Layer**
`private/docs/prose/prose_subevent_segmentation_doctrine.md`
Version 1.0 — May 2026

---

## 1. Purpose

This document governs how prose narrative is decomposed into calendar subevents in `sxnzlfun_chrysalis`. It defines what a subevent *is*, where its boundaries lie, and what constitutes valid versus invalid segmentation. It is the authoritative reference for any session that maps prose to schema structure.

Without this doctrine, segmentation is ad hoc. Ad hoc segmentation produces non-deterministic replay — the same prose passage will be sliced differently in different sessions, breaking the calendar's integrity as a queryable story record.

---

## 2. What a Subevent Is

A subevent (`subevent_index ≥ 1` in `calendar_events`) is **one coherent experiential unit** within a parent event's time slot.

Formally: a subevent represents a stretch of narrative time during which:
- The subject's primary attention, action, or inner state is *stable* or *continuously evolving toward a single outcome*
- The social configuration (who is present and in what relational mode) does not fundamentally shift
- The location does not change in a way that constitutes a scene break
- No new structural event (a rehearsal becoming a confrontation, a meal becoming a negotiation) supersedes the framing established at the subevent's opening

A subevent ends when any of the above conditions breaks.

**Minimum unit**: A subevent must be narratively substantial enough to produce at least one sentence of prose summary. Do not create subevents for beats that could be absorbed into adjacent continuity without loss.

**Maximum unit**: A subevent must not span two distinct experiential states that the narrative treats as separate moments. If the story marks a transition with a paragraph break, tonal shift, or new character action that reorients the scene, that transition is a segmentation boundary.

---

## 3. Valid Segmentation Boundaries

A boundary between subevents is valid when *at least one* of the following is true:

### 3.1 Structural Event Transition
The formal purpose of the activity changes. Examples:
- Warm-up ends; drilling begins
- Drilling ends; full run-through begins
- A social meal becomes a private conversation after others leave
- A debrief opens into a conflict

### 3.2 Location Transition (Scene Break)
The subject moves to a different physical space and the narrative follows. A brief exit and return within the same scene (stepping out for water, returning to the floor) does **not** constitute a boundary unless the narrative marks it as a distinct beat.

### 3.3 Social Configuration Shift
The set of active participants changes *and* the relational dynamic of the scene materially changes as a result. Examples:
- Kai leaves; Shay and Sebastian are alone for the first time
- A third party arrives and changes the register of an ongoing conversation
- The group breaks into pairs; Shay's dyadic interaction begins

A participant entering or leaving without affecting the relational frame of the scene does **not** constitute a boundary.

### 3.4 Experiential State Transition
The subject's inner state shifts categorically, not merely intensifies. Examples:
- Observation → participation
- Performance anxiety → flow state
- Social discomfort → private resolve
- Confusion → understanding (threshold moment, not gradual accumulation)

Gradual emotional build within a continuous activity is **not** a boundary. A qualitative shift that the prose marks as a turning point **is**.

### 3.5 Chronological Gap
Narrative time jumps forward and the prose does not bridge the gap. A time-skip of more than a few minutes, marked by a scene transition or summary passage, creates a boundary.

---

## 4. Invalid Fragmentation Patterns

The following patterns produce phantom subevents and must be rejected:

### 4.1 Beat-Level Fragmentation
Splitting individual moments of action or dialogue that belong to a continuous sequence.

*Bad*: Subevent 3 = "Shay notices Sebastian watching her." / Subevent 4 = "Shay continues drilling."  
*Good*: One subevent covers the entire drilling sequence including the noticing moment as an embedded beat.

### 4.2 Emotion-as-Boundary
Treating every emotional beat as a segmentation trigger. Emotional texture within a continuous activity belongs to the subevent's `description`, not to separate subevents.

### 4.3 Dialogue-as-Boundary
A conversation that opens, develops, and resolves within one structural activity is one subevent. Do not create a new subevent for each exchange unless the conversation itself undergoes a structural transition (e.g., small talk → confrontation → resolution — three phases, potentially three subevents).

### 4.4 Micro-Location Changes
Moving from one side of the studio to the other, stepping off the floor to receive a note, or going to the mirror wall are not location transitions. The scene remains continuous.

### 4.5 Participant Flicker
A character briefly entering or exiting a scene without changing its fundamental dynamic does not constitute a boundary. Only apply Rule 3.3 when the relational frame of the scene actually changes.

---

## 5. Continuity Pressure Doctrine

When in doubt, **merge rather than split**.

This doctrine exists because:
1. Subevents are deterministic schema records. Splitting creates two rows where one should exist, and that cannot be invisibly corrected later without breaking downstream references.
2. The calendar is a story record, not a screenplay breakdown. It does not need to capture every beat — it needs to capture the shape of the day.
3. Over-segmented calendars produce noisy query results. A well-segmented calendar reveals narrative structure at a glance.

**The test**: If removing the boundary between two candidate subevents would produce a single coherent summary sentence without losing structural information, merge them.

**The exception**: If the story explicitly marks the transition as significant — through a paragraph break, a tonal shift, a chapter beat — honour that mark. The author's intent is data.

---

## 6. Experiential State Transition Rules

Experiential state transitions are the subtlest segmentation trigger and the most commonly misapplied.

### 6.1 What qualifies
A valid experiential state transition must meet all three conditions:
- The shift is *categorical*, not *incremental* (anxiety → resolve, not mild anxiety → stronger anxiety)
- The narrative marks it as a threshold (a line of internal narration, a physical action that signals the change, a shift in register)
- The new state persists for the remainder of the subevent or until the next valid boundary

### 6.2 What does not qualify
- Intensification of an existing state
- A passing thought or observation that does not redirect the subject's orientation
- A reaction to an event that resolves within the same beat

### 6.3 Compound states
Some subevents contain multiple emotional registers held simultaneously (performance focus + social anxiety, for example). These are not split. The subevent description should capture the compound state.

---

## 7. Dialogue Handling Rules

### 7.1 Embedded dialogue
Dialogue that is part of a continuous activity (notes during drilling, banter between formations) is embedded in the parent subevent. It does not generate its own subevent.

### 7.2 Dialogue as primary activity
When conversation *is* the activity — a debrief, a private exchange, a formal instruction session — it is its own subevent. The subevent covers the full conversational unit.

### 7.3 Dialogue with structural phases
A single conversation may contain distinct structural phases (opening small talk → substantive content → closing challenge). These phases may warrant separate subevents if each phase is narratively substantial and the transitions between them are marked.

Apply the continuity pressure doctrine: if a two-sentence summary covers the whole conversation without distortion, it is one subevent.

### 7.4 Dialogue across a location change
If a conversation begins in one space and continues in another (moves from studio floor to corridor, for example) and the narrative follows it, the boundary is the location change, not the dialogue structure.

---

## 8. Emotional Transition Handling

Emotional transitions belong to subevent `description` unless they meet the full criteria in §6.1.

When an emotional transition *does* meet §6.1:
- The boundary falls at the moment of transition, not before or after it
- The preceding subevent ends at the last beat of the prior state
- The new subevent begins at the first beat of the new state
- The transition moment itself belongs to whichever subevent it more naturally closes (usually the prior state — the moment of shift is the conclusion of what came before)

---

## 9. Chronological Transition Handling

### 9.1 Explicit time gaps
If prose skips forward in time with a clear break (scene ending, white space, transitional narration like "an hour later"), that gap is a boundary.

### 9.2 Compressed time
Narrative compression within a continuous activity ("they ran it four more times before Kai called a break") does not create a boundary. The activity remains one subevent.

### 9.3 Summary-then-scene
If a subevent opens with a summary of time passing and then zooms into a specific moment, the subevent covers both — the summary is the opening, the scene is the body.

### 9.4 Real date alignment
Each subevent's `real_date_start_id` and `real_date_end_id` must be consistent with the parent event's date. Subevents do not cross midnight unless the parent event explicitly spans two dates.

---

## 10. Schema Boundary Notes

### 10.1 Subevent index
`subevent_index` is 1-based. The parent event row has `subevent_index = NULL`. Children are 1, 2, 3… in chronological order. Gaps in the sequence are not permitted — subevents must be assigned consecutively.

### 10.2 Event code inheritance
Subevents share the parent's `event_index`. They do not generate new `event_index` values. Their `event_code` in the `events` table follows the `CAL-WWDTES` format where S is the subevent index.

### 10.3 Summary field
Every subevent row requires a non-null `summary`. The summary must be a prose sentence, not a label. It should express *what happened* in the subevent, not just name the activity.

### 10.4 Parent reference
`parent_event_id` references `events.id`, not `calendar_events.id`. Always verify this before inserting.

---

## 11. Planner Heuristics

When planning subevent decomposition before writing SQL, apply these in order:

1. **Read the full prose passage** before assigning any boundaries. Do not segment line by line.
2. **Identify the structural skeleton**: what are the distinct activities in this time slot?
3. **Apply the valid boundary checklist** (§3) to each candidate transition between activities.
4. **Apply continuity pressure** (§5) to any boundary that is not clearly structural.
5. **Assign subevents to the surviving boundaries**, in chronological order, 1-based.
6. **Write the summary** for each subevent before writing any SQL. If a summary cannot be written in one sentence without distortion, reconsider the segmentation.
7. **Verify event codes** against the `CAL-WWDTES` format and confirm no collisions with existing rows.
8. **Check `MAX(id)`** in `calendar_events` before inserting — the id column is not auto-increment.

---

## 12. Deterministic Replay Implications

The calendar is a deterministic story record. This means:

- The same prose passage must always produce the same set of subevents, regardless of which session processes it
- Segmentation decisions made in one session are binding on all future sessions
- If a future session would segment differently, the discrepancy must be resolved explicitly — either by documenting why the original segmentation was wrong and correcting it, or by confirming that the original was correct and the new impulse was fragmentation pressure

**The test for replay fidelity**: Given only the subevent summaries in the database, could a reader reconstruct the shape of the scene — who was there, what happened structurally, what changed — without reading the prose? If yes, the segmentation is complete. If no, something structural was lost in a merge or buried in a fragment.

---

## 13. Examples

### 13.1 Good segmentation — Monday morning rehearsal

**Prose summary**: Shay arrives, changes, joins warm-up on the floor with the full team. Kai runs a structured warm-up sequence — footwork drills, frame exercises, partner rotations. Then the team breaks into formation lines and runs the opening segment three times. After the third run, Kai stops them and gives Shay individual correction on her entry timing. The others wait.

**Subevents**:
1. Warm-up sequence (structural activity: team warm-up; full social configuration; continuous)
2. Formation run-throughs (structural transition: activity changes to full-formation drilling)
3. Individual correction from Kai (structural + social configuration transition: group dynamic suspended; Shay singled out)

**Why not more?**: The three run-throughs are compressed repetition within one structural activity — they do not generate three subevents. Shay's emotional response to being singled out is embedded in subevent 3's description, not a fourth subevent.

---

### 13.2 Bad segmentation — beat-level fragmentation

**Prose moment**: Shay notices Sebastian watching her from the side during drilling.

**Bad**: Subevent = "Shay notices Sebastian watching her."  
**Why bad**: This is an embedded beat within a drilling subevent. It does not change the structural activity, social configuration, or her experiential state categorically. It belongs in the drilling subevent's description.

---

### 13.3 Borderline case — conversation with structural phases

**Prose summary**: After rehearsal, Shay and Jorge FaceTime. They open with catch-up (Jorge's work, her flat). Then Jorge asks directly how she's finding the team. Shay deflects. Jorge pushes. She admits she feels watched by people who haven't decided whether to accept her yet. Jorge says something that makes her laugh and reframes it.

**Segmentation decision**: One subevent or two?

Apply continuity pressure: the conversation has an emotional arc — deflection to admission to reframe — but it is one continuous FaceTime call with one continuous social configuration (Shay + Jorge only). The structural phases (catch-up → direct question → admission → reframe) are all within the same conversational activity.

**Decision**: One subevent. The summary captures the arc: Shay FaceTimes Jorge; deflects his questions about the team before admitting she feels on probation; Jorge reframes it. Structural detail beyond that belongs in a prose note, not a new subevent.

**Exception trigger**: If the reframe moment is a genuine experiential state transition that the narrative marks as a threshold (new paragraph, shift in Shay's internal register, change in how she holds herself for the rest of the day), it may warrant a second subevent. Apply §6.1 criteria.

---

## 14. Governing Principles Summary

| Principle | Rule |
|---|---|
| Default | Merge rather than split |
| Boundary trigger | Structural, locational, social, experiential, or chronological — and marked by the narrative |
| Fragmentation test | Can two candidate subevents be summarised in one sentence without loss? If yes, merge. |
| Emotion handling | Belongs in description unless it meets the full §6.1 categorical threshold criteria |
| Dialogue handling | Embedded in parent unless conversation is the primary activity |
| Replay fidelity | Segmentation must be deterministic — same prose always produces same subevents |
| Schema fidelity | `summary` is required, non-null, prose. `subevent_index` is consecutive, 1-based. `parent_event_id` references `events.id`. |

---

*This document governs all calendar subevent decomposition in sxnzlfun_chrysalis. Update version and date when revised. All changes must be applied retroactively to any in-progress calendar population that contradicts revised rules.*
