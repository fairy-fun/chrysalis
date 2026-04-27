<?php

declare(strict_types=1);

function create_performance_routine(
    PDO $pdo,
    string $teamId,
    int $yearValue,
    string $routineName,
    string $choreographyTypeId,
    ?string $musicTitle,
    ?int $durationSeconds,
    ?string $statusClassvalId,
    ?string $notes,
    ?string $sourceDocument
): array {
    $teamId = trim($teamId);
    $routineName = trim($routineName);
    $choreographyTypeId = trim($choreographyTypeId);
    $statusClassvalId = is_string($statusClassvalId) ? trim($statusClassvalId) : null;

    if ($teamId === '') {
        throw new InvalidArgumentException('team_id must be a non-empty string');
    }

    if ($yearValue < 1) {
        throw new InvalidArgumentException('year_value must be a positive integer');
    }

    if ($routineName === '') {
        throw new InvalidArgumentException('routine_name must be a non-empty string');
    }

    if ($choreographyTypeId === '') {
        throw new InvalidArgumentException('choreography_type_id must be a non-empty string');
    }

    if ($durationSeconds !== null && $durationSeconds < 1) {
        throw new InvalidArgumentException('duration_seconds must be a positive integer when supplied');
    }

    if ($statusClassvalId === '') {
        $statusClassvalId = null;
    }

    assert_performance_routine_entity_exists($pdo, $teamId);
    $yearId = resolve_performance_routine_year_id($pdo, $yearValue);
    assert_performance_routine_classval_type(
        $pdo,
        $choreographyTypeId,
        'classval_type_choreography_type',
        'choreography_type_id'
    );

    if ($statusClassvalId !== null) {
        assert_performance_routine_classval_type(
            $pdo,
            $statusClassvalId,
            'classval_type_status',
            'status_classval_id'
        );
    }

    $pdo->beginTransaction();

    try {
        $placeholderCode = 'TMP-' . bin2hex(random_bytes(16));

        $insert = $pdo->prepare(
            'INSERT INTO performance_routines (
                routine_code,
                team_id,
                year_id,
                routine_name,
                music_title,
                duration_seconds,
                notes,
                source_document,
                created_at,
                updated_at,
                choreography_type_id,
                status_classval_id
            ) VALUES (
                :routine_code,
                :team_id,
                :year_id,
                :routine_name,
                :music_title,
                :duration_seconds,
                :notes,
                :source_document,
                NOW(),
                NOW(),
                :choreography_type_id,
                :status_classval_id
            )'
        );

        $insert->execute([
            ':routine_code' => $placeholderCode,
            ':team_id' => $teamId,
            ':year_id' => $yearId,
            ':routine_name' => $routineName,
            ':music_title' => $musicTitle,
            ':duration_seconds' => $durationSeconds,
            ':notes' => $notes,
            ':source_document' => $sourceDocument,
            ':choreography_type_id' => $choreographyTypeId,
            ':status_classval_id' => $statusClassvalId,
        ]);

        $routineId = (int) $pdo->lastInsertId();
        $routineCode = 'ROUTINE-' . str_pad((string) $routineId, 3, '0', STR_PAD_LEFT);

        $update = $pdo->prepare(
            'UPDATE performance_routines
             SET routine_code = :routine_code
             WHERE routine_id = :routine_id'
        );

        $update->execute([
            ':routine_code' => $routineCode,
            ':routine_id' => $routineId,
        ]);

        $row = read_performance_routine($pdo, $routineId);

        $pdo->commit();

        return $row;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function resolve_performance_routine_year_id(PDO $pdo, int $yearValue): int
{
    $stmt = $pdo->prepare(
        'SELECT id
         FROM years
         WHERE year_value = :year_value
         LIMIT 1'
    );

    $stmt->execute([':year_value' => $yearValue]);
    $yearId = $stmt->fetchColumn();

    if ($yearId === false) {
        throw new RuntimeException('No matching years row exists for the supplied year_value');
    }

    return (int) $yearId;
}

function assert_performance_routine_entity_exists(PDO $pdo, string $entityId): void
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM entities
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([':id' => $entityId]);

    if ($stmt->fetchColumn() === false) {
        throw new RuntimeException('team_id does not resolve to entities.id');
    }
}

function assert_performance_routine_classval_type(
    PDO $pdo,
    string $classvalId,
    string $expectedTypeId,
    string $fieldName
): void {
    $stmt = $pdo->prepare(
        'SELECT classval_type_id
         FROM classvals
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([':id' => $classvalId]);
    $actualTypeId = $stmt->fetchColumn();

    if ($actualTypeId === false) {
        throw new RuntimeException($fieldName . ' does not resolve to classvals.id');
    }

    if ($actualTypeId !== $expectedTypeId) {
        throw new RuntimeException(
            $fieldName . ' must have classval_type_id = ' . $expectedTypeId
        );
    }
}

function read_performance_routine(PDO $pdo, int $routineId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            routine_id,
            routine_code,
            team_id,
            year_id,
            medley_id,
            routine_name,
            music_title,
            duration_seconds,
            notes,
            source_document,
            created_at,
            updated_at,
            choreography_type_id,
            status_classval_id
         FROM performance_routines
         WHERE routine_id = :routine_id'
    );

    $stmt->execute([':routine_id' => $routineId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        throw new RuntimeException('Created performance routine could not be read back');
    }

    return $row;
}