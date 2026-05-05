<?php

require_once __DIR__ . '/../framework/calendar/calendar_chronology_resolver.php';

/**
 * Build a validated calendar tree from flat projection rows.
 *
 * Enforces:
 * - parent presence
 * - no duplicate IDs
 * - no duplicate chronology_address
 * - valid chronology format
 * - numeric chronology ordering (not lexicographic)
 * - parent chronology prefix rule
 * - depth consistency (segments === depth)
 *
 * @param array $rows
 * @return array
 * @throws Exception
 */
function build_calendar_tree(array $rows): array
{
    $nodes = [];
    $childrenMap = [];
    $chronologyIndex = [];

    // --- Helpers -------------------------------------------------------------

    $parseChronology = function (string $addr): array {
        if (!preg_match('/^[1-9][0-9]*(?:\.[1-9][0-9]*)*$/', $addr)) {
            throw new Exception("Invalid chronology_address format: {$addr}");
        }

        return array_map('intval', explode('.', $addr));
    };

    $isPrefix = function (array $parent, array $child): bool {
        if (count($parent) >= count($child)) {
            return false;
        }

        for ($i = 0; $i < count($parent); $i++) {
            if ($parent[$i] !== $child[$i]) {
                return false;
            }
        }

        return true;
    };

    $compareChronology = function (array $a, array $b): int {
        $len = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            if ($a[$i] !== $b[$i]) {
                return $a[$i] <=> $b[$i];
            }
        }

        return count($a) <=> count($b);
    };

    // --- First pass: normalize + validate uniqueness -------------------------

    foreach ($rows as $row) {
        if (!isset($row['id'], $row['chronology_address'])) {
            throw new Exception("Row missing required fields");
        }

        $id = (string)$row['id'];
        $parentId = isset($row['parent_id']) ? (string)$row['parent_id'] : null;
        $addr = (string)$row['chronology_address'];

        if (isset($nodes[$id])) {
            throw new Exception("Duplicate node id: {$id}");
        }

        if (isset($chronologyIndex[$addr])) {
            throw new Exception("Duplicate chronology_address: {$addr}");
        }

        $segments = $parseChronology($addr);

        $nodes[$id] = [
            'id' => $id,
            'parent_id' => $parentId,
            'chronology_address' => $addr,
            'segments' => $segments,
            'children' => [],
        ];

        $chronologyIndex[$addr] = $id;
    }

    // --- Second pass: parent presence + structural checks --------------------

    foreach ($nodes as $id => &$node) {
        $parentId = $node['parent_id'];

        if ($parentId !== null) {
            if (!isset($nodes[$parentId])) {
                throw new Exception("Missing parent {$parentId} for node {$id}");
            }

            $parent = $nodes[$parentId];

            // Prefix rule
            if (!$isPrefix($parent['segments'], $node['segments'])) {
                throw new Exception(
                    "Chronology prefix violation: parent {$parent['chronology_address']} "
                    . "is not prefix of {$node['chronology_address']}"
                );
            }

            // Depth consistency
            $expectedDepth = count($parent['segments']) + 1;
            if (count($node['segments']) !== $expectedDepth) {
                throw new Exception(
                    "Depth mismatch for node {$id}: expected {$expectedDepth}, got "
                    . count($node['segments'])
                );
            }

            $childrenMap[$parentId][] = $id;
        } else {
            // Root depth must be 1
            if (count($node['segments']) !== 1) {
                throw new Exception(
                    "Root node {$id} must have depth 1 chronology, got {$node['chronology_address']}"
                );
            }
        }
    }
    unset($node);

    // --- Attach children -----------------------------------------------------

    foreach ($childrenMap as $parentId => $childIds) {
        $nodes[$parentId]['children'] = $childIds;
    }

    // --- Sort children by numeric chronology --------------------------------

    foreach ($nodes as &$node) {
        if (!empty($node['children'])) {
            usort($node['children'], function ($a, $b) use ($nodes, $compareChronology) {
                return $compareChronology(
                    $nodes[$a]['segments'],
                    $nodes[$b]['segments']
                );
            });
        }
    }
    unset($node);

    // --- Build nested structure ---------------------------------------------

    $buildSubtree = function ($id) use (&$buildSubtree, &$nodes): array {
        $node = $nodes[$id];

        $result = [
            'id' => $node['id'],
            'parent_id' => $node['parent_id'],
            'chronology_address' => $node['chronology_address'],
            'children' => [],
        ];

        foreach ($node['children'] as $childId) {
            $result['children'][] = $buildSubtree($childId);
        }

        return $result;
    };

    // --- Collect roots -------------------------------------------------------

    $roots = [];

    foreach ($nodes as $id => $node) {
        if ($node['parent_id'] === null) {
            $roots[] = $id;
        }
    }

    // Sort roots too
    usort($roots, function ($a, $b) use ($nodes, $compareChronology) {
        return $compareChronology(
            $nodes[$a]['segments'],
            $nodes[$b]['segments']
        );
    });

    // --- Final tree ----------------------------------------------------------

    $tree = [];

    foreach ($roots as $rootId) {
        $tree[] = $buildSubtree($rootId);
    }

    return $tree;
}