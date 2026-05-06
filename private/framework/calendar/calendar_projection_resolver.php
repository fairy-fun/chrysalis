<?php

declare(strict_types=1);

/**
 * Canonical projection resolution boundary.
 *
 * Runtime code should use projection_id internally.
 * projection_entity_id / entity_id resolution exists only as external
 * compatibility ingress.
 */

/**
 * Resolve a projection row by canonical projection id.
 */
function find_calendar_projection_by_id(
    PDO $pdo,
    int $projectionId
): ?array {
    if ($projectionId < 1) {
        throw new InvalidArgumentException(
            'projection_id must be positive'
        );
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM sxnzlfun_chrysalis.calendar_projections
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $projectionId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/**
 * Require a projection row by canonical projection id.
 */
function require_calendar_projection_by_id(
    PDO $pdo,
    int $projectionId
): array {
    $projection = find_calendar_projection_by_id(
        $pdo,
        $projectionId
    );

    if ($projection === null) {
        throw new RuntimeException(
            "Projection not found for projection_id: {$projectionId}"
        );
    }

    return $projection;
}

/**
 * Resolve a projection row by external compatibility entity id.
 */
function find_calendar_projection_by_entity_id(
    PDO $pdo,
    string $projectionEntityId
): ?array {
    $projectionEntityId = trim($projectionEntityId);

    if ($projectionEntityId === '') {
        throw new InvalidArgumentException(
            'projection_entity_id must be non-empty'
        );
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM sxnzlfun_chrysalis.calendar_projections
        WHERE entity_id = :entity_id
        LIMIT 1
    ");

    $stmt->execute([
        ':entity_id' => $projectionEntityId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/**
 * Require a projection row by external compatibility entity id.
 */
function require_calendar_projection_by_entity_id(
    PDO $pdo,
    string $projectionEntityId
): array {
    $projection = find_calendar_projection_by_entity_id(
        $pdo,
        $projectionEntityId
    );

    if ($projection === null) {
        throw new RuntimeException(
            "Projection not found for entity_id: {$projectionEntityId}"
        );
    }

    return $projection;
}

/**
 * Backward-compatible helper.
 *
 * Prefer find_calendar_projection_by_entity_id() for compatibility ingress
 * and find_calendar_projection_by_id() for runtime canonical resolution.
 */
function find_calendar_projection(
    PDO $pdo,
    string $projectionEntityId
): ?array {
    return find_calendar_projection_by_entity_id(
        $pdo,
        $projectionEntityId
    );
}

/**
 * Backward-compatible helper.
 *
 * Prefer require_calendar_projection_by_entity_id() for compatibility ingress
 * and require_calendar_projection_by_id() for runtime canonical resolution.
 */
function require_calendar_projection(
    PDO $pdo,
    string $projectionEntityId
): array {
    return require_calendar_projection_by_entity_id(
        $pdo,
        $projectionEntityId
    );
}

/**
 * Backward-compatible helper.
 *
 * Existing runtime code may depend on this signature.
 * Keep until all internals standardize on projection_id.
 */
function require_calendar_projection_id(
    PDO $pdo,
    string $projectionEntityId
): int {
    $projection = require_calendar_projection_by_entity_id(
        $pdo,
        $projectionEntityId
    );

    if (!isset($projection['id'])) {
        throw new RuntimeException(
            'Projection row missing id'
        );
    }

    return (int)$projection['id'];
}

/**
 * Resolve external compatibility identifier from canonical id.
 */
function calendar_projection_entity_id(
    PDO $pdo,
    int $projectionId
): string {
    $projection = require_calendar_projection_by_id(
        $pdo,
        $projectionId
    );

    $entityId = $projection['entity_id'] ?? null;

    if (!is_string($entityId) || trim($entityId) === '') {
        throw new RuntimeException(
            "Projection entity_id not found for projection_id: {$projectionId}"
        );
    }

    return trim($entityId);
}

/**
 * Assert canonical projection id exists.
 */
function assert_calendar_projection_exists(
    PDO $pdo,
    int $projectionId
): void {
    require_calendar_projection_by_id(
        $pdo,
        $projectionId
    );
}