# A Guide To Chats

To start a workflow in a new natural-language chat, simply describe what you want naturally.

### Tier 1
#### Create an event in the correct projection

##### Create a book event

##### Create an event that only attaches to the real-time calendar


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

#### check to see if prose is attached to a calendar event or subevent
Tier 3: if I ask you if blank has prose, don't just look at `prose_body`
___

### Tier 4

#### Retrieve prose by chronology address

To retrieve prose already attached to a calendar event or subevent by chronology position, tell the chat:

```text
Show me the prose for 1.2.1.3

or

Show me the prose for 1.2.1.3.1
```

#### Retrieve prose by week/day position

You may also request prose by temporal position:

Show me the prose for week 1 day 2

#### Retrieve real-time calendar events
Show me projection_id = 5; for a given time-span