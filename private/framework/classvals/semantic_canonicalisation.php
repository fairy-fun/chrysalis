<?php

declare(strict_types=1);

function loadClassvalSemanticCanonicalRules(): array
{
    $rulesPath = __DIR__ . '/semantic_canonical_rules.php';
    $rules = require $rulesPath;

    if (!is_array($rules)) {
        throw new RuntimeException('Invalid semantic canonical rules file');
    }

    return $rules;
}

function validateClassvalSemanticCanonicalRule(array $rule): void
{
    $name = $rule['name'] ?? null;
    $canonical = $rule['canonical'] ?? null;
    $deprecated = $rule['deprecated'] ?? null;

    if (!is_string($name) || $name === '') {
        throw new RuntimeException('Semantic canonical rule missing valid name');
    }

    if (!is_array($canonical)) {
        throw new RuntimeException("Semantic canonical rule '{$name}' missing canonical block");
    }

    if (!is_array($deprecated)) {
        throw new RuntimeException("Semantic canonical rule '{$name}' missing deprecated block");
    }

    $canonicalId = $canonical['id'] ?? null;
    $canonicalCode = $canonical['code'] ?? null;

    if (!is_string($canonicalId) || $canonicalId === '') {
        throw new RuntimeException("Semantic canonical rule '{$name}' missing canonical id");
    }

    if (!is_string($canonicalCode) || $canonicalCode === '') {
        throw new RuntimeException("Semantic canonical rule '{$name}' missing canonical code");
    }

    $deprecatedIds = $deprecated['ids'] ?? [];
    $deprecatedCodes = $deprecated['codes'] ?? [];

    if (!is_array($deprecatedIds)) {
        throw new RuntimeException("Semantic canonical rule '{$name}' has invalid deprecated ids");
    }

    if (!is_array($deprecatedCodes)) {
        throw new RuntimeException("Semantic canonical rule '{$name}' has invalid deprecated codes");
    }

    foreach ($deprecatedIds as $deprecatedId) {
        if (!is_string($deprecatedId) || $deprecatedId === '') {
            throw new RuntimeException("Semantic canonical rule '{$name}' contains invalid deprecated id");
        }
    }

    foreach ($deprecatedCodes as $deprecatedCode) {
        if (!is_string($deprecatedCode) || $deprecatedCode === '') {
            throw new RuntimeException("Semantic canonical rule '{$name}' contains invalid deprecated code");
        }
    }

    if ($deprecatedIds === [] && $deprecatedCodes === []) {
        throw new RuntimeException(
            "Semantic canonical rule '{$name}' must define at least one deprecated id or code"
        );
    }
}

function buildClassvalSemanticCanonicalQuery(array $rule): array
{
    validateClassvalSemanticCanonicalRule($rule);

    $deprecatedIds = array_values($rule['deprecated']['ids'] ?? []);
    $deprecatedCodes = array_values($rule['deprecated']['codes'] ?? []);

    $clauses = [];
    $params = [];

    foreach ($deprecatedIds as $index => $deprecatedId) {
        $param = ':deprecated_id_' . $index;
        $clauses[] = 'id = ' . $param;
        $params[$param] = $deprecatedId;
    }

    foreach ($deprecatedCodes as $index => $deprecatedCode) {
        $param = ':deprecated_code_' . $index;
        $clauses[] = 'code = ' . $param;
        $params[$param] = $deprecatedCode;
    }

    if ($clauses === []) {
        throw new RuntimeException(
            "Semantic canonical rule '{$rule['name']}' produced no query clauses"
        );
    }

    $sql = '
        SELECT id, code
        FROM classvals
        WHERE ' . implode(' OR ', $clauses) . '
        ORDER BY id ASC, code ASC
        LIMIT 1
    ';

    return [
        'sql' => $sql,
        'params' => $params,
    ];
}

function findFirstClassvalSemanticCanonicalViolation(PDO $pdo, array $rule): ?array
{
    $query = buildClassvalSemanticCanonicalQuery($rule);

    $stmt = $pdo->prepare($query['sql']);
    $stmt->execute($query['params']);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function assertClassvalSemanticCanonicalRule(PDO $pdo, array $rule): void
{
    validateClassvalSemanticCanonicalRule($rule);

    $match = findFirstClassvalSemanticCanonicalViolation($pdo, $rule);
    if ($match === null) {
        return;
    }

    $canonicalId = $rule['canonical']['id'];
    $canonicalCode = $rule['canonical']['code'];

    throw new RuntimeException(
        'Semantic duplicate detected for rule '
        . $rule['name']
        . ': use canonical '
        . $canonicalId
        . ' / '
        . $canonicalCode
        . ' instead'
    );
}

function assertAllClassvalSemanticCanonicalRules(PDO $pdo): void
{
    $rules = loadClassvalSemanticCanonicalRules();

    foreach ($rules as $index => $rule) {
        if (!is_array($rule)) {
            throw new RuntimeException('Invalid semantic canonical rule at index ' . $index);
        }

        assertClassvalSemanticCanonicalRule($pdo, $rule);
    }
}