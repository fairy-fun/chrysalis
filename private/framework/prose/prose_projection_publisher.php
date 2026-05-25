<?php
declare(strict_types=1);

function set_projection_published_draft(PDO $pdo, array $body): array
{
    $projectionId = (int) ($body['projection_id'] ?? 0);
    $draftId = (int) ($body['published_prose_draft_id'] ?? 0);

    if ($projectionId < 1) {
        throw new InvalidArgumentException('projection_id must be a positive integer');
    }

    if ($draftId < 1) {
        throw new InvalidArgumentException('published_prose_draft_id must be a positive integer');
    }

    $validation = $pdo->prepare('
        SELECT
            pd.id AS prose_draft_id,
            pd.prose_family_id AS draft_family_id,
            pp.id AS projection_id,
            pp.prose_family_id AS projection_family_id,
            pp.target_entity_id,
            pp.is_export_target,
            pp.published_prose_draft_id AS previous_published_prose_draft_id
        FROM prose_drafts pd
        INNER JOIN prose_projections pp
            ON pp.id = :projection_id
        WHERE pd.id = :draft_id
        LIMIT 1
    ');

    $validation->execute([
        ':projection_id' => $projectionId,
        ':draft_id' => $draftId,
    ]);

    $row = $validation->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException('Projection/draft validation failed');
    }

    if ((int) $row['draft_family_id'] !== (int) $row['projection_family_id']) {
        throw new RuntimeException(
            'Cross-family publication mutation forbidden'
        );
    }

    if ((int) $row['is_export_target'] !== 1) {
        throw new RuntimeException(
            'Projection is not export-capable'
        );
    }

    $update = $pdo->prepare('
        UPDATE prose_projections
        SET published_prose_draft_id = :draft_id
        WHERE id = :projection_id
        LIMIT 1
    ');

    $update->execute([
        ':draft_id' => $draftId,
        ':projection_id' => $projectionId,
    ]);

    return [
        'projection_id' => $projectionId,
        'target_entity_id' => $row['target_entity_id'],
        'prose_family_id' => (int) $row['projection_family_id'],
        'previous_published_prose_draft_id' => $row['previous_published_prose_draft_id'],
        'published_prose_draft_id' => $draftId,
    ];
}
