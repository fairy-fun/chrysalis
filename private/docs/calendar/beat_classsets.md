## Beat Classification Model

event → event_type_id → beat_classset_id → (set_id, code) → beat_type_id

- Classset is determined by event type (NOT by classifier)
- Classifier must emit codes valid within the resolved classset
- `(set_id, code)` must exist in `cvt_calendar_beat_type`
- Domains remain valid human-readable categorizations, but they are not beat-resolution authority

## Classsets

### DEFAULT
### PERSONAL
### INTIMATE

### DEFAULT codes
- instruction
- demonstration
- correction
- interaction
- evaluation
- reflection
- transition
## Classifier Prompts

### DEFAULT
You are classifying a narrative beat for a structured calendar system.

Classset: DEFAULT

Allowed codes:
- instruction
- demonstration
- correction
- interaction
- evaluation
- reflection
- transition

Rules:
- Output EXACTLY one code from the allowed list
- Do not invent new codes
- Prefer the most specific match
- If no rule clearly applies, return: transition

Return format:
{ "code": "<code>" }

### PERSONAL
You are classifying a narrative beat focused on internal state.

Classset: PERSONAL

Allowed codes:
- reflection
- confession
- realization
- doubt
- intention
- emotional_shift
- transition

Definitions:
- reflection: thinking about something (ongoing)
- realization: moment of insight or change in understanding
- confession: revealing something personal or hidden
- doubt: uncertainty, hesitation, second-guessing
- intention: decision or commitment to act
- emotional_shift: clear change in emotional state

Rules:
- Output EXACTLY one code
- Do not use DEFAULT-style codes like instruction or demonstration
- If unclear, return: transition

Return format:
{ "code": "<code>" }

### INTIMATE
You are classifying a relational or physically/emotionally close interaction.

Classset: INTIMATE

Allowed codes:
- approach
- contact
- withdrawal
- tension
- vulnerability
- reassurance
- transition

Definitions:
- approach: moving closer (physically or emotionally)
- contact: touch or direct connection
- withdrawal: pulling away or disengaging
- tension: unresolved emotional or physical strain
- vulnerability: openness, exposure, emotional risk
- reassurance: calming, comforting, stabilizing

Rules:
- Output EXACTLY one code
- Do not use instructional or analytical codes
- If unclear, return: transition

Return format:
{ "code": "<code>" }

## Guarantees

- Classifier must emit a valid code for the resolved classset
- Invalid codes will fail in `resolve_beat_type_id()`
- No cross-classset fallback
- No global code assumptions
