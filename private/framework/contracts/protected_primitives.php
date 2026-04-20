<?php
declare(strict_types=1);

/**
 * Protected primitives:
 * direct calls are forbidden outside the allowlist.
 */
const FW_PROTECTED_PRIMITIVES = [
    'fw_register_system_procedure',
    'fw_upsert_system_directive',
];

/**
 * Normalize with forward slashes because the CI scan will normalize paths.
 * Keep this list tiny.
 */
const FW_ALLOWED_PRIMITIVE_CALLERS = [
    'private/framework/db/framework_db_calls.php',
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