<?php

declare(strict_types=1);

require __DIR__ . '/../api/api_bootstrap.php';
require __DIR__ . '/../audit/audit_traversal_trigger_integrity.php';
require __DIR__ . '/../audit/audit_event_graph_identity.php';
require __DIR__ . '/../audit/audit_attribute_domain_mapping.php';
require __DIR__ . '/../audit/audit_classval_uniqueness.php';

$pdo = makePdo();
$schemaName = verifyExpectedDatabase($pdo);

assert_traversal_trigger_absence($pdo, $schemaName);
echo "OK: traversal trigger absence passed\n";

assert_event_graph_identity($pdo, $schemaName);
echo "OK: event graph identity passed\n";

assert_attribute_domain_mapping($pdo, $schemaName);
echo "OK: attribute domain mapping passed\n";

assert_classval_uniqueness($pdo, $schemaName);
echo "OK: classval uniqueness passed\n";

echo "OK: all audits passed\n";