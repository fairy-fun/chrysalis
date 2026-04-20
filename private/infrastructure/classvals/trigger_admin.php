<?php
declare(strict_types=1);

function assert_valid_classvals_table(string $table): void
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        throw new InvalidArgumentException("invalid table name");
    }
    if (!str_ends_with($table, '_classvals')) {
        throw new InvalidArgumentException("table must end with _classvals");
    }
}

function qident(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function normalize_classval_code(?string $code): ?string
{
    return $code === null ? null : mb_strtolower($code, 'UTF-8');
}

function canonical_trigger_names(string $table): array
{
    return [
        'normalize' => "trg_{$table}_code_normalize",
        'immutable' => "trg_{$table}_code_immutable",
    ];
}

function canonical_trigger_specs(string $table, ?string $expectedDefiner = null): array
{
    $names = canonical_trigger_names($table);

    return [
        $names['normalize'] => [
            'name' => $names['normalize'],
            'timing' => 'BEFORE',
            'event' => 'INSERT',
            'statement' => "SET NEW.code = LOWER(NEW.code)",
            'signature' => trigger_signature('BEFORE', 'INSERT', "SET NEW.code = LOWER(NEW.code)"),
            'expected_definer' => $expectedDefiner,
            'sql' => "
                CREATE DEFINER = CURRENT_USER TRIGGER `{$names['normalize']}`
                BEFORE INSERT ON " . qident($table) . "
                FOR EACH ROW
                SET NEW.code = LOWER(NEW.code)
            ",
        ],
        $names['immutable'] => [
            'name' => $names['immutable'],
            'timing' => 'BEFORE',
            'event' => 'UPDATE',
            'statement' => "
                BEGIN
                    IF NOT (NEW.code <=> OLD.code) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'code is immutable and cannot be changed';
                    END IF;
                END
            ",
            'signature' => trigger_signature(
                'BEFORE',
                'UPDATE',
                "
                BEGIN
                    IF NOT (NEW.code <=> OLD.code) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'code is immutable and cannot be changed';
                    END IF;
                END
                "
            ),
            'expected_definer' => $expectedDefiner,
            'sql' => "
                CREATE DEFINER = CURRENT_USER TRIGGER `{$names['immutable']}`
                BEFORE UPDATE ON " . qident($table) . "
                FOR EACH ROW
                BEGIN
                    IF NOT (NEW.code <=> OLD.code) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'code is immutable and cannot be changed';
                    END IF;
                END
            ",
        ],
    ];
}

function normalized_sql_fragment(string $sql): string
{
    $sql = trim($sql);
    $sql = preg_replace('/\s+/', ' ', $sql) ?? $sql;
    $sql = preg_replace('/\s*;\s*$/', '', $sql) ?? $sql;
    return trim($sql);
}

function trigger_signature(string $timing, string $event, string $actionStatement): string
{
    $payload = strtoupper(trim($timing))
        . '|'
        . strtoupper(trim($event))
        . '|'
        . normalized_sql_fragment($actionStatement);

    return hash('sha256', $payload);
}

function current_db_name(PDO $db): string
{
    return (string)$db->query('SELECT DATABASE()')->fetchColumn();
}

function current_account(PDO $db): string
{
    return (string)$db->query('SELECT CURRENT_USER()')->fetchColumn();
}

function table_has_code_column(PDO $db, string $table): bool
{
    $sql = "
        SELECT COUNT(*) AS c
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = 'code'
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function list_classvals_tables(PDO $db): array
{
    $sql = "
        SELECT t.table_name
        FROM information_schema.tables t
        WHERE t.table_schema = DATABASE()
          AND t.table_type = 'BASE TABLE'
          AND t.table_name LIKE '%\\_classvals'
          AND EXISTS (
              SELECT 1
              FROM information_schema.columns c
              WHERE c.table_schema = t.table_schema
                AND c.table_name = t.table_name
                AND c.column_name = 'code'
          )
        ORDER BY t.table_name
    ";

    $stmt = $db->query($sql);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

    return array_map('strval', $rows ?: []);
}

function fetch_table_triggers(PDO $db, string $table): array
{
    $sql = "
        SELECT
            trigger_name,
            action_timing,
            event_manipulation,
            action_statement,
            definer,
            action_order
        FROM information_schema.triggers
        WHERE trigger_schema = DATABASE()
          AND event_object_table = ?
        ORDER BY action_timing, event_manipulation, action_order, trigger_name
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$table]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $row['signature'] = trigger_signature(
            (string)$row['action_timing'],
            (string)$row['event_manipulation'],
            (string)$row['action_statement']
        );
    }

    return $rows;
}

