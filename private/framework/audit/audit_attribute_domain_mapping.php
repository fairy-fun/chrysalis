<?php

declare(strict_types=1);

function audit_attribute_domain_mapping(PDO $pdo, string $schemaName): array
{
    $sql = "
        SELECT DISTINCT
            cpa.attribute_type_id
        FROM {$schemaName}.character_profile_attributes cpa
        LEFT JOIN {$schemaName}.attribute_domain_map adm
            ON adm.attribute_type_id = cpa.attribute_type_id
        WHERE adm.attribute_type_id IS NULL
        ORDER BY cpa.attribute_type_id
    ";

    $stmt = $pdo->query($sql);
    $missing = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return [
        'ok' => count($missing) === 0,
        'missing_attribute_type_ids' => $missing,
    ];
}

function assert_attribute_domain_mapping(PDO $pdo, string $schemaName): void
{
    $result = audit_attribute_domain_mapping($pdo, $schemaName);

    if ($result['ok'] !== true) {
        throw new RuntimeException(
            'Attribute type(s) missing domain mapping: '
            . implode(', ', $result['missing_attribute_type_ids'])
        );
    }
}