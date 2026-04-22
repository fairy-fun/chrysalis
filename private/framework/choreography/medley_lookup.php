<?php

declare(strict_types=1);

function resolve_medley_id(PDO $pdo, mixed $rawMedleyId, mixed $rawMedleyName): int
{
    $hasMedleyId = $rawMedleyId !== null;
    $hasMedleyName = $rawMedleyName !== null;

    if ($hasMedleyId && $hasMedleyName) {
        throw new InvalidArgumentException('Provide exactly one of medley_id or medley_name');
    }

    if (!$hasMedleyId && !$hasMedleyName) {
        throw new InvalidArgumentException('Missing medley_id or medley_name');
    }

    if ($hasMedleyId) {
        if (!is_int($rawMedleyId) || $rawMedleyId < 1) {
            throw new InvalidArgumentException('medley_id must be a positive integer');
        }

        return $rawMedleyId;
    }

    if (!is_string($rawMedleyName)) {
        throw new InvalidArgumentException('medley_name must be a non-empty string');
    }

    $medleyName = trim($rawMedleyName);
    if ($medleyName === '') {
        throw new InvalidArgumentException('medley_name must be a non-empty string');
    }

    $sql = <<<'SQL'
    SELECT id
    FROM medleys
    WHERE search_name = :search_name
    LIMIT 1
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':search_name', $medleyName, PDO::PARAM_STR);
    $stmt->execute();

    $resolvedId = $stmt->fetchColumn();

    if ($resolvedId === false) {
        throw new RuntimeException('Medley not found by name');
    }

    return (int) $resolvedId;
}