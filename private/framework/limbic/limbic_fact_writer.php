<?php

declare(strict_types=1);

require_once __DIR__ . '/../facts/apply_fact.php';
require_once __DIR__ . '/limbic_fact_validator.php';

function apply_limbic_fact(PDO $pdo, array $body): array
{
    $fact = limbic_validate_apply_fact($pdo, $body);

    return apply_event_fact(
        $pdo,
        $fact['subject_entity_id'],
        $fact['context_entity_id'],
        $fact['fact_type_id'],
        $fact['object_entity_id'],
        $fact['source_document'],
        $fact['notes']
    );
}