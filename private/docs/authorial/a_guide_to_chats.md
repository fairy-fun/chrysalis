# A Guide To Chats

To start a workflow in a new natural-language chat, simply describe what you want naturally.

### Tier 0

#### I am creating chronology containers.

#### I want to create a Book week

#### I want to create a character

### Tier 1
#### Show me all prose for Week [x]

```text
startWorkflowChat Show me all prose for week 1
```

#### 1.1 Show me published prose for Week [x]

```text
startWorkflowChat Show me published prose for Week 1
```

#### 1.2 Show me all prose for Week [x], Day [y]

```text
startWorkflowChat show me all prose for Week 1, Day 2
```

#### 1.3 Show me published prose for Week [x], Day [y]

```text
startWorkflowChat Show me published prose for Week 1, Day 2
```
#### 1.4 Show me all prose for Week [x].[y]

```text
startWorkflowChat Show me all prose for 1.2
```

#### 1.5 Show me published prose for `[x].[y]`

```text
Call startWorkflowChat with message: Show me published prose for 1.2
```

#### 1.6 Show me published prose for `[year][month][day]-[x]`

```text
startWorkflowChat Show me all prose for 20250122-e
```
Expected output:

_1.2.1.2 — Week 1, Day 2, Morning, 20250120-a (calendar_event:2)_

#### 1.7 Create an event in the correct projection

##### 1.7.1 Create a book event
Write: 
```text
startWorkflowChat create book event
```

##### 1.7.2 Create an event that only attaches to the real-time calendar


### Tier 2

#### 2.1 Add prose to an existing event

To add prose to an existing calendar event whose entity ID you already know, tell the chat:

```text
startWorkflowChat I want to add prose to an existing calendar event.
```
or 

```text
Call startWorkflowChat with message: I want to add prose to an existing calendar event.
```

The runtime will ask for the calendar event entity ID.

Reply with the entity ID.

#### Add a label to existing prose

##### Examples now routed correctly:
```text
“add reference label”
“attach reference label”
“assign reference label”
startWorkflowChat I want to add a reference label to an existing prose attachment
```

___

### Tier 3

#### 3.1 add a new prose draft to a family

```text
startWorkflowChat I want to add a new prose draft to a prose family
```

#### 3.2 publish a selected draft
```text
startWorkflowChat set published prose draft

“set canonical export draft”
“elevate prose draft”
“change canonical export target”
```

#### 3A: derive beat/title metadata from attached prose

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

#### 3d: I want to tag Characters from an event's attached prose

```text
Call startWorkflowChat with message: I want to tag locations from an event's attached prose
```

#### 3e: I want to approve Locations from an event's attached prose
```text
Call startWorkflowChat with message: I want to approve locations from attached prose
```

---

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
```
or
```text

Show me the prose for 1.2.1.3.1
```

#### Retrieve prose by label

To retrieve prose already attached to a calendar event by label, tell the chat:

```text
startWorkflowChat Show me the prose for 20250120-a
```


#### Retrieve prose by week/day position

You may also request prose by temporal position:

Show me the prose for week 1 day 2

#### Retrieve real-time calendar events
Show me projection_id = 5; for a given time-span

