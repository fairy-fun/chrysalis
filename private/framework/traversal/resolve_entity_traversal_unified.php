<?php
function resolve_entity_traversal_unified(
    PDO $pdo,
    int $pathId,
    ?int $startEntityId,
    string $mode
): array {
    if ($mode === 'frontier') {
        if ($startEntityId === null) {
            throw new InvalidArgumentException('start_entity_id required for frontier mode');
        }

        return execute_frontier_entity_traversal($pdo, $pathId, $startEntityId);
    }

    if ($mode === 'join') {
        return resolve_entity_traversal_full($pdo, $pathId);
    }

    throw new InvalidArgumentException('Invalid traversal mode');
}