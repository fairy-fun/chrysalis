# START:
Author intent = add / continue / inspect / revise prose

1. Resolve current projection
   If missing → interview: “Which book/projection?”
   If found → continue

2. Resolve latest executable event
   If none → interview: “Are we creating the next event or choosing an existing one?”
   If found → continue

3. Resolve prose publication state
   If prose_found → ask: inspect / continue / revise / segment?
   If no prose → ask: create draft / import prose / choose another event?
   If ambiguous → show candidates and ask which is canonical
   If publication missing → ask whether to create binding

4. Resolve author mode
   Options:
    - draft new prose
    - paste prose for segmentation
    - revise existing prose
    - export existing prose
    - create next calendar event first

5. Jump to correct contract section
    - event creation protocol
    - prose draft JSON contract
    - segmentation doctrine
    - latest prose inspection
    - revision/export path

The key is to write it as an interview router:

At each failure or ambiguity point, ask exactly one author-facing question.
Do not ask upstream questions once downstream state is already resolved.
Do not ask week/day/time/event unless runtime resolution failed.

I’d add a section like this to the contract:

## Author Interview Routing

The prose runtime MUST treat author answers as routing signals, not as freeform planning instructions.

Author answers map to the following branches:

- “write new prose” → latest event prose resolution → create prose draft
- “continue” → latest event prose resolution → inspect existing prose → append/revise
- “I have prose” / pasted prose → latest event resolution → segmentation doctrine
- “make the next event” → calendar event creation protocol
- “show me where we are” → latest event prose resolution only
- “revise this” → existing prose draft resolution → revision flow
- “export” → published prose/export flow

Then each branch gets a small “required docs” list so the chat only loads the heavy documents when needed. That will make the workflow much less mushy.