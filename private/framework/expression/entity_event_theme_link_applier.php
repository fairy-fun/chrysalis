<?php

declare(strict_types=1);

require_once __DIR__ . '/entity_event_theme_link_validator.php';

function apply_entity_event_theme_link_proposal(PDO $pdo, array $proposal): array
{
    $validation = validate_entity_event_theme_link_proposal($pdo, $proposal);

    if (($validation['ok'] ?? false) !== true) {
        return [
            'status' => 'rejected',
            'validation' => $validation,
        ];
    }

    $row = $validation['normalised'];

    $stmt = $pdo->prepare(<<<SQL
INSERT INTO sxnzlfun_chrysalis.entity_linked_facts (
    subject_entity_id,
    fact_type_id,
    object_entity_id,
    created_at
)
VALUES (
    :subject_entity_id,
    :fact_type_id,
    :object_entity_id,
    NOW()
)
SQL);

    $stmt->execute([
        'subject_entity_id' => $row['subject_entity_id'],
        'fact_type_id' => $row['fact_type_id'],
        'object_entity_id' => $row['object_entity_id'],
    ]);

    return [
        'status' => 'applied',
        'fact_id' => (int)$pdo->lastInsertId(),
        'fact' => $row,
    ];
}