## Character Identity Resolution via Team Membership

### Overview

Character identity in the system is **not derived from team membership entity IDs (`tm_*`)**, but from the underlying `member_id` / `member_entity_id` associated with the membership.

Team membership entities act as **wrappers**, not canonical identities.

---

### Key Entities

* **Team Membership Entity**

  ```
  tm_tiffany_rose
  entity_type: entity_type_team_membership
  ```

* **Canonical Character Entity**

  ```
  CHAR-MAIN-012
  entity_type: entity_type_character
  ```

---

### Resolution Path

A team membership resolves to a character as follows:

```
team_memberships.entity_id (tm_tiffany_rose)
    → team_memberships.member_id / member_entity_id (CHAR-MAIN-012)
        → entities.id (CHAR-MAIN-012, entity_type_character)
```

---

### Invariant

> If a person exists on a team, they MUST resolve to a canonical `entity_type_character`.

This invariant is enforced **indirectly** via `team_memberships.member_id`.

---

### Important Distinction

| Concept           | Example         | Role                    |
| ----------------- | --------------- | ----------------------- |
| Membership Entity | tm_tiffany_rose | Contextual / relational |
| Character Entity  | CHAR-MAIN-012   | Canonical identity      |

**Do not use `tm_*` IDs as identity anchors.**

---

### Dream Journal Mapping

Dream journals MUST be anchored to the **canonical character entity**, not the membership.

Correct:

```
dream_journal:CHAR-MAIN-012
```

Incorrect:

```
dream_journal:tm_tiffany_rose   ❌
dream_journal:tiffany_rose      ❌ (non-canonical)
```

---

### Rationale

Using canonical character IDs:

* Prevents identity drift (string mismatches, aliases)
* Aligns with existing entity graph structure
* Ensures compatibility with joins and projections
* Keeps narrative systems (dreams, prose) consistent with choreography/team systems

---

### Future Constraint (Recommended)

Enforce:

```
dream_journal:* → must correspond to an existing entity_type_character
```

This can be implemented via:

* application-level guard, or
* database constraint / validation layer

---
