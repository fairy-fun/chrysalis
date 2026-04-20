<?php

declare(strict_types=1);

/**
 * Classvals Trigger Rules (Chrysalis)
 */

function assert_identifier(string $name): void
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException("invalid identifier: {$name}");
    }
}

function assert_classvals_table_name(string $table): void
{
    assert_identifier($table);

    if (!str_ends_with($table, '_classvals')) {
        throw new InvalidArgumentException("table must end with _classvals");
    }
}

function quote_ident(string $name): string
{
    assert_identifier($name);
    return "`{$name}`";
}

function build_trigger_names(string $table): array
{
    $normalize = "trg_{$table}_code_normalize";
    $immutable = "trg_{$table}_code_immutable";

    if (strlen($normalize) > 64 || strlen($immutable) > 64) {
        throw new RuntimeException("trigger name too long for {$table}");
    }

    return [$normalize, $immutable];
}

function list_triggers(PDO $db, string $table): array
{
    $stmt = $db->prepare("
        SELECT TRIGGER_NAME, ACTION_TIMING, EVENT_MANIPULATION, ACTION_STATEMENT, DEFINER
        FROM information_schema.TRIGGERS
        WHERE TRIGGER_SCHEMA = DATABASE()
          AND EVENT_OBJECT_TABLE = ?
    ");
    $stmt->execute([$table]);

    return $stmt->fetchAll();
}

function canonical_sql(string $table): array
{
    [$normalize, $immutable] = build_trigger_names($table);
    $q = quote_ident($table);

    return [
        $normalize => "
CREATE DEFINER = CURRENT_USER TRIGGER {$normalize}
BEFORE INSERT ON {$q}
FOR EACH ROW
SET NEW.code = LOWER(NEW.code)
",
        $immutable => "
CREATE DEFINER = CURRENT_USER TRIGGER {$immutable}
BEFORE UPDATE ON {$q}
FOR EACH ROW
BEGIN
    IF NOT (NEW.code <=> OLD.code) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'code is immutable and cannot be changed';
    END IF;
END
"
    ];
}

function normalize_sql(string $sql): string
{
    return strtolower(preg_replace('/\s+/', ' ', trim($sql)));
}

function trigger_signature(array $row): string
{
    return hash('sha256', implode('|', [
        strtoupper($row['ACTION_TIMING']),
        strtoupper($row['EVENT_MANIPULATION']),
        normalize_sql($row['ACTION_STATEMENT']),
    ]));
}

function expected_signatures(string $table): array
{
    [$normalize, $immutable] = build_trigger_names($table);

    return [
        $normalize => hash('sha256', 'BEFORE|INSERT|' . normalize_sql("SET NEW.code = LOWER(NEW.code)")),
        $immutable => hash('sha256', 'BEFORE|UPDATE|' . normalize_sql("
            BEGIN
                IF NOT (NEW.code <=> OLD.code) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'code is immutable and cannot be changed';
                END IF;
            END
        "))
    ];
}

function inspect_classval_trigger_state(PDO $db, string $table): array
{
    assert_classvals_table_name($table);

    $rows = list_triggers($db, $table);
    $byName = [];

    foreach ($rows as $r) {
        $byName[$r['TRIGGER_NAME']] = $r;
    }

    [$normalize, $immutable] = build_trigger_names($table);
    $expected = expected_signatures($table);

    $missing = [];
    $drift = [];

    foreach ([$normalize, $immutable] as $name) {
        if (!isset($byName[$name])) {
            $missing[] = $name;
            continue;
        }

        if (trigger_signature($byName[$name]) !== $expected[$name]) {
            $drift[$name] = true;
        }
    }

    $status = 'ok';

    if (!empty($missing)) {
        $status = 'missing';
    } elseif (!empty($drift)) {
        $status = 'drift';
    }

    return [
        'table' => $table,
        'status' => $status,
        'missing' => $missing,
        'drift' => array_keys($drift),
        'actual_count' => count($rows),
    ];
}

function rebuild_classval_triggers(PDO $db, string $table, bool $strict = true): array
{
    $before = list_triggers($db, $table);
    [$normalize, $immutable] = build_trigger_names($table);
    $sql = canonical_sql($table);

    $actions = [];

    foreach ([$normalize, $immutable] as $name) {
        $db->exec("DROP TRIGGER IF EXISTS {$name}");
        $actions[] = "drop {$name}";
    }

    foreach ($sql as $name => $stmt) {
        $db->exec($stmt);
        $actions[] = "create {$name}";
    }

    $after = list_triggers($db, $table);

    $inspection = inspect_classval_trigger_state($db, $table);

    if ($strict && $inspection['status'] !== 'ok') {
        return [
            'table' => $table,
            'before' => $before,
            'after' => $after,
            'actions' => $actions,
            'ok' => false,
            'error' => 'strict validation failed',
            'inspection' => $inspection,
        ];
    }

    return [
        'table' => $table,
        'before' => $before,
        'after' => $after,
        'actions' => $actions,
        'ok' => true,
    ];
}