<?php

declare(strict_types=1);

function prose_character_find_surface_spans(
    string $proseBody,
    string $surfaceForm
): array {

    $surfaceForm = trim($surfaceForm);

    if ($surfaceForm === '') {
        return [];
    }

    $pattern =
        '/(?<![\\p{L}\\p{N}_])'
        . preg_quote($surfaceForm, '/')
        . '(?![\\p{L}\\p{N}_])/iu';

    if (
        preg_match_all(
            $pattern,
            $proseBody,
            $matches,
            PREG_OFFSET_CAPTURE
        ) !== false
        && isset($matches[0])
    ) {

        $spans = [];

        foreach ($matches[0] as $match) {
            $matchedText = (string)($match[0] ?? '');
            $offsetStart = (int)($match[1] ?? 0);

            $spans[] = [
                'matched_text' => $matchedText,
                'source_offset_start' => $offsetStart,
                'source_offset_end'
                    => $offsetStart + strlen($matchedText),
            ];
        }

        return $spans;
    }

    return [];
}
