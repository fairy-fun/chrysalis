<?php

declare(strict_types=1);

function resolve_classval_type_id(
    PDO $pdo,
    string $typeCode
): string {
    if (trim($typeCode) === '') {
        throw new InvalidArgumentException(
            'typeCode is required'
        );
    }

    static $cache = [];

    if (isset($cache[$typeCode])) {
        return $cache[$typeCode];
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM classval_type_classvals
        WHERE code = :code
        LIMIT 1
    ");

    $stmt->execute([
        ':code' => $typeCode,
    ]);

    $id = $stmt->fetchColumn();

    if (!is_string($id) || trim($id) === '') {
        throw new RuntimeException(
            'Missing classval type: '
            . $typeCode
        );
    }

    $cache[$typeCode] = $id;

    return $id;
}

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

function resolve_classval_id_by_type_code(
    PDO $pdo,
    string $typeCode,
    string $code
): string {
    if (trim($typeCode) === '' || trim($code) === '') {
        throw new InvalidArgumentException(
            'typeCode and code are required'
        );
    }

    return resolve_classval_id(
        $pdo,
        resolve_classval_type_id($pdo, $typeCode),
        $code
    );
}