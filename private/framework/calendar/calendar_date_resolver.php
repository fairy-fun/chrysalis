<?php

declare(strict_types=1);

function resolve_calendar_date(PDO $pdo, string $dateEntityId): string
{
    $stmt = $pdo->prepare("
        SELECT date_value
        FROM dates
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $dateEntityId,
    ]);

    $dateValue = $stmt->fetchColumn();

    if ($dateValue === false || $dateValue === null || $dateValue === '') {
        throw new RuntimeException(
            "Unresolvable calendar date entity: {$dateEntityId}"
        );
    }

    return (string)$dateValue;
}

function resolve_calendar_datetime(PDO $pdo, ?string $dateEntityId): ?string
{
    if ($dateEntityId === null || trim($dateEntityId) === '') {
        return null;
    }

    return resolve_calendar_date($pdo, $dateEntityId) . ' 00:00:00';
}