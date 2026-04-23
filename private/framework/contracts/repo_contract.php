<?php
declare(strict_types=1);

const FW_AUDIT_ENTRYPOINT = 'private/framework/contracts/chrysalis_hydration_prompt.md';

const FW_REPO_CONTRACT = [
    'audit_entrypoint' => FW_AUDIT_ENTRYPOINT,
    'doctrine_owner' => 'php',
    'db_role' => 'transport_only',
    'ci_role' => 'enforcement_authority',

    'api_operations' => [
        'listRepo' => [
            'handler' => 'public_html/pecherie/chill-api/repo/list_repo.php',
            'behaviour_tested' => true,
            'audit_visibility_required' => true,
        ],
        'getRepoFile' => [
            'handler' => 'public_html/pecherie/chill-api/repo/get_repo_file.php',
            'behaviour_tested' => true,
            'audit_visibility_required' => true,
        ],
        'query' => [
            'handler' => 'public_html/pecherie/chill-api/query.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'executeSqlRead' => [
            'handler' => 'public_html/pecherie/chill-api/query.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'tables' => [
            'handler' => 'public_html/pecherie/chill-api/tables.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'columns' => [
            'handler' => 'public_html/pecherie/chill-api/columns.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'resolveMedleyCore' => [
            'handler' => 'public_html/pecherie/chill-api/choreography/resolve_medley_core.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'suggestLinkEntity' => [
            'handler' => 'public_html/pecherie/chill-api/entity/suggest_link_entity.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
        'auditTraversalTriggerIntegrity' => [
            'handler' => 'public_html/pecherie/chill-api/audit/traversal_trigger_integrity.php',
            'behaviour_tested' => false,
            'audit_visibility_required' => true,
        ],
    ],
];