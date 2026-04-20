# Chrysalis Hydration Prompt

<!-- CHRYSALIS_PROMPT_VERSION: 1.0 -->

Purpose:
Defines the canonical repository audit procedure used by GPT and developers
to evaluate boundary compliance and doctrine alignment.

Scope:

* Framework boundary enforcement
* Protected primitive usage
* CI coverage validation

Source of truth:
Must align with:

* repo_contract.php
* CI enforcement scripts

Last updated: 2026-04-20

Load the current Chrysalis repository state from GitHub and perform a full boundary and doctrine audit.

Do not answer until you have inspected actual repository files and call paths.

If a repository contract exists, load it first and treat it as authoritative unless repository code clearly contradicts it.

When repository access is partial, state exactly what files, directories, or call paths could not be inspected, then continue with best-effort analysis grounded only in what was actually visible.

---

## 1. Identify architecture layers

Map the repository into the following layers (create concrete file-level mappings):

* raw primitives (e.g. fw_upsert_system_directive, fw_register_system_procedure)
* DB adapters (e.g. fw_db_* functions)
* safe wrappers / public entry points
* directive / service layer
* validators and assertion logic
* registry / canonical text builders
* audit / repair helpers
* CI enforcement scripts

List the actual files implementing each layer.

If a layer is absent, say so explicitly.

---

## 2. Trace protected call paths

For each of the following:

* fw_upsert_system_directive
* fw_db_upsert_system_directive
* fw_register_system_procedure
* fw_db_register_system_procedure

Do a call-path analysis:

* where it is defined
* every file that calls it (directly or indirectly)
* full call chain (entry point → … → target)

Be explicit. Do not summarise — show the paths.

If a function does not exist, say that clearly.
If a function exists but has no callers, state that clearly.
Distinguish direct calls from indirect calls.

---

## 3. Enforce boundary rules

Evaluate the repository against these rules:

### Raw primitive isolation

* raw primitives must only be called from their designated owners
* no direct calls from services, controllers, or CI scripts

### DB adapter isolation

* DB adapters must not be called arbitrarily
* only approved service-layer callers allowed

### Doctrine location

* no business or framework doctrine in:

  * database rows
  * stored procedures (beyond minimal invariants)
* doctrine must live in PHP or CI

### Wrapper integrity

* all external usage must go through safe wrappers
* no bypass paths

For each violation:

* show exact file and code reference
* explain why it violates the rule

If no violation is found for a rule, say so explicitly.

---

## 4. Contract verification (if present)

If a contract file exists (e.g. private/framework/contracts/repo_contract.php):

* load and interpret it as the source of truth
* compare declared rules vs actual usage
* identify:

  * missing enforcement
  * incorrect allowlists
  * drift between contract and code

Quote contract constants, arrays, or declarations only as needed to support the analysis.

If no contract file exists, say so explicitly.

---

## 5. CI enforcement coverage

Inspect CI scripts (e.g. check_forbidden_primitive_calls.php, check_directive_registry_drift.php):

* what rules are enforced today
* what violations would NOT be caught
* gaps between doctrine and CI enforcement

Prefer concrete examples over general statements.

---

## 6. Drift and risk analysis

Summarise:

* current boundary compliance (clean / minor drift / major violations)
* highest-risk violations (ranked)
* architectural inconsistencies
* fragile areas likely to regress

Base this only on repository evidence actually inspected.

---

## 7. Actionable fixes (ordered)

Provide a prioritised fix list:

For each item:

* what to change (file + function level)
* why it matters
* whether it is:

  * enforcement gap
  * structural violation
  * doctrine inconsistency

Order fixes by risk reduction and architectural importance.

---

## 8. Output format

Structure your response as:

1. Layer map
2. Call-path analysis
3. Violations (with code references)
4. Contract comparison
5. CI coverage gaps
6. Risk summary
7. Ordered fix plan

Be concrete, not abstract. Use real file paths and function names from the repository.

---

## Assumptions

* Doctrine lives in PHP
* DB layer is transport only
* CI is the enforcement authority
* Protected primitives must never be directly called outside allowed boundaries
* Repository state is the source of truth (not assumptions)

---

If repository access is incomplete or ambiguous, state exactly what is missing and proceed with best-effort analysis.
