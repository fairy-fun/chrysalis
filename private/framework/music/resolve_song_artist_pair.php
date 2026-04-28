<?php

declare(strict_types=1);

const SONG_ARTIST_FACT_TYPE_ID = 'fact_type_song_artist';

function resolve_song_artist_pair_song_id(PDO $pdo, mixed $rawSongEntityId): string
{
    if (is_string($rawSongEntityId) && trim($rawSongEntityId) !== '') {
        return trim($rawSongEntityId);
    }

    if ($rawSongEntityId !== null) {
        throw new InvalidArgumentException('song_entity_id must be a non-empty string when provided');
    }

    $stmt = $pdo->query(<<<'SQL'
SELECT rc.entity_id
FROM sxnzlfun_chrysalis.request_context rc
WHERE rc.entity_id IS NOT NULL
ORDER BY rc.created_at DESC, rc.context_id DESC
LIMIT 1
SQL);

    $songEntityId = $stmt->fetchColumn();

    if (!is_string($songEntityId) || trim($songEntityId) === '') {
        throw new RuntimeException('No song found in request_context');
    }

    return trim($songEntityId);
}

function resolve_song_artist_pair(PDO $pdo, string $songEntityId): array
{
    $songEntityId = trim($songEntityId);

    if ($songEntityId === '') {
        throw new InvalidArgumentException('song_entity_id must be a non-empty string');
    }

    $stmt = $pdo->prepare(<<<'SQL'
SELECT
    elf.subject_entity_id AS song_entity_id,
    elf.object_entity_id AS artist_entity_id
FROM sxnzlfun_chrysalis.entity_linked_facts elf
WHERE elf.subject_entity_id = :song_entity_id
  AND elf.fact_type_id = :fact_type_id
ORDER BY elf.object_entity_id ASC
SQL);

    $stmt->execute([
        'song_entity_id' => $songEntityId,
        'fact_type_id' => SONG_ARTIST_FACT_TYPE_ID,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $matchCount = count($rows);

    if ($matchCount === 0) {
        return [
            'song_entity_id' => $songEntityId,
            'artist_entity_id' => null,
            'match_status' => 'not_found',
            'match_count' => 0,
            'matches' => [],
        ];
    }

    if ($matchCount > 1) {
        return [
            'song_entity_id' => $songEntityId,
            'artist_entity_id' => null,
            'match_status' => 'ambiguous',
            'match_count' => $matchCount,
            'matches' => $rows,
        ];
    }

    return [
        'song_entity_id' => $songEntityId,
        'artist_entity_id' => $rows[0]['artist_entity_id'],
        'match_status' => 'resolved',
        'match_count' => 1,
        'matches' => $rows,
    ];
}