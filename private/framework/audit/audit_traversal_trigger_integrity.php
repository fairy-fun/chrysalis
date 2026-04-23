<?php

declare(strict_types=1);

function normalise_trigger_sql(string $sql): string
{
    $sql = trim($sql);
    $sql = preg_replace('/\s+/', ' ', $sql);

    return $sql ?? '';
}

function fetch_expected_traversal_trigger_snippets(PDO $pdo): array
{
    $sql = "
        SELECT
            s.name,
            s.sql_text
        FROM sql_snippets AS s
        WHERE s.name LIKE 'trg_entity_traversal_steps_%'
        ORDER BY s.name
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();

    $expected = [];

    foreach ($rows as $row) {
        $name = (string) ($row['name'] ?? '');
        $sqlText = (string) ($row['sql_text'] ?? '');

        if ($name === '') {
            continue;
        }

        $expected[$name] = [
            'name' => $name,
            'sql_text' => $sqlText,
            'sql_md5' => md5($sqlText),
            'normalised_sql_md5' => md5(normalise_trigger_sql($sqlText)),
        ];
    }

    return $expected;
}

function fetch_live_traversal_triggers(PDO $pdo, string $schemaName): array
{
    $sql = "
        SELECT
            t.TRIGGER_NAME,
            t.ACTION_STATEMENT
        FROM information_schema.TRIGGERS AS t
        WHERE t.TRIGGER_SCHEMA = :schema_name
          AND t.TRIGGER_NAME LIKE 'trg_entity_traversal_steps_%'
        ORDER BY t.TRIGGER_NAME
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':schema_name' => $schemaName,
    ]);

    $rows = $stmt->fetchAll();

    $live = [];

    foreach ($rows as $row) {
        $name = (string) ($row['TRIGGER_NAME'] ?? '');
        $actionStatement = (string) ($row['ACTION_STATEMENT'] ?? '');

        if ($name === '') {
            continue;
        }

        $live[$name] = [
            'name' => $name,
            'action_statement' => $actionStatement,
            'sql_md5' => md5($actionStatement),
            'normalised_sql_md5' => md5(normalise_trigger_sql($actionStatement)),
        ];
    }

    return $live;
}

function audit_traversal_trigger_integrity(PDO $pdo, string $schemaName): array
{
    $expected = fetch_expected_traversal_trigger_snippets($pdo);
    $live = fetch_live_traversal_triggers($pdo, $schemaName);

    $violations = [];

    foreach ($expected as $triggerName => $expectedTrigger) {
        $liveTrigger = $live[$triggerName] ?? null;

        if ($liveTrigger === null) {
            $violations[] = [
                'trigger_name' => $triggerName,
                'violation_code' => 'missing_trigger',
                'expected_sql_md5' => $expectedTrigger['sql_md5'],
                'actual_sql_md5' => null,
                'expected_normalised_sql_md5' => $expectedTrigger['normalised_sql_md5'],
                'actual_normalised_sql_md5' => null,
            ];
            continue;
        }

        if ($liveTrigger['sql_md5'] !== $expectedTrigger['sql_md5']) {
            $violations[] = [
                'trigger_name' => $triggerName,
                'violation_code' => 'sql_mismatch',
                'expected_sql_md5' => $expectedTrigger['sql_md5'],
                'actual_sql_md5' => $liveTrigger['sql_md5'],
                'expected_normalised_sql_md5' => $expectedTrigger['normalised_sql_md5'],
                'actual_normalised_sql_md5' => $liveTrigger['normalised_sql_md5'],
            ];
        }
    }

    return [
        'ok' => count($violations) === 0,
        'schema_name' => $schemaName,
        'expected_trigger_count' => count($expected),
        'live_trigger_count' => count($live),
        'violations' => $violations,
    ];
}

function assert_traversal_trigger_integrity(PDO $pdo, string $schemaName): void
{
    $audit = audit_traversal_trigger_integrity($pdo, $schemaName);

    if ($audit['ok'] === true) {
        return;
    }

    throw new RuntimeException('Traversal trigger integrity validation failed.');
}