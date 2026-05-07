<?php
declare(strict_types=1);

/**
 * Prose metadata derivation.
 *
 * Authorial input is prose_body.
 * Title, summary, beat summary, projection recommendations,
 * and export recommendations are derived structural metadata.
 *
 * User-provided title/summary values are editorial overrides,
 * not required creative payload.
 */

function prose_first_meaningful_line(string $proseBody): ?string
{
    $lines = preg_split('/\r\n|\r|\n/', $proseBody);

    if (!is_array($lines)) {
        return null;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed !== '') {
            return $trimmed;
        }
    }

    return null;
}

function prose_truncated_summary(
    string $proseBody,
    int $maxLength = 240
): ?string {

    $normalized = trim(
        preg_replace('/\s+/', ' ', $proseBody) ?? ''
    );

    if ($normalized === '') {
        return null;
    }

    if (mb_strlen($normalized) <= $maxLength) {
        return $normalized;
    }

    return rtrim(
            mb_substr($normalized, 0, $maxLength - 1)
        ) . '…';
}

function derive_prose_metadata(
    string $proseBody,
    array $context = []
): array {

    $suggestedTitle = prose_first_meaningful_line($proseBody);

    if ($suggestedTitle !== null) {
        $suggestedTitle = mb_substr($suggestedTitle, 0, 120);
    }

    return [
        'title' => $suggestedTitle,
        'summary' => prose_truncated_summary($proseBody),
        'beat_summary' => null,
        'projection_recommendations' => [],
        'export_recommendations' => [],
    ];
}