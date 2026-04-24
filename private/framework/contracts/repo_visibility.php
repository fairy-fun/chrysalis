<?php

declare(strict_types=1);

return [
    'visible_prefixes' => [
        '.github/workflows',
        'private/framework/contracts',
        'private/framework/support',
        'public_html/pecherie/chill-api/repo',
        'public_html/pecherie/chill-api/choreography',
        'public_html/pecherie/chill-api/entity',
        'public_html/pecherie/chill-api/expression',
        'public_html/pecherie/chill-api/audit',
        'public_html/pecherie/chill-api/calendar',
    ],

    'visible_files' => [
        'private/framework/procedures/procedure_registry_reader.php',
        'private/framework/procedures/procedure_source_inspector.php',
        'private/framework/directives/directive_text.php',
        'private/framework/directives/directive_validator.php',
        'public_html/pecherie/chill-api/index.php',
        'public_html/pecherie/chill-api/query.php',
        'public_html/pecherie/chill-api/tables.php',
        'public_html/pecherie/chill-api/columns.php',
    ],

    'required_operations' => [
        'listRepo' => 'public_html/pecherie/chill-api/repo/list_repo.php',
        'getRepoFile' => 'public_html/pecherie/chill-api/repo/get_repo_file.php',
        'executeSqlRead' => 'public_html/pecherie/chill-api/query.php',
        'tables' => 'public_html/pecherie/chill-api/tables.php',
        'columns' => 'public_html/pecherie/chill-api/columns.php',
        'query' => 'public_html/pecherie/chill-api/query.php',
        'resolveMedleyCore' => 'public_html/pecherie/chill-api/choreography/resolve_medley_core.php',
        'suggestLinkEntity' => 'public_html/pecherie/chill-api/entity/suggest_link_entity.php',
        'auditTraversalTriggerIntegrity' => 'public_html/pecherie/chill-api/audit/traversal_trigger_integrity.php',
        'auditEventGraphIdentity' => 'public_html/pecherie/chill-api/audit/event_graph_identity.php',
        'resolveEntityTraversal' => 'public_html/pecherie/chill-api/entity/resolve_entity_traversal.php',
        'resolveEntityMeasurements' => 'public_html/pecherie/chill-api/entity/resolve_entity_measurements.php',
        'resolveCharacterExpressionOutput' => 'public_html/pecherie/chill-api/expression/resolve_character_expression_output.php',
    ],
];
