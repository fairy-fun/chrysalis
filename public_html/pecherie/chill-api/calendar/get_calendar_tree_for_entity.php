<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, [
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
}

requireAuth();

$body = $_QUERY_BODY ?? [];
$entityId = $body['entity_id'] ?? null;
$maxDepth = $body['max_depth'] ?? 8;

if (!is_string($entityId) || trim($entityId) === '') {
    respond(400, [
        'status' => 'error',
        'error' => 'entity_id must be a non-empty string',
    ]);
}

$entityId = trim($entityId);

if (!is_int($maxDepth) && !(is_string($maxDepth) && ctype_digit($maxDepth))) {
    respond(400, [
        'status' => 'error',
        'error' => 'max_depth must be a positive integer',
    ]);
}

$maxDepth = (int)$maxDepth;

if ($maxDepth < 0 || $maxDepth > 20) {
    respond(400, [
        'status' => 'error',
        'error' => 'max_depth must be between 0 and 20',
    ]);
}

$pdo = makePdo('read');
$expectedDatabase = verifyExpectedDatabase($pdo);

try {
    $stmt = $pdo->prepare("
        WITH RECURSIVE calendar_tree AS (
            SELECT
                ce.id,
                ce.entity_id,
                ce.event_id,
                ce.parent_event_id,
                ce.layer_id,
                ce.sequence_index,
                ce.summary,
                ce.created_at,
                ce.updated_at,
                e.entity_type_id,
                ce.chronology_address,
                0 AS depth
            FROM sxnzlfun_chrysalis.calendar_events ce
            INNER JOIN sxnzlfun_chrysalis.entities e
                ON e.id = ce.entity_id
            WHERE ce.entity_id = :entity_id

            UNION ALL

            SELECT
                child.id,
                child.entity_id,
                child.event_id,
                child.parent_event_id,
                child.layer_id,
                child.sequence_index,
                child.summary,
                child.created_at,
                child.updated_at,
                child_entity.entity_type_id,
                child.chronology_address,
                calendar_tree.depth + 1 AS depth
            FROM sxnzlfun_chrysalis.calendar_events child
            INNER JOIN sxnzlfun_chrysalis.entities child_entity
                ON child_entity.id = child.entity_id
            INNER JOIN calendar_tree
                ON child.parent_event_id = calendar_tree.id
            WHERE calendar_tree.depth < :max_depth
        )
        SELECT
            id,
            entity_id,
            event_id,
            parent_event_id,
            layer_id,
            sequence_index,
            summary,
            created_at,
            updated_at,
            entity_type_id,
            chronology_address,
            depth
        FROM calendar_tree
        ORDER BY depth ASC, parent_event_id ASC, sequence_index ASC, id ASC
    ");

    $stmt->execute([
        ':entity_id' => $entityId,
        ':max_depth' => $maxDepth,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        respond(404, [
            'status' => 'error',
            'error' => 'Calendar node not found',
            'database' => $expectedDatabase,
        ]);
    }

    $root = null;
    $nodesById = [];

    foreach ($rows as $row) {
        if (!str_starts_with((string)$row['entity_type_id'], 'entity_type_calendar_')) {
            respond(409, [
                'status' => 'error',
                'error' => 'Entity is not a calendar node',
                'database' => $expectedDatabase,
            ]);
        }

        if (!str_starts_with((string)$row['layer_id'], 'calendar_layer_')) {
            respond(409, [
                'status' => 'error',
                'error' => 'Invalid calendar layer',
                'database' => $expectedDatabase,
            ]);
        }

        $row['id'] = (int)$row['id'];
        $row['event_id'] = (int)$row['event_id'];
        $row['parent_event_id'] = $row['parent_event_id'] === null
            ? null
            : (int)$row['parent_event_id'];
        $row['sequence_index'] = $row['sequence_index'] === null
            ? null
            : (int)$row['sequence_index'];
        $row['depth'] = (int)$row['depth'];
        $row['children'] = [];

        $nodesById[$row['id']] = $row;

        if ($row['depth'] === 0) {
            $root = $row;
        }
    }

    if ($root === null) {
        respond(500, [
            'status' => 'error',
            'error' => 'Calendar tree root was not returned',
            'database' => $expectedDatabase,
        ]);
    }

    foreach ($nodesById as $id => &$node) {
        if ($node['depth'] === 0) {
            continue;
        }

        $parentId = $node['parent_event_id'];

        if ($parentId !== null && array_key_exists($parentId, $nodesById)) {
            $nodesById[$parentId]['children'][] = &$node;
        }
    }
    unset($node);

    $rootId = $root['id'];
    $root = stripInternalIds($nodesById[$rootId]);

    function stripInternalIds(array $node): array
    {
        unset($node['id']);

        if (!empty($node['children'])) {
            foreach ($node['children'] as &$child) {
                $child = stripInternalIds($child);
            }
        }

        return $node;
    }

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,
        'max_depth' => $maxDepth,
        'root' => $root,
    ]);

} catch (Throwable $e) {
    debugRespond(500, [
        'error' => 'Failed to fetch calendar tree',
        'database' => $expectedDatabase,
    ], $e);
}