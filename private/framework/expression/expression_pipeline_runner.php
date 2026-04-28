<?php


declare(strict_types=1);

require_once __DIR__ . '/expression_output_resolver.php';
require_once __DIR__ . '/expression_constraint_engine.php';

function run_expression_pipeline(
    PDO     $pdo,
    string  $characterId,
    string  $contextEntityId,
    ?string $asOfTs = null
): array
{
    $characterId = trim($characterId);
    $contextEntityId = trim($contextEntityId);

    if ($characterId === '') {
        throw new InvalidArgumentException('character_id must be non-empty');
    }

    if ($contextEntityId === '') {
        throw new InvalidArgumentException('context_entity_id must be non-empty');
    }

    // =========================================
    // STEP 1 — create constraint run
    // =========================================

    $resolutionRunId = generate_uuid_v4();

    $insert = $pdo->prepare(<<<'SQL'
INSERT INTO sxnzlfun_chrysalis.expression_constraint_runs (
    resolution_run_id,
    character_id,
    context_entity_id,
    created_at
)
VALUES (
    :resolution_run_id,
    :character_id,
    :context_entity_id,
    NOW()
)
SQL
    );

    $insert->execute([
        'resolution_run_id' => $resolutionRunId,
        'character_id' => $characterId,
        'context_entity_id' => $contextEntityId,
    ]);

    $constraintRunId = (int)$pdo->lastInsertId();

    // =========================================
    // STEP 2 — resolve expression output
    // =========================================

    $resolved = resolve_character_expression_output(
        $pdo,
        $characterId,
        null // domain optional — match DB behaviour for now
    );

    $winners = array_values($resolved['winners'] ?? []);

    // =========================================
    // STEP 3 — run constraint engine (PHP)
    // =========================================

    $outputs = run_expression_constraint_engine(
        $pdo,
        $constraintRunId,
        $winners
    );

    // =========================================
    // STEP 4 — return final shape
    // =========================================

    return [
        'constraint_run_id' => $constraintRunId,
        'resolution_run_id' => $resolutionRunId,
        'character_id' => $characterId,
        'context_entity_id' => $contextEntityId,
        'outputs' => $outputs,
    ];
}

function generate_uuid_v4(): string
{
    $data = random_bytes(16);

    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}