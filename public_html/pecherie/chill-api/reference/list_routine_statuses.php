<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respond(405, ['error' => 'Method not allowed']);
}

requireAuth();

$pdo = makePdo();
$expectedDatabase = verifyExpectedDatabase($pdo);

$stmt = $pdo->query(
    "SELECT id, label FROM classvals WHERE classval_type_id = 'routine_status' ORDER BY label"
);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

respond(200, [
    'status' => 'ok',
    'database' => $expectedDatabase,
    'routine_statuses' => $rows,
]);