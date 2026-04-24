<?php

declare(strict_types=1);

function audit_expression_domain_alias(PDO $pdo, string $schemaName): array
{
    $tableCheck = $pdo->prepare(
        'SELECT 1
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = :schema_name
           AND TABLE_NAME = :table_name
         LIMIT 1'
    );

    $tableCheck->execute([
        ':schema_name' => $schemaName,
        ':table_name' => 'expression_domain_aliases',
    ]);

    if ($tableCheck->fetchColumn() === false) {
        return [
            'ok' => true,
            'table_exists' => false,
            'missing_aliases' => [],
            'inactive_aliases' => [],
            'invalid_aliases' => [],
        ];
    }

    $requiredAliases = require __DIR__ . '/../expression/expression_domain_alias_contract.php';

    // --- contract validation (fail fast before DB work) ---
    $seenInputs = [];

    foreach ($requiredAliases as $i => $alias) {
        $input = trim((string)($alias['input_domain_id'] ?? ''));
        $target = trim((string)($alias['target_domain_id'] ?? ''));

        if ($input === '') {
            throw new RuntimeException(
                'Expression domain alias contract error: empty input_domain_id at index ' . $i
            );
        }

        if ($target === '') {
            throw new RuntimeException(
                'Expression domain alias contract error: empty target_domain_id for input_domain_id=' . $input
            );
        }

        if (isset($seenInputs[$input])) {
            throw new RuntimeException(
                'Expression domain alias contract error: duplicate input_domain_id=' . $input
            );
        }

        $seenInputs[$input] = true;
    }

    $stmt = $pdo->prepare(
        "SELECT input_domain_id, target_domain_id, is_active
         FROM {$schemaName}.expression_domain_aliases
         WHERE input_domain_id = :input_domain_id
         LIMIT 1"
    );

    $missingAliases = [];
    $inactiveAliases = [];
    $invalidAliases = [];

    foreach ($requiredAliases as $requiredAlias) {
        $stmt->execute([
            ':input_domain_id' => $requiredAlias['input_domain_id'],
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            $missingAliases[] = $requiredAlias;
            continue;
        }

        if ((int)($row['is_active'] ?? 0) !== 1) {
            $inactiveAliases[] = $row;
            continue;
        }

        if ((string)($row['target_domain_id'] ?? '') !== $requiredAlias['target_domain_id']) {
            $invalidAliases[] = [
                'input_domain_id' => (string)($row['input_domain_id'] ?? ''),
                'expected_target_domain_id' => $requiredAlias['target_domain_id'],
                'actual_target_domain_id' => (string)($row['target_domain_id'] ?? ''),
            ];
        }
    }

    $targetDomainIds = array_values(array_unique(array_map(
        static fn(array $alias): string => trim((string)($alias['target_domain_id'] ?? '')),
        $requiredAliases
    )));

    $targetDomainIds = array_values(array_filter(
        $targetDomainIds,
        static fn(string $id): bool => $id !== ''
    ));

    $missingTargetDomainEntities = [];

    if ($targetDomainIds !== []) {
        $placeholders = implode(',', array_fill(0, count($targetDomainIds), '?'));

        $entityStmt = $pdo->prepare("
        SELECT id
        FROM {$schemaName}.entities
        WHERE id IN ($placeholders)
          AND entity_type_id = 'entity_type_domain'
    ");

        $entityStmt->execute($targetDomainIds);

        $validTargetDomainIds = $entityStmt->fetchAll(PDO::FETCH_COLUMN);
        $missingTargetDomainEntities = array_values(array_diff($targetDomainIds, $validTargetDomainIds));
    }

    return [
        'ok' => count($missingAliases) === 0
            && count($inactiveAliases) === 0
            && count($invalidAliases) === 0
            && count($missingTargetDomainEntities) === 0,
        'table_exists' => true,
        'missing_aliases' => $missingAliases,
        'inactive_aliases' => $inactiveAliases,
        'invalid_aliases' => $invalidAliases,
        'missing_target_domain_entities' => $missingTargetDomainEntities,
    ];
}

function assert_expression_domain_alias(PDO $pdo, string $schemaName): void
{
    $result = audit_expression_domain_alias($pdo, $schemaName);

    if ($result['ok'] === true) {
        return;
    }

    throw new RuntimeException(
        'Expression domain alias audit failed: '
        . json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
    );
}
