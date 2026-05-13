<?php

declare(strict_types=1);

require_once __DIR__
    . '/../classvals/classval_resolver.php';

function governance_classval_id(
    PDO $pdo,
    string $classvalTypeCode,
    string $code
): string {

    $allowedClassvalTypes = [
        GovernanceDomains::EPISTEMIC_ORIGIN,
        GovernanceDomains::ADJUDICATION_STATUS,
        GovernanceDomains::CONTRADICTION_STATE,
    ];

    if (!in_array($classvalTypeCode, $allowedClassvalTypes, true)) {
        throw new InvalidArgumentException(
            'Unsupported governance classval type: '
            . $classvalTypeCode
        );
    }

    return resolve_classval_id_by_type_code(
        $pdo,
        $classvalTypeCode,
        $code
    );
}
