<?php


declare(strict_types=1);

function build_entity_traversal_plan(array $definition): array
{
    $path = $definition['path'];

    $rootTable = (string)$path['root_table_name'];
    if ($rootTable === '') {
        throw new RuntimeException('Root table not resolved');
    }

    $aliases = [];
    $tableCounts = [];

    $rootAlias = make_entity_traversal_alias($rootTable, $tableCounts);
    $aliases[$rootTable][] = $rootAlias;

    $joins = [];

    foreach ($definition['steps'] as $step) {
        $leftTable = (string)$step['left_table_name'];
        $viaTable = (string)$step['via_table'];

        $leftAlias = latest_alias_for_table($aliases, $leftTable);
        $viaAlias = make_entity_traversal_alias($viaTable, $tableCounts);

        $aliases[$viaTable][] = $viaAlias;

        $joins[] = [
            'type' => ((int)$step['is_optional'] === 1) ? 'LEFT JOIN' : 'JOIN',
            'table' => $viaTable,
            'alias' => $viaAlias,
            'left_alias' => $leftAlias,
            'from_column' => (string)$step['from_column'],
            'to_column' => (string)$step['to_column'],
        ];
    }

    return [
        'root' => [
            'table' => $rootTable,
            'alias' => $rootAlias,
        ],
        'aliases' => $aliases,
        'joins' => $joins,
        'projections' => $definition['projections'],
        'ordering' => $definition['ordering'],
        'path' => $path,
    ];
}

function latest_alias_for_table(array $aliases, string $table): string
{
    if (!isset($aliases[$table]) || $aliases[$table] === []) {
        throw new RuntimeException('Left alias not found for table: ' . $table);
    }

    return $aliases[$table][array_key_last($aliases[$table])];
}

function make_entity_traversal_alias(string $table, array &$tableCounts): string
{
    $tableCounts[$table] = ($tableCounts[$table] ?? 0) + 1;

    $parts = explode('_', $table);
    $base = '';

    foreach ($parts as $part) {
        if ($part !== '') {
            $base .= strtolower($part[0]);
        }
    }

    if ($base === '') {
        throw new RuntimeException('Cannot generate alias for table: ' . $table);
    }

    return $tableCounts[$table] === 1
        ? $base
        : $base . $tableCounts[$table];
}