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

        // ❌ Any PHP file that references calendar_events in write-like framework logic
        // must be part of the ensurer boundary. Read-only resolvers may still SELECT
        // from calendar_events, but calendar framework writes must not invent side paths.
        if (
            str_contains($contents, 'calendar_events') &&
            preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE)\b/i', $contents)
        ) {
            if (
                $path !== $allowedInsertFile &&
                !str_contains($contents, 'ensure_calendar_node')
            ) {
                throw new RuntimeException(
                    "Calendar table mutation must go through ensure_calendar_node in {$path}"
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

        // ❌ Block legacy index-based creator logic in calendar framework code.
        if (
            str_contains($path, '/private/framework/calendar/') &&
            preg_match('/\b(?:week_index|day_index|time_index|event_index|subevent_index)\b/', $contents)
        ) {
            throw new RuntimeException(
                "Legacy index-based calendar logic detected in {$path}"
            );
        }

        // ❌ Block old hierarchical creator naming outside deprecated shim comments/defs.
        if (preg_match('/under_.*(?:event|time|day)/i', $contents)) {
            throw new RuntimeException(
                "Hierarchical creator naming detected in {$path}; use ensure chain"
            );
        }
    }
}
