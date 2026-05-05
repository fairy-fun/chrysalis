<?php


function resolve_calendar_projection(PDO $pdo, int $projectionId): array
{
    if ($projectionId <= 0) {
        throw new InvalidArgumentException("Invalid projection id");
    }

    $sql = "
        SELECT
            ep.id AS projection_row_id,
            ep.calendar_event_id AS event_id,
            ep.calendar_projection_id,
            ep.chronology_address,
            ep.projection_sequence,
            ep.parent_projection_row_id,
            ep.render_label,
            ep.notes,

            e.parent_event_id,
            e.summary,
            e.event_type_id

        FROM calendar_event_projections ep
        JOIN calendar_events e
            ON e.id = ep.calendar_event_id

        WHERE ep.calendar_projection_id = :projection_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['projection_id' => $projectionId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Index nodes ---
    $nodes = [];
    foreach ($rows as $row) {
        $eventId = (int)$row['event_id'];

        $nodes[$eventId] = [
            'event_id' => $eventId,
            'chronology_address' => $row['chronology_address'],
            'label' => $row['render_label'] ?: $row['summary'],
            'parent_event_id' => $row['parent_event_id'] ? (int)$row['parent_event_id'] : null,
            'children' => [],
        ];
    }

    // --- Build tree ---
    $tree = [];

    foreach ($nodes as $eventId => &$node) {
        $parentId = $node['parent_event_id'];

        if ($parentId !== null) {
            if (isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$node;
            } else {
                // Orphan inside projection
                $node['orphaned_in_projection'] = true;
                $node['missing_parent_event_id'] = $parentId;
                $tree[] = &$node;
            }
        } else {
            $tree[] = &$node;
        }
    }
    unset($node);

    // --- Chronology sort helper ---
    $compareChronology = function ($a, $b) {
        $aParts = array_map('intval', explode('.', $a['chronology_address']));
        $bParts = array_map('intval', explode('.', $b['chronology_address']));

        $len = max(count($aParts), count($bParts));

        for ($i = 0; $i < $len; $i++) {
            $aVal = $aParts[$i] ?? 0;
            $bVal = $bParts[$i] ?? 0;

            if ($aVal < $bVal) return -1;
            if ($aVal > $bVal) return 1;
        }

        return 0;
    };

    // --- Recursive sort ---
    $sortTree = function (&$nodes) use (&$sortTree, $compareChronology) {
        usort($nodes, $compareChronology);

        foreach ($nodes as &$node) {
            if (!empty($node['children'])) {
                $sortTree($node['children']);
            }
        }
    };

    $sortTree($tree);

    return $tree;
}