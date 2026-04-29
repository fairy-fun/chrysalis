<?php

declare(strict_types=1);

function validate_real_date_start_id_exists(PDO $pdo, string $realDateStartId): void
{
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM sxnzlfun_chrysalis.dates
         WHERE id = :id
         LIMIT 1"
    );

    $stmt->execute([
        ':id' => $realDateStartId,
    ]);

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

