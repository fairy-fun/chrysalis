<?php

declare(strict_types=1);

function validate_entity_event_theme_link_proposal(PDO $pdo, array $proposal): array
{
    $subjectEntityId = trim((string)($proposal['subject_entity_id'] ?? ''));
    $factTypeId = trim((string)($proposal['fact_type_id'] ?? ''));
    $objectEntityId = trim((string)($proposal['object_entity_id'] ?? ''));

    $errors = [];

    if ($subjectEntityId === '') {
        $errors[] = 'subject_entity_id is required';
    }

    if ($factTypeId !== 'fact_type_event_theme') {
        $errors[] = 'fact_type_id must be fact_type_event_theme';
    }

    if ($objectEntityId === '') {
        $errors[] = 'object_entity_id is required';
    }

    if ($errors !== []) {
        return [
            'ok' => false,
            'errors' => $errors,
        ];
    }

    $stmt = $pdo->prepare(<<<SQL
SELECT COUNT(*) AS count
FROM sxnzlfun_chrysalis.entities
WHERE id IN (:subject_entity_id, :object_entity_id)
SQL);

    $stmt->execute([
        'subject_entity_id' => $subjectEntityId,
        'object_entity_id' => $objectEntityId,
    ]);

    if ((int)$stmt->fetch(PDO::FETCH_ASSOC)['count'] !== 2) {
        $errors[] = 'subject_entity_id and object_entity_id must both exist in entities';
    }

    $dup = $pdo->prepare(<<<SQL
SELECT COUNT(*) AS count
FROM sxnzlfun_chrysalis.entity_linked_facts
WHERE subject_entity_id = :subject_entity_id
  AND fact_type_id = :fact_type_id
  AND object_entity_id = :object_entity_id
SQL);

    $dup->execute([
        'subject_entity_id' => $subjectEntityId,
        'fact_type_id' => $factTypeId,
        'object_entity_id' => $objectEntityId,
    ]);

    if ((int)$dup->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
        $errors[] = 'fact already exists';
    }

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'normalised' => [
            'subject_entity_id' => $subjectEntityId,
            'fact_type_id' => $factTypeId,
            'object_entity_id' => $objectEntityId,
        ],
    ];
}