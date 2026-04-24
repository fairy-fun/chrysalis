<?php

declare(strict_types=1);

require __DIR__ . '/../api/api_bootstrap.php';
require __DIR__ . '/../audit/audit_traversal_trigger_integrity.php';
require __DIR__ . '/../audit/audit_event_graph_identity.php';
require __DIR__ . '/../audit/audit_attribute_domain_mapping.php';
require __DIR__ . '/../audit/audit_classval_uniqueness.php';
require __DIR__ . '/../audit/audit_classval_reference_integrity.php';
require __DIR__ . '/../audit/audit_classval_entity_mirror.php';
require __DIR__ . '/../audit/audit_profile_type_entity_mirror.php';
require __DIR__ . '/../audit/audit_status_entity_mirror.php';
require __DIR__ . '/../audit/audit_figure_entity_mirror.php';
require __DIR__ . '/../audit/audit_typed_entity_reference_integrity.php';
require __DIR__ . '/../audit/audit_untyped_varchar_id_surface.php';
require __DIR__ . '/../audit/audit_expression_domain_alias.php';

$pdo = makePdo();
$schemaName = verifyExpectedDatabase($pdo);

$audits = [
    'traversal trigger absence' => fn () => assert_traversal_trigger_absence($pdo, $schemaName),
    'event graph identity' => fn () => assert_event_graph_identity($pdo, $schemaName),
    'attribute domain mapping' => fn () => assert_attribute_domain_mapping($pdo, $schemaName),
    'classval uniqueness' => fn () => assert_classval_uniqueness($pdo, $schemaName),
    'classval reference integrity' => fn () => assert_classval_reference_integrity($pdo, $schemaName),
    'classval entity mirror' => fn () => assert_classval_entity_mirror($pdo, $schemaName),
    'profile type entity mirror' => fn () => assert_profile_type_entity_mirror($pdo, $schemaName),
    'status entity mirror' => fn () => assert_status_entity_mirror($pdo, $schemaName),
    'figure entity mirror' => fn () => assert_figure_entity_mirror($pdo, $schemaName),
    'typed entity reference integrity' => fn () => assert_typed_entity_reference_integrity($pdo, $schemaName),
    'untyped varchar id surface' => fn () => assert_untyped_varchar_id_surface($pdo, $schemaName),
    'expression domain alias' => fn () => assert_expression_domain_alias($pdo, $schemaName),
];

foreach ($audits as $auditName => $runAudit) {
    echo "==> Running audit: {$auditName}\n";

    try {
        $runAudit();
        echo "OK: {$auditName} passed\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "\nFAIL: {$auditName}\n");
        fwrite(STDERR, $e->getMessage() . "\n");

        if ($e->getPrevious() !== null) {
            fwrite(STDERR, "Previous: " . $e->getPrevious()->getMessage() . "\n");
        }

        exit(1);
    }
}

echo "OK: all audits passed\n";
