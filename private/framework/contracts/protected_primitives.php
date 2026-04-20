<?php
declare(strict_types=1);

/**
 * Two protected layers:
 *
 * 1) Raw protected primitives
 *    - direct calls are forbidden outside the DB adapter file
 *
 * 2) Protected DB adapters
 *    - direct calls are forbidden outside the safe service layer
 */

/**
 * Raw protected primitives:
 * only the DB adapter file may call these.
 */
const FW_PROTECTED_PRIMITIVES = [
    'fw_register_system_procedure',
    'fw_upsert_system_directive',
];

/**
 * DB adapter functions:
 * only the safe service files may call these.
 */
const FW_PROTECTED_DB_ADAPTERS = [
    'fw_db_register_system_procedure',
    'fw_db_upsert_system_directive',
];

/**
 * Normalize with forward slashes because the CI scan will normalize paths.
 * Keep these lists tiny and explicit.
 */
const FW_ALLOWED_PRIMITIVE_CALLERS = [
    'private/framework/db/framework_db_calls.php',
];

const FW_ALLOWED_DB_ADAPTER_CALLERS = [
    'private/framework/procedures/procedure_registration_service.php',
    'private/framework/directives/directive_service.php',
];

/**
 * Public safe entrypoints.
 */
const FW_PUBLIC_SAFE_ENTRYPOINTS = [
    'fw_safe_register_system_procedure',
    'fw_safe_upsert_system_directive',
];

const FW_ALLOWED_REGISTER_PREFIX = 'fw_';
const FW_ALLOWED_DIRECTIVE_TARGET_PREFIX = 'fw_run_';