# Add Prose Via Postman To Existing Event

## Purpose

Defines the bounded workflow for attaching prose
to an already-existing calendar_layer_event
through the API/Postman flow.

---

## Entry Conditions

The operator intends to:

- attach prose to an existing event
- use Postman/API flow
- avoid manual hierarchy traversal

Required runtime state:

- projection resolved
- event resolved
- concrete calendar_layer_event identity resolved

---

## Runtime Resolution Rules

The runtime MUST first attempt:

- latest event resolution
- publication resolution
- prose existence resolution

The runtime MUST NOT ask:

- what week
- what day
- what event

if canonical event resolution already succeeded.

---

## Required Documents

- create_prose_draft.md
- create_prose_draft_json_contract.md

---

## Canonical Workflow

resolve target event
→ inspect publication state
→ prepare prose draft payload
→ submit API request
→ verify publication state
→ bounded stop
→ NEXT CHAT START PACK

---

## Required Runtime Outputs

...

---

## Interview Rules

...

---

## Postman Payload Rules

...

---

## Verification Rules

...

---

## Stopping Rules

...

---

## NEXT CHAT START PACK Template

...