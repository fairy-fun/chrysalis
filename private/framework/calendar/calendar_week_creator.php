<?php
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