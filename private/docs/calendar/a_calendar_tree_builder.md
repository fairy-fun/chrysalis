# Calendar Tree Builder — Usage Contract
## Purpose

`build_calendar_tree()` converts a flat set of rows into a hierarchical, chronologically ordered tree.

It is data-source agnostic and is intended to be used by:

* projection resolvers
* entity tree endpoints (future refactor)
* any feature requiring deterministic calendar hierarchy construction
### Input Requirements

Each row must be normalized into the following structure before calling:
```
[
'id' => (int|string),                 // unique identifier for the node
'parent_id' => (int|string|null),    // null for root nodes
'chronology_address' => (string),    // dot-separated positive integers

    // any additional fields are preserved
]
```
Example
```
[
[
'id' => 10,
'parent_id' => null,
'chronology_address' => '1',
'label' => 'Root event',
],
[
'id' => 11,
'parent_id' => 10,
'chronology_address' => '1.1',
'label' => 'Child event',
],
]
```
### Usage
```
$tree = build_calendar_tree($rows);
```
### Behavior
Hierarchy Resolution
1.    Parent-child relationships are built using parent_id
2.    Missing parents are not silently ignored

Orphaned nodes are surfaced:
```
[
'orphaned_in_tree' => true,
'missing_parent_id' => <id>
]
```
### 2. Chronology Enforcement

All chronology_address values must match:
```
^[1-9][0-9]*(?:\.[1-9][0-9]*)*$
```
Invalid values will throw:

InvalidArgumentException

This matches the contract defined in:

`private/framework/calendar/calendar_chronology_resolver.php`

### 3. Sorting
   Siblings are sorted using chronology_address
   Comparison is numeric and segment-based, not string-based

Example:

1.2 < 1.10   ✅ correct
### 4. Output Shape

Each node will include:
```
[
'id' => ...,
'parent_id' => ...,
'chronology_address' => ...,
'children' => [...],

    // plus any original fields
]
```
#### Integration Pattern (Projection Resolver)

When using this in a projection resolver:

##### Step 1 — Normalize rows
```
$rows[] = [
'id' => $eventId,
'parent_id' => $parentEventId,
'chronology_address' => $address,
'label' => $label,
];
```
##### Step 2 — Build tree
```
$tree = build_calendar_tree($rows);
```
##### Important Design Rules
* Chronology is order, not structure
* Structure is defined only by parent_id
* No synthetic parents are created
* Invalid chronology is rejected early
* Tree building is deterministic
### Non-Goals

This function does not:

* fetch data from the database
* validate projection completeness
* enforce business rules beyond structure + ordering
* resolve chronology paths
## Related Components
`calendar_chronology_resolver.php` → validates and resolves chronology paths
`resolve_calendar_projection.php` → prepares projection data for tree building
`get_calendar_tree_for_* endpoints` → expose trees via API