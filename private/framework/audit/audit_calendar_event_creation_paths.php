<?php

declare(strict_types=1);

function assert_calendar_event_creation_paths(): void
{
    $repoRoot = dirname(__DIR__, 3);

    $allowedLayerEnsurerFile = realpath(
        $repoRoot . '/private/framework/calendar/calendar_layer_ensurers.php'
    );

    $allowedInsertFile = realpath(
        $repoRoot . '/private/framework/calendar/calendar_node_ensurer.php'
    );

    $allowedChronologyMaterializerFile = realpath(
        $repoRoot . '/private/framework/procedures/materialize_calendar_chronology.php'
    );

    $allowedProjectionMaterializerFile = realpath(
        $repoRoot . '/private/framework/calendar/calendar_projection_materializer.php'
    );

    $tierOneEventCreationPaths = [
        '/private/framework/calendar/calendar_event_creation_service.php',
        '/private/framework/calendar/calendar_book_event_ensurer.php',
        '/private/framework/procedures/workflow_calendar_event_create_driver.php',
    ];

    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($repoRoot)
    );

    foreach ($rii as $file) {
        if (!$file->isFile()) {
            continue;
        }

        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getRealPath();

        if ($path === __FILE__) {
            continue;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Failed to read file: {$path}");
        }

        // ❌ Block direct INSERT into calendar_events.
        if (
            preg_match('/\bINSERT\s+INTO\s+(?:sxnzlfun_chrysalis\.)?calendar_events\b/i', $contents)
        ) {
            if ($path !== $allowedInsertFile) {
                throw new RuntimeException(
                    "Illegal INSERT INTO calendar_events in {$path}"
                );
            }
        }

        // ❌ calendar_events writes must stay inside approved source-table boundaries.
        // This deliberately protects calendar_events only, not derived tables such as
        // calendar_event_projections.
        if (
            preg_match('/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+(?:sxnzlfun_chrysalis\.)?calendar_events\b/i', $contents)
        ) {
            if (
                $path !== $allowedInsertFile &&
                $path !== $allowedChronologyMaterializerFile &&
                !str_contains($contents, 'ensure_calendar_node')
            ) {
                throw new RuntimeException(
                    "Calendar source table mutation must go through ensure_calendar_node in {$path}"
                );
            }
        }

        // ❌ Projection/build rows are derived read-model state.
        // They must be written only by the projection materializer.
        if (
            preg_match('/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+(?:sxnzlfun_chrysalis\.)?(?:calendar_event_projections|calendar_projection_builds)\b/i', $contents)
        ) {
            if ($path !== $allowedProjectionMaterializerFile) {
                throw new RuntimeException(
                    "Calendar projection mutation must go through calendar_projection_materializer in {$path}"
                );
            }
        }

        // ❌ Block direct event-layer creation via primitive outside approved boundaries.
        if (
            str_contains($contents, 'ensure_calendar_node') &&
            str_contains($contents, 'calendar_layer_event')
        ) {
            if (
                $path !== $allowedLayerEnsurerFile &&
                $path !== $allowedInsertFile
            ) {
                throw new RuntimeException(
                    "Illegal event-layer creation via ensure_calendar_node in {$path}"
                );
            }
        }

        // ❌ Block deprecated creator usage outside the compatibility shim itself.
        if (
            str_contains($contents, 'create_calendar_event_under_')
        ) {
            throw new RuntimeException(
                "Forbidden legacy calendar event creator usage in {$path}"
            );
        }

        // ❌ Block deprecated generic creator usage.
        if (str_contains($contents, 'create_calendar_event(')) {
            throw new RuntimeException(
                "Deprecated create_calendar_event() usage in {$path}"
            );
        }

        $isTierOneEventCreationPath = false;

        foreach ($tierOneEventCreationPaths as $tierOnePath) {
            if (str_contains($path, $tierOnePath)) {
                $isTierOneEventCreationPath = true;
                break;
            }
        }

        // ❌ Block legacy chronology-container locality persistence in Tier 1 event creation.
        // Canonical Tier 1 event locality must resolve to calendar_book_times.id and then
        // persist through calendar_events.book_time_id + event_index only.
        //
        // Forbidden in Tier 1:
        // - using week/day/time indexes as calendar_events locality
        // - creating or inferring chronology containers during event placement
        // - recursive chronology reconstruction
        //
        // Allowed outside Tier 1:
        // - explicit chronology authoring/bootstrap operations
        // - administrative creation of calendar_book_weeks/calendar_book_days/calendar_book_times
        if (
            $isTierOneEventCreationPath &&
            preg_match(
                '/\b(?:week_index|day_index|time_index)\b/',
                $contents
            ) &&
            !str_contains(
                $contents,
                'calendar_book_times'
            ) &&
            !str_contains(
                $contents,
                'resolve_calendar_book_time_id_from_legacy_time_node'
            )
        ) {
            throw new RuntimeException(
                "Legacy chronology-container locality logic detected in Tier 1 event creation path {$path}"
            );
        }

        // ❌ Block old hierarchical creator naming outside deprecated shim comments/defs.
        if (preg_match('/under_.*(?:event|time|day)/i', $contents)) {
            throw new RuntimeException(
                "Hierarchical creator naming detected in {$path}; use ensure chain"
            );
        }

        if (preg_match('/\bWHERE\s+chronology_address\s*=/i', $contents)) {
            throw new RuntimeException("Forbidden chronology_address lookup in {$path}");
        }

        if (preg_match('/\bUPDATE\s+(?:sxnzlfun_chrysalis\.)?calendar_events\s+SET\s+chronology_address\b/i', $contents)) {
            throw new RuntimeException("Forbidden chronology_address mutation in {$path}");
        }

        if (
            str_contains($path, '/public_html/') &&
            preg_match('/\bcalendar_events\.id\b|\bce\.id\b/i', $contents)
        ) {
            throw new RuntimeException("Public API exposes calendar_events.id in {$path}");
        }
    }
}
