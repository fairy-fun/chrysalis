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
ON DUPLICATE KEY UPDATE
    linked_fact_id = linked_fact_id
SQL);

    $stmt->execute([
        'subject_entity_id' => $row['subject_entity_id'],
        'fact_type_id' => $row['fact_type_id'],
        'object_entity_id' => $row['object_entity_id'],
    ]);

    return [
        'status' => $stmt->rowCount() > 0 ? 'applied' : 'duplicate',
        'fact_id' => (int)$pdo->lastInsertId(),
        'fact' => $row,
    ];
}

function applyEntityEventThemeSuggestions(PDO $pdo, array $proposals): array
{
    $results = [];

    foreach ($proposals as $index => $proposal) {
        if (!is_array($proposal)) {
            $results[] = [
                'index' => $index,
                'status' => 'rejected',
                'validation' => [
                    'ok' => false,
                    'errors' => ['proposal must be an array'],
                ],
            ];
            continue;
        }

        $result = apply_entity_event_theme_link_proposal($pdo, $proposal);
        $result['index'] = $index;

        $results[] = $result;
    }

    return [
        'status' => 'ok',
        'proposal_count' => count($proposals),
        'applied_count' => count(array_filter($results, fn ($r) => ($r['status'] ?? null) === 'applied')),
        'duplicate_count' => count(array_filter($results, fn ($r) => ($r['status'] ?? null) === 'duplicate')),
        'rejected_count' => count(array_filter($results, fn ($r) => ($r['status'] ?? null) === 'rejected')),
        'results' => $results,
    ];
}