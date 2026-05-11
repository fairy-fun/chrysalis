# Fact Lineage Contract

## Core Doctrine

A supersession is a single atomic lineage transition, not two independent writes.

Fact lineage exists to preserve:
- immutable historical truth
- deterministic canonical resolution
- auditability of state evolution
- strict linear supersession semantics

Facts are never mutated in place once materially asserted.

Instead:
- prior facts become historical
- new facts supersede prior facts
- lineage continuity is preserved explicitly

---

# Canonical Slot Doctrine

A canonical slot represents the active semantic position for a fact.

The slot identity remains stable across supersession.

Only the current head of a lineage chain may occupy the canonical slot.

---

## Global Fact Canonical Slot

Defined by:

```text
(subject_entity_id, fact_type_id)
```
Examples:
```text
Alice -> lives_in -> Paris
Alice -> lives_in -> Tokyo
```
Both facts occupy the same canonical slot:
```text
(Alice, lives_in)
```
The object value may evolve over time.

Event Fact Canonical Slot

Defined by:
```text
(subject_entity_id, context_entity_id, fact_type_id)
```
Examples:
```text
Alice @ Event_X -> role -> attendee
Alice @ Event_X -> role -> speaker
```
Both facts occupy the same canonical slot:
```text
(Alice, Event_X, role)
```
### Immutability Doctrine

Historical facts are immutable.

Once written:

* semantic content must not change
* lineage identity must not change
* timestamps remain historical truth

Allowed operations:

* insert new fact
* supersede prior fact
* resolve lineage

Forbidden operations:

* overwrite canonical historical content
* mutate historical object values
* rewrite lineage ancestry
### Current Head Semantics

Each canonical slot must have exactly one current fact.

This is represented by:
```text
is_current TINYINT(1)
```
Rules:
```text
is_current = 1
```
means:

active canonical head
currently resolved fact
```text
is_current = 0
```
means:

historical superseded fact

At all times:
```text
exactly one current fact exists per canonical slot
```
Supersession Semantics

Supersession creates a new immutable fact while preserving prior history.

Given:
```text
A -> current
```
Superseding creates:
```text
A -> historical
B -> current
```
with:
```text
B.supersedes_linked_fact_id = A.linked_fact_id
```
Lineage therefore forms:
```text
A <- B <- C <- D
```
where:

arrows represent supersession ancestry
lineage traversal moves backward through superseded history
Linear Lineage Doctrine

Fact lineage is strictly linear.

Allowed:
```text
A <- B <- C
```
Forbidden:
```text
      B
     /
A <-<
     \
      C
```
Meaning:

a fact may have at most one successor
lineage forks are forbidden

This preserves deterministic canonical history.

Required Database Invariants

The database layer must enforce:

One Current Head Per Canonical Slot

Global:
```sql
UNIQUE (
    subject_entity_id,
    fact_type_id,
    is_current
)
```
Event:
```sql
UNIQUE (
    subject_entity_id,
    context_entity_id,
    fact_type_id,
    is_current
)
```
One Successor Maximum
```sql
UNIQUE (supersedes_linked_fact_id)
```
This prevents lineage forks.

### Referential Lineage Integrity
```sql
FOREIGN KEY (supersedes_linked_fact_id)
REFERENCES entity_linked_facts_*(linked_fact_id)
```
This prevents orphan lineage references.

### Self-Supersession Prohibition

Forbidden:
```text
linked_fact_id = supersedes_linked_fact_id
```
Self-cycles are invalid lineage states.

### Atomic Transition Requirement

A supersession is a single atomic state transition.

It is NOT:

* one update
* followed by one unrelated insert

Instead it is:

>one indivisible lineage mutation

A valid supersession operation must:

1. lock the current canonical head
2. demote the old current row
3. insert the new current row
4. commit atomically
### Transaction Requirement

Supersession writes MUST execute inside a database transaction.

Required structure:
```sql
BEGIN;

SELECT ...
FOR UPDATE;

UPDATE old_current
SET is_current = 0
WHERE linked_fact_id = ?;

INSERT INTO ...
(
    ...,
    supersedes_linked_fact_id,
    is_current
)
VALUES
(
    ...,
    ?,
    1
);

COMMIT;
```
### Locking Requirement

Canonical head selection MUST use:
```text
SELECT ... FOR UPDATE
```
This serializes concurrent supersession attempts.

Without locking:

* concurrent writers may create multiple current heads
* lineage forks may emerge transiently
* canonical resolution becomes nondeterministic
### Failure Semantics

If any step of a supersession fails:

* the transaction must roll back entirely
* prior current head remains authoritative
* partial lineage mutation is forbidden

Allowed outcomes:
```text
old current preserved
```
or:
```text
new current committed
```
Forbidden outcome:
```text
no current head exists
```
or:
```text
multiple current heads exist
```
### Concurrency Doctrine

Concurrent supersession attempts are expected operational behavior.

The system must guarantee:

* deterministic canonical resolution
* serialized lineage mutation
* transactional integrity
* stable current-head uniqueness

Under contention:

* writes may block temporarily
* retries are acceptable
* uniqueness violations indicate race contention

### Resolver Doctrine

Canonical resolution MUST resolve only the current head.

Historical facts remain queryable for:

* audit
* provenance
* temporal reconstruction
* lineage traversal

Resolvers must not mutate lineage state.

### Historical Query Doctrine

Lineage history is append-only.

The system must preserve:

* complete supersession ancestry
* historical object values
* temporal ordering
* immutable provenance

Historical lineage traversal must remain possible indefinitely.

### Forbidden States

The following states are invalid:

#### Multiple Current Heads
```text
two rows with is_current = 1
within the same canonical slot
```
Lineage Forks
```text
multiple rows superseding the same ancestor
```
#### Orphan Supersession References
```text
supersedes_linked_fact_id
referencing nonexistent lineage nodes
```
#### Cyclic Lineage

Forbidden:
```text
A <- B <- C <- A
```
Lineage must remain acyclic.

### CI Enforcement Expectations

CI must validate:

* canonical uniqueness enforcement
* one-successor enforcement
* orphan rejection
* self-supersession rejection
* cycle rejection
* transactional rollback behavior
* concurrent supersession safety
* preservation of historical lineage continuity
### Architectural Summary

The lineage model guarantees:

* immutable historical facts
* deterministic canonical resolution
* transactional supersession
* linear lineage continuity
* database-enforced integrity
* audit-safe historical preservation

The canonical slot represents semantic continuity.

The lineage chain represents historical evolution.

The current head represents present canonical truth.