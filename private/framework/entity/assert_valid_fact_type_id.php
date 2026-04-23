<?php

declare(strict_types=1);

require_once __DIR__ . '/../classval/classval_domains.php';

function assert_valid_fact_type_id(PDO $pdo, string $factTypeId): void
{
    if ($factTypeId === '') {
        throw new InvalidArgumentException('Fact type id is required');
    }

    if ($factTypeId !== trim($factTypeId)) {
        throw new InvalidArgumentException('Fact type id must not contain surrounding whitespace');
    }

    $stmt = $pdo->prepare(
        'SELECT 1
           FROM classvals
          WHERE id = :fact_type_id
            AND classval_type_id = :classval_type_id
          LIMIT 1'
    );

    $stmt->execute([
        ':fact_type_id'     => $factTypeId,
        ':classval_type_id' => ClassvalDomains::ENTITY_LINKED_FACT_TYPE,
    ]);

    if ($stmt->fetchColumn() === false) {
        throw new RuntimeException('Unknown or invalid fact type id: ' . $factTypeId);
    }
}