function inspect_classval_trigger_state(
    PDO $db,
    string $table,
    bool $hardStrict = false,
    ?string $expectedDefiner = null
): array {
    assert_valid_classvals_table($table);

    if (!table_has_code_column($db, $table)) {
        return [
            'table' => $table,
            'status' => 'invalid',
            'ok' => false,
            'details' => [
                'missing' => [],
                'wrong_definition' => [],
                'unexpected_relevant' => [],
                'definers' => [],
                'errors' => ['missing code column'],
            ],
        ];
    }

    $expectedDefiner ??= current_account($db);
    $triggers = fetch_table_triggers($db, $table);
    $byName = [];
    foreach ($triggers as $t) {
        $byName[$t['trigger_name']] = $t;
    }

    $canonical = canonical_trigger_specs($table, $expectedDefiner);

    $missing = [];
    $wrongDefinition = [];
    $unexpectedRelevant = [];
    $definers = [];
    $errors = [];

    foreach ($canonical as $name => $spec) {
        if (!isset($byName[$name])) {
            $missing[] = $name;
            continue;
        }

        $actual = $byName[$name];
        $definers[$name] = $actual['definer'];

        $mismatches = [];
        if (strtoupper((string)$actual['action_timing']) !== $spec['timing']) {
            $mismatches['timing'] = [
                'expected' => $spec['timing'],
                'actual' => $actual['action_timing'],
            ];
        }
        if (strtoupper((string)$actual['event_manipulation']) !== $spec['event']) {
            $mismatches['event'] = [
                'expected' => $spec['event'],
                'actual' => $actual['event_manipulation'],
            ];
        }
        if ((string)$actual['signature'] !== $spec['signature']) {
            $mismatches['signature'] = [
                'expected' => $spec['signature'],
                'actual' => $actual['signature'],
                'expected_statement' => normalized_sql_fragment($spec['statement']),
                'actual_statement' => normalized_sql_fragment((string)$actual['action_statement']),
            ];
        }
        if ($expectedDefiner !== null && (string)$actual['definer'] !== $expectedDefiner) {
            $mismatches['definer'] = [
                'expected' => $expectedDefiner,
                'actual' => $actual['definer'],
            ];
        }

        if ($mismatches) {
            $wrongDefinition[$name] = $mismatches;
        }
    }

    $canonicalNames = array_keys($canonical);
    foreach ($triggers as $t) {
        $isRelevant =
            strtoupper((string)$t['action_timing']) === 'BEFORE'
            && in_array(strtoupper((string)$t['event_manipulation']), ['INSERT', 'UPDATE'], true);

        if ($isRelevant && !in_array((string)$t['trigger_name'], $canonicalNames, true)) {
            $unexpectedRelevant[] = [
                'trigger_name' => $t['trigger_name'],
                'timing' => $t['action_timing'],
                'event' => $t['event_manipulation'],
                'definer' => $t['definer'],
                'signature' => $t['signature'],
            ];
        }
    }

    $status = 'ok';
    if ($missing) {
        $status = 'missing';
    }
    if ($wrongDefinition || $unexpectedRelevant) {
        $status = 'drift';
    }

    if ($hardStrict) {
        if (count($triggers) !== 2) {
            $errors[] = 'trigger count is not exactly 2';
        }
        if ($unexpectedRelevant) {
            $errors[] = 'extra relevant BEFORE INSERT/UPDATE triggers exist';
        }

        $actualNames = array_map(fn(array $t) => $t['trigger_name'], $triggers);
        sort($actualNames);
        $expectedNames = $canonicalNames;
        sort($expectedNames);

        if ($actualNames !== $expectedNames) {
            $errors[] = 'trigger names are not exactly canonical';
        }

        if ($missing || $wrongDefinition) {
            $errors[] = 'final state is not exactly canonical';
        }
    }

    $ok = empty($missing) && empty($wrongDefinition) && empty($unexpectedRelevant) && empty($errors);

    return [
        'table' => $table,
        'status' => $ok ? 'ok' : $status,
        'ok' => $ok,
        'details' => [
            'missing' => $missing,
            'wrong_definition' => $wrongDefinition,
            'unexpected_relevant' => $unexpectedRelevant,
            'definers' => $definers,
            'errors' => $errors,
        ],
        'triggers' => $triggers,
    ];
}

