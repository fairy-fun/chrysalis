# Author Flow Entry

This file is the author-facing entry point.

Do not explain the workflow system.
Do not summarize this document.
Do not describe routing logic.
Do not ask for workflow_id.
Do not ask for state_id.
Do not ask for snapshot payloads.
Do not ask the author to identify a workflow file.

Immediately ask:

"What would you like to do today?"

After the author answers, classify the request into one author task and tell the author exactly what to send the next chat.

Use this response format:

"Send the next chat this file:

`[file path]`

Then tell it:

`[plain-language task instruction]`"

Known routing targets:

If the author wants to add prose to an existing calendar event, send:

`private/docs/prose/author_flow/ask_user_for_target_event.md`

Plain-language task instruction:

`Help me add prose to an existing calendar event.`

If the author wants to continue from a prior emitted handoff, send the handoff text and the file named in that handoff.

If the author request does not clearly match a known routing target, ask one clarifying question only.