<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__, 3);
require_once $repoRoot . '/private/framework/api/api_bootstrap.php';

$pdo = makePdo();

function require_table_exists_for_profile_type_mirror(PDO $pdo, string $tableName): void
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
         LIMIT 1'
    );

    $stmt->execute([':table_name' => $tableName]);

    if ($stmt->fetchColumn() === false) {
        throw new RuntimeException('Required table missing: ' . $tableName);
    }
}

function upsert_profile_type_entity_type(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO entity_type_classvals (
            id,
            code,
            label
        )
        VALUES (
            :id,
            :code,
            :label
        )
        ON DUPLICATE KEY UPDATE
            code = VALUES(code),
            label = VALUES(label)'
    );

    $stmt->execute([
        ':id' => 'entity_type_profile_type',
        ':code' => 'profile_type',
        ':label' => 'Profile Type',
    ]);
}

function mirror_all_profile_types_as_entities(PDO $pdo): int
{
    $stmt = $pdo->prepare(
        "INSERT INTO entities (
            id,
            entity_type_id
        )
        SELECT DISTINCT
            refs.profile_type_id,
            'entity_type_profile_type'
        FROM (
            SELECT profile_type_id
            FROM character_profiles
            WHERE profile_type_id IS NOT NULL

            UNION

            SELECT profile_type_id
            FROM profile_type_priority
            WHERE profile_type_id IS NOT NULL

            UNION

            SELECT profile_type_id
            FROM profile_type_domain_map
            WHERE profile_type_id IS NOT NULL
        ) refs
        LEFT JOIN entities e
            ON e.id = refs.profile_type_id
        WHERE e.id IS NULL"
    );

    $stmt->execute();

    return $stmt->rowCount();
}

require_table_exists_for_profile_type_mirror($pdo, 'entity_type_classvals');
require_table_exists_for_profile_type_mirror($pdo, 'entities');
require_table_exists_for_profile_type_mirror($pdo, 'character_profiles');
require_table_exists_for_profile_type_mirror($pdo, 'profile_type_priority');
require_table_exists_for_profile_type_mirror($pdo, 'profile_type_domain_map');

upsert_profile_type_entity_type($pdo);
$count = mirror_all_profile_types_as_entities($pdo);

echo 'OK: Mirrored profile types as entities: ' . (string)$count . PHP_EOL;