function ensure_classvals_trigger_audit_table(PDO $db): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS classvals_trigger_audit (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_uuid CHAR(36) NOT NULL,
            table_name VARCHAR(255) NOT NULL,
            action_type ENUM('check','repair','repair_failed') NOT NULL,
            strict_mode TINYINT(1) NOT NULL DEFAULT 1,
            hard_strict_mode TINYINT(1) NOT NULL DEFAULT 1,
            expected_definer VARCHAR(255) NULL,
            before_status VARCHAR(32) NOT NULL,
            after_status VARCHAR(32) NULL,
            before_signature_json LONGTEXT NULL,
            after_signature_json LONGTEXT NULL,
            actions_json LONGTEXT NULL,
            error_text TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cta_table_created (table_name, created_at),
            KEY idx_cta_event_uuid (event_uuid),
            KEY idx_cta_action_type_created (action_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $db->exec($sql);
}

function trigger_signature_map(array $inspection): array
{
    $out = [];
    foreach (($inspection['triggers'] ?? []) as $t) {
        $out[$t['trigger_name']] = [
            'timing' => $t['action_timing'],
            'event' => $t['event_manipulation'],
            'definer' => $t['definer'],
            'signature' => $t['signature'],
        ];
    }
    ksort($out);
    return $out;
}

function write_classvals_trigger_audit(
    PDO $db,
    string $eventUuid,
    string $table,
    string $actionType,
    bool $strictMode,
    bool $hardStrictMode,
    ?string $expectedDefiner,
    array $before,
    ?array $after,
    array $actions,
    ?string $errorText
): void {
    ensure_classvals_trigger_audit_table($db);

    $sql = "
        INSERT INTO classvals_trigger_audit (
            event_uuid,
            table_name,
            action_type,
            strict_mode,
            hard_strict_mode,
            expected_definer,
            before_status,
            after_status,
            before_signature_json,
            after_signature_json,
            actions_json,
            error_text
        ) VALUES (
            :event_uuid,
            :table_name,
            :action_type,
            :strict_mode,
            :hard_strict_mode,
            :expected_definer,
            :before_status,
            :after_status,
            :before_signature_json,
            :after_signature_json,
            :actions_json,
            :error_text
        )
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':event_uuid' => $eventUuid,
        ':table_name' => $table,
        ':action_type' => $actionType,
        ':strict_mode' => $strictMode ? 1 : 0,
        ':hard_strict_mode' => $hardStrictMode ? 1 : 0,
        ':expected_definer' => $expectedDefiner,
        ':before_status' => (string)($before['status'] ?? 'unknown'),
        ':after_status' => $after ? (string)($after['status'] ?? 'unknown') : null,
        ':before_signature_json' => json_encode(trigger_signature_map($before), JSON_UNESCAPED_SLASHES),
        ':after_signature_json' => $after ? json_encode(trigger_signature_map($after), JSON_UNESCAPED_SLASHES) : null,
        ':actions_json' => json_encode($actions, JSON_UNESCAPED_SLASHES),
        ':error_text' => $errorText,
    ]);
}

function rebuild_classval_triggers(
    PDO $db,
    string $table,
    bool $strict = true,
    bool $hardStrict = true,
    ?string $expectedDefiner = null,
    ?string $eventUuid = null
): array {
    assert_valid_classvals_table($table);

    if (!table_has_code_column($db, $table)) {
        return [
            'table' => $table,
            'ok' => false,
            'errors' => ['missing code column'],
        ];
    }

    $eventUuid ??= function_exists('uuid_create')
        ? uuid_create(UUID_TYPE_RANDOM)
        : bin2hex(random_bytes(16));

    $expectedDefiner ??= current_account($db);
    $actions = [];

    $before = inspect_classval_trigger_state($db, $table, $hardStrict, $expectedDefiner);
    $canonical = canonical_trigger_specs($table, $expectedDefiner);

    try {
        $db->beginTransaction();

        foreach (canonical_trigger_names($table) as $triggerName) {
            $db->exec("DROP TRIGGER IF EXISTS `" . str_replace('`', '``', $triggerName) . "`");
            $actions[] = ['drop' => $triggerName];
        }

        foreach ($canonical as $name => $spec) {
            $db->exec($spec['sql']);
            $actions[] = ['create' => $name];
        }

        $db->commit();

        $after = inspect_classval_trigger_state($db, $table, $hardStrict, $expectedDefiner);

        $errors = [];
        if ($strict && !$after['ok']) {
            $errors[] = 'final state is not exactly canonical';
        }

        write_classvals_trigger_audit(
            $db,
            $eventUuid,
            $table,
            'repair',
            $strict,
            $hardStrict,
            $expectedDefiner,
            $before,
            $after,
            $actions,
            $errors ? implode('; ', $errors) : null
        );

        return [
            'table' => $table,
            'ok' => empty($errors),
            'strict' => $strict,
            'hard_strict' => $hardStrict,
            'expected_definer' => $expectedDefiner,
            'before' => $before,
            'after' => $after,
            'actions' => $actions,
            'errors' => $errors,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $after = inspect_classval_trigger_state($db, $table, false, $expectedDefiner);

        write_classvals_trigger_audit(
            $db,
            $eventUuid,
            $table,
            'repair_failed',
            $strict,
            $hardStrict,
            $expectedDefiner,
            $before,
            $after,
            $actions,
            $e->getMessage()
        );

        return [
            'table' => $table,
            'ok' => false,
            'strict' => $strict,
            'hard_strict' => $hardStrict,
            'expected_definer' => $expectedDefiner,
            'before' => $before,
            'after' => $after,
            'actions' => $actions,
            'errors' => [$e->getMessage()],
        ];
    }
}