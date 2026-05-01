contract so you don’t regress.

# Limbic System — Event-Scoped Fact Model

## Overview

This system models **character limbic state as event-scoped facts**, not traits.

Each record captures:

```text
(character) + (event) → (limbic state)
```
Limbic state is:

time-bound
context-specific
non-global

A character does not “have” a limbic state — they enter one within an event.

🧱 Table Architecture (CRITICAL)
```text
entity_linked_facts_event   → event-scoped facts (WRITE HERE)
entity_linked_facts_global  → global facts (NOT USED for limbic)
entity_linked_facts         → READ-ONLY VIEW (DO NOT WRITE)
```

🚨 Non-Negotiable Rule
NEVER write to entity_linked_facts

Reason:

entity_linked_facts is a VIEW
Views do NOT reliably preserve:
AUTO_INCREMENT
DEFAULT timestamps

This causes:

“missing default value” warnings
inconsistent insert behaviour
✍️ Write Contract (LOCKED)
Table
entity_linked_facts_event
---