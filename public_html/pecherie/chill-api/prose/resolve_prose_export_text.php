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

try {
    $body = getJsonBody();

    $projectionTypeId = $body['projection_type_id'] ?? null;

    if (
        !is_string($projectionTypeId)
        || trim($projectionTypeId) === ''
    ) {
        throw new InvalidArgumentException(
            'projection_type_id is required'
        );
    }

    $projectionTypeId = trim($projectionTypeId);

    $pdo = makePdo('read');

    $expectedDatabase = verifyExpectedDatabase($pdo);

    $stmt = $pdo->prepare("
        SELECT
            pp.id AS projection_id,
            pd.id AS prose_draft_id,
            pd.entity_id AS prose_entity_id,
            pd.title,
            pp.target_entity_id,
            pp.projection_order,
            pd.prose_body
        FROM prose_projections pp
        JOIN prose_drafts pd
            ON pd.id = pp.prose_draft_id
        WHERE pp.projection_type_id = :projection_type_id
          AND pp.is_export_target = 1
        ORDER BY
            pp.target_entity_id,
            pp.projection_order,
            pp.id
    ");

    $stmt->execute([
        ':projection_type_id' => $projectionTypeId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond(200, [
        'status' => 'ok',
        'database' => $expectedDatabase,

        /*
         * projection_type_id selects the export surface
         * is_export_target selects canonical export rows
         * pd.prose_body provides renderable text
         */

        'projection_type_id' => $projectionTypeId,
        'export_count' => count($rows),
        'exports' => $rows,
    ]);

} catch (InvalidArgumentException $e) {

    respond(400, [
        'status' => 'error',
        'error' => $e->getMessage(),
    ]);

} catch (Throwable $e) {

    debugRespond(500, [
        'status' => 'error',
        'error' => 'Failed to resolve prose export text',
    ], $e);
}