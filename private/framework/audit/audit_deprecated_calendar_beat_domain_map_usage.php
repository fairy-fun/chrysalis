<?php


declare(strict_types=1);

function assert_deprecated_calendar_beat_domain_map_usage(): void
{
    $repoRoot = dirname(__DIR__, 3);

    $allowedPathFragments = [
        '/private/docs/',
        '/migrations/',
        '/database/migrations/',
    ];

    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($repoRoot)
    );

    foreach ($rii as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $extension = $file->getExtension();

        if (!in_array($extension, ['php', 'md', 'sql'], true)) {
            continue;
        }

        $path = $file->getRealPath();

        if ($path === false || $path === __FILE__) {
            continue;
        }

        $normalisedPath = str_replace('\\', '/', $path);

        $isAllowed = false;

        foreach ($allowedPathFragments as $fragment) {
            if (str_contains($normalisedPath, $fragment)) {
                $isAllowed = true;
                break;
            }
        }

        if ($isAllowed) {
            continue;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Failed to read file: {$path}");
        }

        if (str_contains($contents, 'calendar_beat_domain_map')) {
            throw new RuntimeException(
                "Deprecated calendar_beat_domain_map usage detected in {$path}; use domain → classset → (set_id, code) → beat_type_id"
            );
        }
    }
}