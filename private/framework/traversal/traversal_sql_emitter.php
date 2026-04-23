<?php


declare(strict_types=1);

function emit_entity_traversal_sql(array $plan): string
{
    $pathId = (int)($plan['path']['path_id'] ?? 0);
    if ($pathId < 1) {
        throw new InvalidArgumentException('path_id must be a positive integer');
    }

    $rootTable = require_non_empty_identifier($plan['root']['table'] ?? null, 'root table');
    $rootAlias = require_non_empty_identifier($plan['root']['alias'] ?? null, 'root alias');

    $selectParts = emit_entity_traversal_select_parts($plan);
    $scoreSql = emit_entity_traversal_score_sql($plan);

    $sql = "SELECT\n    "
        . implode(",\n    ", $selectParts)
        . ",\n    " . $scoreSql . " AS score\n"
        . "FROM sxnzlfun_chrysalis." . $rootTable . " " . $rootAlias . "\n"
        . "JOIN sxnzlfun_chrysalis.entity_traversal_paths p\n"
        . "    ON p.id = " . $pathId . "\n"
        . emit_entity_traversal_join_sql($plan)
        . "WHERE p.id = " . $pathId . "\n"
        . "ORDER BY score DESC";

    return $sql;
}

function emit_entity_traversal_select_parts(array $plan): array
{
    $parts = [];

    foreach (($plan['projections'] ?? []) as $projection) {
        $table = require_non_empty_identifier($projection['source_table_name'] ?? null, 'projection source table');
        $column = require_non_empty_identifier($projection['source_column_name'] ?? null, 'projection source column');
        $output = require_non_empty_identifier($projection['output_name'] ?? null, 'projection output name');

        $alias = latest_alias_for_table($plan['aliases'], $table);

        $parts[] = $alias . '.' . $column . ' AS ' . $output;
    }

    if ($parts === []) {
        throw new RuntimeException('No projections resolved');
    }

    return $parts;
}

function emit_entity_traversal_score_sql(array $plan): string
{
    $terms = [];

    foreach (($plan['ordering'] ?? []) as $ordering) {
        $table = require_non_empty_identifier($ordering['source_table_name'] ?? null, 'ordering source table');
        $column = require_non_empty_identifier($ordering['column_name'] ?? null, 'ordering column');

        $alias = latest_alias_for_table($plan['aliases'], $table);

        $terms[] = 'COALESCE(' . $alias . '.' . $column . ', 0)';
    }

    $orderScore = $terms === []
        ? '0'
        : implode(' + ', $terms);

    return '(p.priority * 1000) + (' . $orderScore . ')';
}

function emit_entity_traversal_join_sql(array $plan): string
{
    $sql = '';

    foreach (($plan['joins'] ?? []) as $join) {
        $type = (string)($join['type'] ?? '');
        if (!in_array($type, ['JOIN', 'LEFT JOIN'], true)) {
            throw new RuntimeException('Invalid join type: ' . $type);
        }

        $table = require_non_empty_identifier($join['table'] ?? null, 'join table');
        $alias = require_non_empty_identifier($join['alias'] ?? null, 'join alias');
        $leftAlias = require_non_empty_identifier($join['left_alias'] ?? null, 'join left alias');
        $fromColumn = require_non_empty_identifier($join['from_column'] ?? null, 'join from column');
        $toColumn = require_non_empty_identifier($join['to_column'] ?? null, 'join to column');

        $sql .= $type . ' sxnzlfun_chrysalis.' . $table . ' ' . $alias . "\n";
        $sql .= '    ON ' . $leftAlias . '.' . $fromColumn . ' = ' . $alias . '.' . $toColumn . "\n";
    }

    return $sql;
}

function require_non_empty_identifier(mixed $value, string $label): string
{
    if (!is_string($value) || trim($value) === '') {
        throw new RuntimeException('Missing ' . $label);
    }

    $value = trim($value);

    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
        throw new RuntimeException('Invalid ' . $label . ': ' . $value);
    }

    return $value;
}