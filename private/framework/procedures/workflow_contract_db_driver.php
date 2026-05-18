<?php
declare(strict_types=1);

/**
 * Contract-driven 1-hop DB resolver
 *
 * Enforces:
 * - 1-hop entity fetch only
 * - static persistence contract mapping
 * - no schema inference
 * - no joins
 */

require_once __DIR__ . '/workflow_db_driver.php';
require_once __DIR__ . '/../contracts/persistence_contracts.php';

/**
 * Resolve a single entity via persistence contract and return raw row.
 *
 * @param string $entityTypeId
 * @param int|string $entityId
 * @param array $context
 *
 * @return array|null
 */
function fw_contract_db_fetch_entity(
    string $entityTypeId,
    int|string $entityId,
    array $context = []
): ?array {

    $contract = fw_persistence_contract_resolve($entityTypeId);

    if (!$contract) {
        throw new RuntimeException(
            "Missing persistence contract for entity type: {$entityTypeId}"
        );
    }

    // Strict contract validation (prevents silent bad config)
    if (!isset($contract['table_name'], $contract['lookup_field'])) {
        throw new RuntimeException(
            "Invalid persistence contract structure for: {$entityTypeId}"
        );
    }

    $table = $contract['table_name'];
    $field = $contract['lookup_field'];
    $op    = $contract['lookup_operator'] ?? '=';

    // Hard 1-hop enforcement
    if ($op !== '=') {
        throw new RuntimeException(
            "Only '=' lookup operator is allowed in 1-hop contract driver"
        );
    }

    // Safety: prevent injection via contract misconfiguration
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        throw new RuntimeException("Unsafe table name in contract: {$table}");
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
        throw new RuntimeException("Unsafe lookup field in contract: {$field}");
    }

    // 1-hop query only
    $sql = "SELECT * FROM {$table} WHERE {$field} = ? LIMIT 1";

    $result = fw_db_query_one($sql, [$entityId]);

    return $result ?: null;
}