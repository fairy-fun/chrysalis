# A Guide To Chats

To start a workflow in a new natural-language chat, simply describe what you want naturally.

### Tier 0

#### I am creating chronology containers.

#### I want to create a Book week

### Tier 1
#### Show me all prose for Week [x]

```text
startWorkflowChat Show me all prose for week 1
```

#### Show me published prose for Week [x]

```text
startWorkflowChat Show me published prose for Week 1
```

#### Show me all prose for Week [x], Day [y]

```text
startWorkflowChat show me all prose for Week 1, Day 2
```

#### Show me published prose for Week [x], Day [y]

```text
startWorkflowChat Show me published prose for Week 1, Day 2
```
#### Show me all prose for Week [x].[y]

```text
startWorkflowChat Show me all prose for 1.2
```

#### Show me published prose for [x].[y]

```text
startWorkflowChat Show me published prose for 1.2
```

#### Create an event in the correct projection

##### Create a book event
Write: 
```text
startWorkflowChat create book event
```

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

#### add a new prose draft to a family

#### publish a selected draft


#### 3A: derive beat/title metadata from attached prose

```text
startWorkflowChat I want to process attached prose into its beat and title
```

or

```text
Call startWorkflowChat with message: derive beat/title metadata from attached prose
```
The runtime will ask for the calendar event entity ID, such as calendar_event:7

Reply with the entity ID.

#### 3b: I want to tag Characters from an event's attached prose

```text
startWorkflowChat I want to tag Characters from an event's attached prose
```
or
```text
startWorkflowChat suggest Characters from attached prose
```
The runtime will ask for the calendar event entity ID, such as calendar_event:7 or 7

Reply with the entity ID.

#### 3c: I want to approve Characters from an event's attached prose
```text
startWorkflowChat I want to approve character tags from attached prose
```

##### Examples now supported:
```text
yes
```
→ approve all resolved suggestions

all except CHAR-MAIN-1004

→ approve all except that entity

not CHAR-MAIN-1004 or CHAR-MAIN-015

→ approve everything except those two

CHAR-MAIN-001 CHAR-SUP-998 CHAR-SUP-997

→ approve only those entities

#### Process attached prose into calendar subevents

To process prose already attached to a calendar event into subevents, tell the chat:

```text
I want to process attached prose into calendar subevents.

or

Call startWorkflowChat with message: segment prose into calendar subevents
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

