<?php

declare(strict_types=1);

function governance_classval_id(
    PDO $pdo,
    string $classvalTypeId,
    string $code
): string {

    $allowedClassvalTypes = [
        GovernanceDomains::EPISTEMIC_ORIGIN,
        GovernanceDomains::ADJUDICATION_STATUS,
        GovernanceDomains::CONTRADICTION_STATE,
    ];

    if (!in_array($classvalTypeId, $allowedClassvalTypes, true)) {
        throw new InvalidArgumentException(
            'Unsupported governance classval type: '
            . $classvalTypeId
        );
    }

    static $cache = [];

    $key = $classvalTypeId . ':' . $code;

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
            'Missing governance classval: '
            . $classvalTypeId . ' / ' . $code
        );
    }

    $cache[$key] = $id;

    return $id;
}