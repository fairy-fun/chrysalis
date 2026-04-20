# Framework contracts

## Procedure registration must use safe wrapper

- Element: Procedure registration
- Type: Safe wrapper contract
- Old home: framework row + DB stored procedure enforcement
- New home:
  - `private/framework/procedures/procedure_registration_service.php`
  - `private/framework/db/framework_db_calls.php`
  - `private/scripts/ci/check_forbidden_primitive_calls.php`
  - `private/scripts/ci/check_procedure_registration_paths.php`
- Invariant:
  - `fw_safe_register_system_procedure()` is the only public registration path
  - `fw_register_system_procedure()` is low-level/internal only
- Enforcement:
  - PHP validates target existence and `fw_` prefix
  - CI forbids direct primitive bypasses
- CI proof:
  - forbidden direct-call scan
  - registration path check
- Repair path:
  - replace direct primitive call with `fw_safe_register_system_procedure()`

## Directive writes must use safe wrapper

- Element: Directive upsert
- Type: Safe wrapper contract
- Old home: framework row rollout policy
- New home:
  - `private/framework/directives/directive_service.php`
  - `private/framework/db/framework_db_calls.php`
  - `private/scripts/ci/check_forbidden_primitive_calls.php`
- Invariant:
  - `fw_upsert_system_directive()` is protected low-level write logic
  - public code must call `fw_safe_upsert_system_directive()`
- Enforcement:
  - PHP generates canonical text and validates binding
  - CI forbids direct primitive bypasses
- CI proof:
  - forbidden direct-call scan
- Repair path:
  - replace direct primitive call with `fw_safe_upsert_system_directive()`

## Procedure-execution directives are generated, not authored freehand

- Element: Procedure-execution directive text
- Type: Canonical builder contract
- Old home: `validate_system_directive(...)`
- New home:
  - `private/framework/directives/directive_text.php`
  - `private/framework/directives/directive_validator.php`
  - `private/scripts/ci/check_directive_registry_drift.php`
- Invariant:
  - target procedure starts with `fw_run_`
  - directive text equals `CALL <schema>.<procedure>();`
  - referenced procedure exists
  - referenced procedure is active in `system_procedure_registry`
- Enforcement:
  - PHP validator
  - CI drift audit
- CI proof:
  - directive drift check
- Repair path:
  - regenerate canonical text and rewrite through safe wrapper