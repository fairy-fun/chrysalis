<?php

declare(strict_types=1);

function assert_calendar_event_creation_paths(): void
{
    $repoRoot = dirname(__DIR__, 3);

    $allowedEnsurerFile = realpath(
        $repoRoot . '/private/framework/calendar/calendar_event_semantic_creator.php'
    );

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

        // ❌ Block direct INSERT into calendar_events
        if (
            preg_match('/\bINSERT\s+INTO\s+(?:sxnzlfun_chrysalis\.)?calendar_events\b/i', $contents)
        ) {
            if ($path !== $allowedInsertFile) {
                throw new RuntimeException(
                    "Illegal INSERT INTO calendar_events in {$path}"
                );
            }
        }

        // ❌ Block direct event-layer creation via ensurer
        if (
            str_contains($contents, 'ensure_calendar_node') &&
            str_contains($contents, 'calendar_layer_event')
        ) {
            if (
                $path !== $allowedEnsurerFile &&
                $path !== $allowedLayerEnsurerFile &&
                $path !== $allowedInsertFile
            ) {
                throw new RuntimeException(
                    "Illegal event-layer creation via ensure_calendar_node in {$path}"
                );
            }
        }

        // ❌ Block deprecated creator usage
        if (
            str_contains($contents, 'create_calendar_event(') &&
            !str_contains($path, 'calendar_event_creator.php')
        ) {
            throw new RuntimeException(
                "Deprecated create_calendar_event() usage in {$path}"
            );
        }
    }
}
