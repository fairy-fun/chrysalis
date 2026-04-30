<?php


declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_annotation_reader.php';

function get_dream_annotations(PDO $pdo, string $dreamEntityId): array
{
    $dreamEntityId = trim($dreamEntityId);

    if ($dreamEntityId === '') {
        throw new InvalidArgumentException('dream_entity_id must be non-empty');
    }

    $stmt = $pdo->prepare("
        SELECT prose_entity_id
        FROM sxnzlfun_chrysalis.dreams
        WHERE dream_entity_id = :dream_entity_id
        LIMIT 1
    ");

    $stmt->execute([
        ':dream_entity_id' => $dreamEntityId,
    ]);

    $proseEntityId = $stmt->fetchColumn();

    if (!is_string($proseEntityId) || trim($proseEntityId) === '') {
        throw new RuntimeException('Dream not found: ' . $dreamEntityId);
    }

    return [
        'dream_entity_id' => $dreamEntityId,
        'prose_entity_id' => $proseEntityId,
        'annotations' => get_prose_annotations($pdo, $proseEntityId),
    ];
}