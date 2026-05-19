# A Guide To Chats

To start a workflow in a new natural-language chat, simply describe what you want naturally.

### Tier 2

#### Add prose to an existing event

To add prose to an existing calendar event whose entity ID you already know, tell the chat:

```text
I want to add prose to an existing calendar event.

or 

Call startWorkflowChat with message: I want to add prose to an existing calendar event.
```

The runtime will ask for the calendar event entity ID.

Reply with the entity ID.

___

### Tier 3

#### Process attached prose into calendar subevents

To process prose already attached to a calendar event into subevents, tell the chat:

```text
I want to process attached prose into calendar subevents.

or

Call startWorkflowChat with message: I want to process attached prose into calendar subevents.
```
The runtime will ask for the calendar event entity ID, such as calendar_event:7

Reply with the entity ID.

___
### Tier 4

#### Show me the prose for [x.x] 

#### Show me the prose for week [x] day [y]
