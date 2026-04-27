<?php

declare(strict_types=1);

function create_performance_routine(
    PDO $pdo,
    string $teamId,
    string $choreographyTypeId,
    string $statusClassvalId,
    int $yearId,
    ?int $medleyId,
    string $routineName,
    ?string $musicTitle,
    ?int $durationSeconds,
    ?string $notes,
    ?string $sourceDocument
): array {
    $stmt = $pdo->prepare(
        'INSERT INTO performance_routines (
            team_id,
            choreography_type_id,
            status_classval_id,
            year_id,
            medley_id,
            routine_name,
            music_title,
            duration_seconds,
            notes,
            source_document,
            created_at,
            updated_at
        ) VALUES (
            :team_id,
            :choreography_type_id,
            :status_classval_id,
            :year_id,
            :medley_id,
            :routine_name,
            :music_title,
            :duration_seconds,
            :notes,
            :source_document,
            NOW(),
            NOW()
        )'
    );

    $stmt->execute([
        ':team_id' => $teamId,
        ':choreography_type_id' => $choreographyTypeId,
        ':status_classval_id' => $statusClassvalId,
        ':year_id' => $yearId,
        ':medley_id' => $medleyId,
        ':routine_name' => $routineName,
        ':music_title' => $musicTitle,
        ':duration_seconds' => $durationSeconds,
        ':notes' => $notes,
        ':source_document' => $sourceDocument,
    ]);

    $routineId = (int) $pdo->lastInsertId();

    return [
        'routine_id' => $routineId,
        'team_id' => $teamId,
        'choreography_type_id' => $choreographyTypeId,
        'status_classval_id' => $statusClassvalId,
        'year_id' => $yearId,
        'medley_id' => $medleyId,
        'routine_name' => $routineName,
        'music_title' => $musicTitle,
        'duration_seconds' => $durationSeconds,
        'notes' => $notes,
        'source_document' => $sourceDocument
    ];
}