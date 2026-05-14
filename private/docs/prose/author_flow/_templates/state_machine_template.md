# {WORKFLOW_NAME} (Tier 2 State Machine Contract)

## Purpose

Defines a deterministic state machine for:

{ONE_LINE_DESCRIPTION_OF_INTENT}

This workflow operates under Tier 2 rules:

- identity-first execution
- no exploratory traversal
- strict state transitions
- no optional steps
- no explanatory output

---

# STATE MACHINE RULES (GLOBAL INVARIANT)

- One state executes at a time
- Each state MUST either:
    - request exactly one input, OR
    - execute exactly one action, OR
    - terminate workflow
- No state may combine input + execution
- No state may infer missing data
- No state may skip transitions

---

# STATE 0 — ENTRY

## INPUT

{FIRST_REQUIRED_INPUT}

## PROMPT

{FIRST_QUESTION_TO_USER}

## TRANSITION

- valid input → STATE 1
- invalid input → repeat STATE 0

---

# STATE 1 — VALIDATION

## INPUT

{ENTITY_OR_PRIMARY_IDENTIFIER}

## ACTION

{DB_QUERY_OR_LOOKUP}

## REQUIRED QUERY

```sql
{SQL_QUERY}
```
RULES
if no result → TERMINATE (not found)
if invalid layer/type → TERMINATE (invalid target)
if valid → continue
TRANSITION

→ STATE 2

STATE 2 — CONTEXT BINDING
INPUT

{SECONDARY_CONTEXT_INPUT}

(e.g. projection_id, parent scope, namespace, etc.)

QUESTION

{SECOND_QUESTION_TO_USER}

RULES
MUST NOT infer defaults
MUST NOT reuse previous chat context unless explicitly provided
TRANSITION

→ STATE 3

STATE 3 — EXECUTION PREPARATION
INPUT
validated entity
bound context
ACTION

{BUILD_PAYLOAD_OR_PREPARATION_STEP}

RULES
no user interaction allowed
deterministic transformation only
TRANSITION

→ STATE 4

STATE 4 — EXECUTION
ACTION

{API_CALL_OR_WRITE_OPERATION}

OUTPUT
success result OR failure result
RULES
no retries without explicit state transition
no hidden fallback logic
TRANSITION

→ STATE 5

STATE 5 — TERMINATION
ACTION

Emit:

NEXT CHAT START PACK

CONTENT
final state summary
next recommended action (if any)
required next document (if workflow continues)

STOP

REQUIRED STATE MACHINE INVARIANTS

This workflow MUST preserve:

deterministic transitions
identity-first resolution
zero inference policy
strict single-action states
no explanatory expansion
ANTI-PATTERNS (FORBIDDEN)
combining validation + execution in same state
asking multiple questions per state
skipping entity validation
assuming projection/context defaults
branching without state declaration