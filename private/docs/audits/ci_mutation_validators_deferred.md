# Deferred CI Mutation Validators

These validators exercise canonical fact write paths through:

- apply_global_fact(...)
- apply_event_fact(...)

They are rollback-protected transactional integration tests, not read-only audits.

Current governance/runtime doctrine prefers:

- schema audits
- invariant audits
- resolver verification
- read-only structural validation

and temporarily excludes canonical write-path mutation testing from the default audit pipeline.

The following validators have therefore been removed from:

private/framework/ci/run_all_audits.php

but retained in the repository for future integration-test reintroduction.

---

## Deferred validators

### private/framework/ci/validate_fact_lineage_integrity.php

Classification:

- mixed validator
- contains mutation-path behavioural testing
- contains read-only structural lineage audits

Mutation behaviors:

- apply_global_fact(...)
- lineage fork rejection testing
- supersession advancement testing

Future cleanup recommendation:

Split structural invariant audits into a dedicated read-only audit file.

---

### private/framework/ci/validate_fact_resolver_lineage.php

Classification:

- mutation-path integration validator

Mutation behaviors:

- apply_global_fact(...)
- canonical lineage resolver advancement testing
- supersession head resolution testing

---

### private/framework/ci/validate_fact_supersession_requirements.php

Classification:

- mutation-path integration validator

Mutation behaviors:

- apply_global_fact(...)
- apply_event_fact(...)
- explicit supersession enforcement testing
- illegal slot advancement rejection testing

---

## Reason for removal from run_all_audits.php

CI previously generated persistent governance-bearing canon residue.

That persistence bug has already been corrected through transaction rollback discipline and removal of obsolete lineage-conflict validators.

However, current doctrine still prefers the default audit pipeline to remain fully read-only until a dedicated integration-test orchestration layer exists.

Potential future orchestration split:

- run_all_audits.php
- run_all_integrations.php
- run_all_contract_tests.php