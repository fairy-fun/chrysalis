<?php

declare(strict_types=1);

require_once __DIR__ . '/assert_entity_exists.php';
require_once __DIR__ . '/assert_valid_entity_type_id.php';
require_once __DIR__ . '/assert_valid_fact_type_id.php';
require_once __DIR__ . '/find_exact_entity_by_canonical_label.php';
require_once __DIR__ . '/resolve_or_suggest_entity_id.php';
require_once __DIR__ . '/entity_link_suggestions.php';

/**
 * Suggest how to link an entity from an explicit subject.
 *
 * Returns structured suggestion output only.
 * Performs no writes.
 *
 * @return array<string,mixed>
 *
 * @throws InvalidArgumentException
 * @throws RuntimeException
 */
function suggest_link_entity_explicit_subject(
    PDO $pdo,
    string $subjectEntityId,
    string $rawLabel,
    string $entityTypeId,
    string $factTypeId
): array {
    if ($subjectEntityId === '') {
        throw new InvalidArgumentException('Subject entity id is required');
    }

    if ($subjectEntityId !== trim($subjectEntityId)) {
        throw new InvalidArgumentException('Subject entity id must not contain surrounding whitespace');
    }

    $rawLabel = trim($rawLabel);

    if ($rawLabel === '') {
        throw new InvalidArgumentException('Entity label is required');
    }

    if ($entityTypeId === '') {
        throw new InvalidArgumentException('Entity type id is required');
    }

    if ($entityTypeId !== trim($entityTypeId)) {
        throw new InvalidArgumentException('Entity type id must not contain surrounding whitespace');
    }

    if ($factTypeId === '') {
        throw new InvalidArgumentException('Fact type id is required');
    }

    if ($factTypeId !== trim($factTypeId)) {
        throw new InvalidArgumentException('Fact type id must not contain surrounding whitespace');
    }

    $subject = assert_entity_exists($pdo, $subjectEntityId);

    assert_valid_entity_type_id($pdo, $entityTypeId);
    assert_valid_fact_type_id($pdo, $factTypeId);

    $existing = find_exact_entity_by_canonical_label($pdo, $rawLabel, $entityTypeId);

    if ($existing !== null) {
        $existingEntityId = is_array($existing)
            ? (string)($existing['id'] ?? $existing['entity_id'] ?? '')
            : (string)$existing;

        if ($existingEntityId === '') {
            throw new RuntimeException('Exact entity lookup returned no usable entity id');
        }

        return [
            'type' => 'sql_suggestion',
            'action' => 'link_entity_generic',
            'subject_entity_id' => (string)($subject['id'] ?? $subjectEntityId),
            'entity_id' => $existingEntityId,
            'steps' => [
                [
                    'step' => 'link_entity',
                    'sql' => build_link_entity_sql(
                        $pdo,
                        (string)($subject['id'] ?? $subjectEntityId),
                        $factTypeId,
                        $existingEntityId
                    ),
                ],
            ],
        ];
    }

    $resolution = resolve_or_suggest_entity_id($pdo, $rawLabel, $entityTypeId);

    $newEntityId = (string)($resolution['entity_id'] ?? '');
    if ($newEntityId === '') {
        throw new RuntimeException('Entity resolution returned no entity_id');
    }

    $resolutionMode = (string)($resolution['mode'] ?? $resolution['resolution_code'] ?? '');
    if ($resolutionMode === '') {
        throw new RuntimeException('Entity resolution returned no mode');
    }

    return [
        'type' => 'sql_suggestion_bundle',
        'action' => 'create_and_link_entity_generic',
        'subject_entity_id' => (string)($subject['id'] ?? $subjectEntityId),
        'entity_id_resolution' => [
            'entity_id' => $newEntityId,
            'mode' => $resolutionMode,
        ],
        'steps' => [
            [
                'step' => 'create_entity',
                'sql' => build_create_entity_sql(
                    $pdo,
                    $newEntityId,
                    $entityTypeId
                ),
            ],
            [
                'step' => 'create_label',
                'sql' => build_create_label_sql(
                    $pdo,
                    $newEntityId,
                    $entityTypeId,
                    $rawLabel
                ),
            ],
            [
                'step' => 'link_entity',
                'sql' => build_link_entity_sql(
                    $pdo,
                    (string)($subject['id'] ?? $subjectEntityId),
                    $factTypeId,
                    $newEntityId
                ),
            ],
        ],
    ];
}