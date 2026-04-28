<?php


declare(strict_types=1);

function run_expression_constraint_engine(PDO $pdo, int $constraintRunId, array $winners): array
{
    if ($constraintRunId <= 0) {
        throw new InvalidArgumentException('constraint_run_id must be positive');
    }

    $state = [];

    foreach ($winners as $winner) {
        $attributeTypeId = trim((string)($winner['attribute_type_id'] ?? ''));
        $valueSource = trim((string)($winner['value_source'] ?? ''));
        $value = trim((string)($winner['value'] ?? ''));

        if ($attributeTypeId === '') {
            throw new RuntimeException('Expression winner is missing attribute_type_id');
        }

        if ($valueSource !== 'classval') {
            continue;
        }

        if ($value === '') {
            continue;
        }

        $state[$attributeTypeId] = [
            'attribute_type_id' => $attributeTypeId,
            'input_value_classval_id' => $value,
            'current_value_classval_id' => $value,
        ];
    }

    foreach (read_expression_constraint_phases($pdo) as $phase) {
        $phaseId = (string)$phase['id'];

        foreach (read_expression_constraint_effects($pdo, $phaseId) as $effect) {
            $attributeTypeId = (string)$effect['attribute_type_id'];

            if (!isset($state[$attributeTypeId])) {
                continue;
            }

            $outputValue = trim((string)($effect['output_value_classval_id'] ?? ''));

            if ($outputValue !== '') {
                $state[$attributeTypeId]['current_value_classval_id'] = $outputValue;
            }
        }
    }

    $outputs = [];

    foreach ($state as $row) {
        $outputs[] = [
            'constraint_run_id' => $constraintRunId,
            'attribute_type_id' => $row['attribute_type_id'],
            'input_value_classval_id' => $row['input_value_classval_id'],
            'output_value_classval_id' => $row['current_value_classval_id'],
            'constraint_effect_type_classval_id' => null,
            'constraint_strength_classval_id' => null,
            'access_state_classval_id' => null,
        ];
    }

    persist_expression_constraint_outputs($pdo, $constraintRunId, $outputs);

    return $outputs;
}

function read_expression_constraint_phases(PDO $pdo): array
{
    $stmt = $pdo->query(<<<'SQL'
SELECT id, code
FROM sxnzlfun_chrysalis.framework_rule_phase_classvals
ORDER BY
    CASE code
        WHEN 'suppression' THEN 1
        WHEN 'reinterpretation' THEN 2
        WHEN 'identity_pressure' THEN 3
        ELSE 100
    END,
    id ASC
SQL
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function read_expression_constraint_effects(PDO $pdo, string $phaseId): array
{
    $phaseId = trim($phaseId);

    if ($phaseId === '') {
        throw new InvalidArgumentException('phase_id must be a non-empty string');
    }

    $stmt = $pdo->prepare(<<<'SQL'
SELECT
    e.attribute_type_id,
    e.output_value_classval_id,
    r.priority
FROM sxnzlfun_chrysalis.expression_constraint_rule_effects e
JOIN sxnzlfun_chrysalis.expression_constraint_rules r
    ON r.id = e.constraint_rule_id
WHERE r.rule_phase_id = :phase_id
ORDER BY r.priority ASC, e.id ASC
SQL
    );

    $stmt->execute([
        'phase_id' => $phaseId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function persist_expression_constraint_outputs(PDO $pdo, int $constraintRunId, array $outputs): void
{
    $delete = $pdo->prepare(<<<'SQL'
DELETE FROM sxnzlfun_chrysalis.expression_constraint_outputs
WHERE constraint_run_id = :constraint_run_id
SQL
    );

    $delete->execute([
        'constraint_run_id' => $constraintRunId,
    ]);

    if ($outputs === []) {
        return;
    }

    $insert = $pdo->prepare(<<<'SQL'
INSERT INTO sxnzlfun_chrysalis.expression_constraint_outputs (
    constraint_run_id,
    attribute_type_id,
    input_value_classval_id,
    output_value_classval_id,
    constraint_effect_type_classval_id,
    constraint_strength_classval_id,
    access_state_classval_id
)
VALUES (
    :constraint_run_id,
    :attribute_type_id,
    :input_value_classval_id,
    :output_value_classval_id,
    :constraint_effect_type_classval_id,
    :constraint_strength_classval_id,
    :access_state_classval_id
)
SQL
    );

    foreach ($outputs as $output) {
        $insert->execute([
            'constraint_run_id' => $constraintRunId,
            'attribute_type_id' => $output['attribute_type_id'],
            'input_value_classval_id' => $output['input_value_classval_id'],
            'output_value_classval_id' => $output['output_value_classval_id'],
            'constraint_effect_type_classval_id' => $output['constraint_effect_type_classval_id'],
            'constraint_strength_classval_id' => $output['constraint_strength_classval_id'],
            'access_state_classval_id' => $output['access_state_classval_id'],
        ]);
    }
}