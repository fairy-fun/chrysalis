<?php


declare(strict_types=1);

require __DIR__ . '/../api/api_bootstrap.php';
require __DIR__ . '/../audit/audit_event_graph_identity.php';

$pdo = makePdo();
$schemaName = verifyExpectedDatabase($pdo);

assert_event_graph_identity($pdo, $schemaName);

echo "OK: event graph identity audit passed\n";