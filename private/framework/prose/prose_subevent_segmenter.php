<?php

declare(strict_types=1);

require_once __DIR__ . '/prose_metadata_deriver.php';

function segment_prose_into_subevents(
    string $prose
): array {

    $normalized = normalize_subevent_prose($prose);

    if ($normalized === '') {
        return [];
    }

    $blocks = preg_split("/\n{2,}/", $normalized);

    if (!is_array($blocks)) {
        return [];
    }

    $blocks = array_values(
        array_filter(
            array_map(
                static fn (string $block): string => trim($block),
                $blocks
            ),
            static fn (string $block): bool => $block !== ''
        )
    );

    $merged = [];

    foreach ($blocks as $block) {

        $words = preg_split('/\s+/', trim($block));
        $wordCount = is_array($words) ? count($words) : 0;

        if ($wordCount < 40 && !empty($merged)) {
            $last = count($merged) - 1;
            $merged[$last] .= "\n\n" . $block;
            continue;
        }

        $merged[] = $block;
    }

    $subevents = [];
    $slot = 1;

    foreach ($merged as $block) {

        $metadata = derive_prose_metadata($block);

        $summary = trim(
            (string)($metadata['summary'] ?? '')
        );

        if ($summary === '') {
            $summary = 'Subevent ' . $slot;
        }

        $subevents[] = [
            'slot' => $slot,
            'summary' => $summary,
            'prose_body' => $block,
        ];

        $slot++;
    }

    return $subevents;
}

function normalize_subevent_prose(
    string $prose
): string {

    $prose = str_replace(
        ["\r\n", "\r"],
        "\n",
        $prose
    );

    $prose = preg_replace(
        "/[ \t]+/",
        ' ',
        $prose
    ) ?? '';

    $prose = preg_replace(
        "/\n{3,}/",
        "\n\n",
        $prose
    ) ?? '';

    return trim($prose);
}