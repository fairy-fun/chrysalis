<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_node_ensurer.php';

function validate_real_date_start_id_exists(PDO $pdo, string $realDateStartId): void
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM sxnzlfun_chrysalis.dates
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $realDateStartId]);

    if ($stmt->fetchColumn() === false) {
        throw new RuntimeException(
            'Invalid real_date_start_id: no matching dates.id = ' . $realDateStartId
        );
    }
}

function resolve_book_projection_entity_id(string $bookCode): string
{
    $bookCode = trim($bookCode);

    if ($bookCode === '') {
        throw new InvalidArgumentException('book_code must be non-empty');
    }

    return 'book_projection_' . $bookCode;
}

function create_calendar_week_for_book(
    PDO $pdo,
    string $bookCode,
    int $weekIndex,
    string $weekLabel,
    string $realDateStartId
): array {
    $bookCode = trim($bookCode);
    $weekLabel = trim($weekLabel);
    $realDateStartId = trim($realDateStartId);

    if ($weekIndex < 1) {
        throw new InvalidArgumentException('week_index must be positive');
    }

    if ($weekLabel === '') {
        throw new InvalidArgumentException('week_label must be non-empty');
    }

    if ($realDateStartId === '') {
        throw new InvalidArgumentException('real_date_start_id must be non-empty');
    }

    validate_real_date_start_id_exists($pdo, $realDateStartId);

    return ensure_calendar_node(
        $pdo,
        resolve_book_projection_entity_id($bookCode),
        'calendar_layer_week',
        null,
        $weekIndex,
        [
            'summary' => $weekLabel,
            'real_date_start_id' => $realDateStartId,
        ]
    );
}