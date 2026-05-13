<?php

declare(strict_types=1);

function resolve_classval_id(
    PDO $pdo,
    string $classvalTypeId,
    string $code
): string {
    if (trim($classvalTypeId) === '' || trim($code) === '') {
        throw new InvalidArgumentException(
            'classvalTypeId and code are required'
        );
    }

    static $cache = [];

    $key = json_encode([$classvalTypeId, $code], JSON_THROW_ON_ERROR);

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM classvals
        WHERE classval_type_id = :classval_type_id
          AND code = :code
        LIMIT 1
    ");

    $stmt->execute([
        ':classval_type_id' => $classvalTypeId,
        ':code' => $code,
    ]);

    $id = $stmt->fetchColumn();

    if (!is_string($id) || trim($id) === '') {
        throw new RuntimeException(
            'Missing classval: '
            . $classvalTypeId
            . ' / '
            . $code
        );
    }

    $cache[$key] = $id;

    return $id;
}