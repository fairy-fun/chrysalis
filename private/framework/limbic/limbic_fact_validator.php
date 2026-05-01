<?php


declare(strict_types=1);

const LIMBIC_FACT_TYPE_ID = 'fact_type_event_character_limbic_state';
const POV_BOUNDED_SHAY_ENTITY_ID = 'CHAR-MAIN-001';

function limbic_required_string(array $source, string $key): string
{
    $value = $source[$key] ?? null;

    if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException($key . ' must be a non-empty string');
    }

    return trim($value);
}

function limbic_optional_string_or_null(array $source, string $key): ?string
{
    if (!array_key_exists($key, $source) || $source[$key] === null) {
        return null;
    }

    if (!is_string($source[$key]) || trim($source[$key]) === '') {
        throw new InvalidArgumentException($key . ' must be null or a non-empty string');
    }

    return trim($source[$key]);
}

function limbic_entity_exists(PDO $pdo, string $entityId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sxnzlfun_chrysalis.entities
        WHERE id = :id
    ");

    $stmt->execute([':id' => $entityId]);

    return (int)$stmt->fetchColumn() === 1;
}

function limbic_validate_apply_fact(PDO $pdo, array $body): array
{
    $subjectEntityId = limbic_required_string($body, 'subject_entity_id');
    $contextEntityId = limbic_required_string($body, 'context_entity_id');
    $objectEntityId = limbic_required_string($body, 'object_entity_id');
    $sourceDocument = limbic_required_string($body, 'source_document');
    $notes = limbic_optional_string_or_null($body, 'notes');

    foreach ([
                 'subject_entity_id' => $subjectEntityId,
                 'context_entity_id' => $contextEntityId,
                 'object_entity_id' => $objectEntityId,
             ] as $field => $entityId) {
        if (!limbic_entity_exists($pdo, $entityId)) {
            throw new InvalidArgumentException('Invalid ' . $field . ': ' . $entityId);
        }
    }

    if ($subjectEntityId === POV_BOUNDED_SHAY_ENTITY_ID) {
        limbic_assert_pov_bounded_fact_support($sourceDocument, $notes);
    }

    return [
        'subject_entity_id' => $subjectEntityId,
        'context_entity_id' => $contextEntityId,
        'fact_type_id' => LIMBIC_FACT_TYPE_ID,
        'object_entity_id' => $objectEntityId,
        'source_document' => $sourceDocument,
        'notes' => $notes,
    ];
}

function limbic_assert_pov_bounded_fact_support(string $sourceDocument, ?string $notes): void
{
    /*
     * Deliberately conservative.
     *
     * For POV-bounded characters, especially Shay, this endpoint may only accept
     * prose-backed internal evidence. Do not infer internal regulation from:
     * - calming someone else
     * - stabilising the room
     * - successful performance
     * - steady tone
     * - competence
     *
     * This is a manual assertion gate for now. Later, this can be tightened with
     * prose_annotation_spans support.
     */
    if ($notes === null || trim($notes) === '') {
        throw new InvalidArgumentException(
            'POV-bounded limbic facts require notes describing explicit internal or physiological prose evidence'
        );
    }
}