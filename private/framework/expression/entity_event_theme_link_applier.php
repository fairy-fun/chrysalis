<?php

declare(strict_types=1);

require_once __DIR__ . '/entity_event_theme_link_validator.php';
require_once __DIR__ . '/../facts/apply_fact.php';

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
    $contextEntityId = $row['context_entity_id'] ?? $row['subject_entity_id'];

    $result = apply_event_fact(
        $pdo,
        $row['subject_entity_id'],
        $contextEntityId,
        $row['fact_type_id'],
        $row['object_entity_id'],
        $row['source_document'] ?? null,
        $row['notes'] ?? null
    );

    return array_merge($result, [
        'fact' => array_merge($result['fact'] ?? [], $row, [
            'context_entity_id' => $contextEntityId,
        ]),
    ]);
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