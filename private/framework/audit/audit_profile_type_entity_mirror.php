<?php

declare(strict_types=1);

function audit_profile_type_entity_mirror(PDO $pdo, string $schemaName): array
{
    $missingSql = "
        SELECT
            refs.profile_type_id,
            refs.source_table
        FROM (
            SELECT DISTINCT
                cp.profile_type_id,
                'character_profiles' AS source_table
            FROM {$schemaName}.character_profiles cp
            WHERE cp.profile_type_id IS NOT NULL

            UNION

            SELECT DISTINCT
                ptp.profile_type_id,
                'profile_type_priority' AS source_table
            FROM {$schemaName}.profile_type_priority ptp
            WHERE ptp.profile_type_id IS NOT NULL

            UNION

            SELECT DISTINCT
                ptdm.profile_type_id,
                'profile_type_domain_map' AS source_table
            FROM {$schemaName}.profile_type_domain_map ptdm
            WHERE ptdm.profile_type_id IS NOT NULL
        ) refs
        LEFT JOIN {$schemaName}.entities e
            ON e.id = refs.profile_type_id
        WHERE e.id IS NULL
        ORDER BY refs.profile_type_id, refs.source_table
    ";

    $wrongTypeSql = "
        SELECT
            refs.profile_type_id,
            refs.source_table,
            e.entity_type_id AS actual_entity_type_id
        FROM (
            SELECT DISTINCT
                cp.profile_type_id,
                'character_profiles' AS source_table
            FROM {$schemaName}.character_profiles cp
            WHERE cp.profile_type_id IS NOT NULL

            UNION

            SELECT DISTINCT
                ptp.profile_type_id,
                'profile_type_priority' AS source_table
            FROM {$schemaName}.profile_type_priority ptp
            WHERE ptp.profile_type_id IS NOT NULL

            UNION

            SELECT DISTINCT
                ptdm.profile_type_id,
                'profile_type_domain_map' AS source_table
            FROM {$schemaName}.profile_type_domain_map ptdm
            WHERE ptdm.profile_type_id IS NOT NULL
        ) refs
        JOIN {$schemaName}.entities e
            ON e.id = refs.profile_type_id
        WHERE e.entity_type_id <> 'entity_type_profile_type'
        ORDER BY refs.profile_type_id, refs.source_table
    ";

    $missingStmt = $pdo->query($missingSql);
    $missingEntities = $missingStmt->fetchAll(PDO::FETCH_ASSOC);

    $wrongTypeStmt = $pdo->query($wrongTypeSql);
    $wrongTypeEntities = $wrongTypeStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'ok' => count($missingEntities) === 0 && count($wrongTypeEntities) === 0,
        'schema_name' => $schemaName,
        'expected_entity_type_id' => 'entity_type_profile_type',
        'missing_entity_count' => count($missingEntities),
        'wrong_type_count' => count($wrongTypeEntities),
        'missing_entities' => $missingEntities,
        'wrong_type_entities' => $wrongTypeEntities,
    ];
}

function assert_profile_type_entity_mirror(PDO $pdo, string $schemaName): void
{
    $audit = audit_profile_type_entity_mirror($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Profile type entity mirror audit failed: missing mirrors='
        . (string)$audit['missing_entity_count']
        . ', wrong type='
        . (string)$audit['wrong_type_count']
    );
}
