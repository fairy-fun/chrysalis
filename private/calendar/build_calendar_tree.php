<?php

declare(strict_types=1);

/**
 * Build a hierarchical calendar tree from flat rows.
 *
 * Required keys per row:
 * - id (int|string)
 * - parent_id (int|string|null)
 * - chronology_address (string)
 *
 * Optional:
 * - any additional fields will be preserved
 */
function build_calendar_tree(array $rows): array
{
    $nodes = [];

    // --- validation + indexing ---
    foreach ($rows as $row) {
        if (!isset($row['id'])) {
            throw new InvalidArgumentException('Row missing id');
        }

        if (!array_key_exists('parent_id', $row)) {
            throw new InvalidArgumentException('Row missing parent_id');
        }

        if (!isset($row['chronology_address'])) {
            throw new InvalidArgumentException('Row missing chronology_address');
        }

        $id = (string)$row['id'];

        assert_valid_chronology_address($row['chronology_address']);

        $row['children'] = [];

        $nodes[$id] = $row;
    }

    // --- build tree ---
    $tree = [];

    foreach ($nodes as $id => &$node) {
        $parentId = $node['parent_id'];

        if ($parentId !== null) {
            $parentId = (string)$parentId;

            if (isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$node;
            } else {
                // orphan handling (explicit, not silent)
                $node['orphaned_in_tree'] = true;
                $node['missing_parent_id'] = $parentId;
                $tree[] = &$node;
            }
        } else {
            $tree[] = &$node;
        }
    }
    unset($node);

    // --- sort ---
    $sortTree = function (array &$nodes) use (&$sortTree): void {
        usort($nodes, function ($a, $b) {
            return compare_chronology_address(
                $a['chronology_address'],
                $b['chronology_address']
            );
        });

        foreach ($nodes as &$node) {
            if (!empty($node['children'])) {
                $sortTree($node['children']);
            }
        }
    };

    $sortTree($tree);

    return $tree;
}

/**
 * Enforces the same contract as calendar_chronology_resolver
 */
function assert_valid_chronology_address(string $address): void
{
    if (!preg_match('/^[1-9][0-9]*(?:\\.[1-9][0-9]*)*$/', $address)) {
        throw new InvalidArgumentException(
            "Invalid chronology_address: {$address}"
        );
    }
}

/**
 * Shared comparison logic (consistent with resolver semantics)
 */
function compare_chronology_address(string $a, string $b): int
{
    $aParts = array_map('intval', explode('.', $a));
    $bParts = array_map('intval', explode('.', $b));

    $len = max(count($aParts), count($bParts));

    for ($i = 0; $i < $len; $i++) {
        $aVal = $aParts[$i] ?? 0;
        $bVal = $bParts[$i] ?? 0;

        if ($aVal < $bVal) return -1;
        if ($aVal > $bVal) return 1;
    }

    return 0;
}