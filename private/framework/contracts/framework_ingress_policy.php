<?php

declare(strict_types=1);

return [
    'required_visible_files' => [
        'private/framework/contracts/protected_primitives.php',
        'private/framework/support/assertions.php',
        'private/framework/support/audit_log.php',
        'private/framework/procedures/procedure_registry_reader.php',
        'private/framework/procedures/procedure_source_inspector.php',
        'private/framework/directives/directive_text.php',
        'private/framework/directives/directive_validator.php',
    ],

    'forbidden_visible_prefixes' => [
        'private/framework/db',
        'private/framework/procedures',
        'private/framework/directives',
    ],

    'forbidden_visible_files' => [
        'private/framework/bootstrap.php',
        'private/framework/db/framework_db_calls.php',
        'private/framework/procedures/procedure_registration_service.php',
        'private/framework/directives/directive_service.php',
    ],
];
