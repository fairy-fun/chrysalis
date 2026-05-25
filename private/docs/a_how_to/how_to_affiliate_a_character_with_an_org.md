# How to Affiliate a Character with an Institution

This guide documents the canonical workflow for affiliating a character/entity with an institution using the live Chrysalis organizational ontology.

---

# Canonical doctrine

Character identity authority:

```text
entities.id
```
Institution/company authority:

companies.company_id

Affiliation authority:

company_assignments

Organizational titles/roles:

company_assignment_roles
    -> company_roles

Important distinction:

affiliation

is NOT:

character identity

and:

organizational role/title

is NOT:

honorific

Examples:

Mr
Mrs
Sir

belong to honorific ontology.

Examples:

Director
Chairman
Personal Trainer
Coach

belong to organizational role ontology.

Step 1 — Verify the institution/company exists

Example query:

SELECT
    company_id,
    company_name,
    shorthand,
    entity_id
FROM companies
WHERE company_name LIKE '%RBDS%'
   OR shorthand LIKE '%RBDS%';

Example result:

COMP-001
Royal Ballroom Dance Society
RBDS
Step 2 — Create the affiliation

Canonical affiliation authority:

company_assignments

Verified live columns include:

member_entity_id
company_id
authority_id
status_id

Example:

INSERT INTO company_assignments (
    company_assignment_id,
    member_entity_id,
    company_id,
    authority_id,
    status_id,
    notes
)
VALUES (
    'COMP-ASSIGN-CHAR-SUP-996-RBDS',
    'CHAR-SUP-996',
    'COMP-001',
    'authority_perform',
    'status_active',
    'Mr Tautua Fruean affiliated with the Royal Ballroom Dance Society.'
);
Live authority ontology

Verified live authority values:

authority_analyze
authority_assign_roles
authority_choreograph
authority_manage_team
authority_perform

Choose the authority that best represents the affiliation semantics.

Live status ontology

Observed live status values:

status_active
status_alumni
status_inactive
Step 3 — Add the organizational role/title

Organizational titles belong in:

company_roles

NOT:

characters

NOT:

entity_labels

NOT:

honorific ontology
3a. Create the canonical role if needed

Example:

INSERT INTO company_roles (
    role_id,
    role_code,
    role_label,
    description
)
VALUES (
    'company_role_personal_trainer',
    'PERSONAL_TRAINER',
    'Personal Trainer',
    'Provides physical conditioning and training support.'
);
3b. Attach the role to the affiliation

Canonical bridge authority:

company_assignment_roles

Example:

INSERT INTO company_assignment_roles (
    company_assignment_id,
    role_id,
    is_primary,
    sort_order,
    notes
)
VALUES (
    'COMP-ASSIGN-CHAR-SUP-996-RBDS',
    'company_role_personal_trainer',
    1,
    1,
    'Primary organizational role for Mr Tautua Fruean within RBDS.'
);
Resulting ontology structure
CHAR-SUP-996
    -> affiliated with
COMP-001 (RBDS)

    -> role/title
Personal Trainer
Important doctrine boundaries

Affiliations are:

organizational relationships

NOT:

canonical character identity

Organizational roles/titles are:

institution-scoped semantic roles

NOT:

honorifics

Do NOT overload:

char_name_first
char_name_full
entity_labels

with organizational titles.

Verification queries
Verify affiliation
```sql
SELECT *
FROM company_assignments
WHERE member_entity_id = 'CHAR-SUP-996';
```
#### Verify role attachment

```sql
SELECT
    car.company_assignment_id,
    cr.role_label,
    car.is_primary
FROM company_assignment_roles car
JOIN company_roles cr
    ON cr.role_id = car.role_id
WHERE car.company_assignment_id =
    'COMP-ASSIGN-CHAR-SUP-996-RBDS';
```
Expected workflow behavior

After these inserts:

Mr Tautua Fruean

should canonically resolve as:

affiliated with RBDS
role: Personal Trainer

without mutating character identity ontology.