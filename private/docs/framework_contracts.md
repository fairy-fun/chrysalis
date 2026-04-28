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


## Expression outputs must be POV-scoped and classval-backed

- Element: Expression constraint outputs
- Type: Interpretation contract
- New home:
  - `private/docs/expression/calendar_projection_expression_model.md`
  - `private/scripts/ci/check_expression_output_validity.php`
- Invariant:
  - `calendar_events` remain structural timeline truth
  - `calendar_event_projection_membership` owns event-to-book projection membership
  - `expression_constraint_outputs` describe one character POV only
  - emotion shifts may be tracked at sub-event chronology level
  - no group-level emotion output is valid without a character POV
  - all expression output values must be classval-backed
- Enforcement:
  - CI rejects expression outputs without a valid constraint run
  - CI rejects outputs whose run lacks `character_id`
  - CI rejects missing or invalid classval references
  - CI rejects missing calendar event context entities
- CI proof:
  - expression output validity check
- Repair path:
  - create or repair the relevant `expression_constraint_runs` row
  - attach output to a single character POV
  - add missing classvals rather than distorting meaning
  - keep structural event/book links in `calendar_event_projection_membership`

## Expression outputs must encode exactly one transformation per POV run

- Element: Expression constraint outputs
- Type: Semantic constraint
- New home:
  - `private/scripts/ci/check_expression_semantics.php`
- Invariant:
  - each constraint run produces exactly one output
  - input and output classvals must differ
  - each run must map to a single valid character POV
  - access state must always be defined
- Enforcement:
  - CI rejects multi-output runs
  - CI rejects non-transformative outputs
  - CI rejects invalid POV identifiers
  - CI rejects missing access state
- CI proof:
  - expression semantics check
- Repair path:
  - split outputs into separate runs
  - define correct transformation mapping
  - assign valid character_id
  - assign correct access_state_classval_id

## Expression runs must use the most specific chronology beat

- Element: Expression constraint runs
- Type: Chronology discipline contract
- New home:
  - `private/scripts/ci/check_expression_chronology_discipline.php`
- Invariant:
  - parent events represent structural scene beats
  - sub-events represent intra-event state movement
  - emotional shifts must attach to the most specific available chronology address
  - multiple shifts for one character inside one parent event must be split across sub-events
  - expression meaning is POV-specific, never event-global
- Enforcement:
  - CI rejects duplicate character runs on the same chronology address
  - CI rejects malformed chronology addresses
  - CI rejects parent-level expression runs when sub-events exist
- CI proof:
  - expression chronology discipline check
- Repair path:
  - create or use the relevant sub-event
  - move the expression run context to the sub-event entity
  - keep one transformation per character POV beat