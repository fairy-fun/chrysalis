<?php
declare(strict_types=1);

/**
 * Framework-level prose metadata derivation.
 *
 * Authorial input is prose_body.
 * Title, summary, beat summary, projection recommendations,
 * and export recommendations are derived structural metadata.
 *
 * User-provided title/summary values are editorial overrides,
 * not required creative payload.
 *
 * Important boundary:
 * - extractive metadata may be produced deterministically here;
 * - semantic metadata must be provided by an explicit caller/corpus hook;
 * - beat_type_id is canonical ontology linkage, not semantic text;
 * - beat_code is a semantic hint that must be resolved through workflow/runtime
 *   authority before calendar_events.beat_type_id is mutated;
 * - framework code must not contain corpus names, event IDs, fixtures,
 *   character names, or publication/export topology assumptions.
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

function prose_normalized_body(string $proseBody): string
{
    return trim(preg_replace('/\s+/', ' ', $proseBody) ?? '');
}

function prose_truncated_summary(
    string $proseBody,
    int $maxLength = 240
): ?string {

    $normalized = prose_normalized_body($proseBody);

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

function prose_contains_all(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (!is_string($needle) || $needle === '') {
            continue;
        }

        if (mb_stripos($haystack, $needle) === false) {
            return false;
        }
    }

    return true;
}

function prose_contains_any(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (!is_string($needle) || $needle === '') {
            continue;
        }

        if (mb_stripos($haystack, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function derive_prose_semantic_metadata(
    string $proseBody,
    array $context = []
): ?array {

    $deriver = $context['semantic_metadata_deriver'] ?? null;

    if (!is_callable($deriver)) {
        return null;
    }

    $semantic = $deriver($proseBody, $context);

    if (!is_array($semantic)) {
        return null;
    }

    $title = trim((string)($semantic['title'] ?? ''));
    $beatSummary = trim((string)($semantic['beat_summary'] ?? ''));

    if ($title === '' || $beatSummary === '') {
        return null;
    }

    return $semantic;
}

function derive_prose_metadata(
    string $proseBody,
    array $context = []
): array {

    $semantic = derive_prose_semantic_metadata($proseBody, $context);

    if (is_array($semantic)) {
        return [
            'title' => $semantic['title'],
            'summary' => prose_truncated_summary($proseBody),
            'beat_summary' => $semantic['beat_summary'],
            'beat_type_id' => $semantic['beat_type_id'] ?? null,
            'beat_code' => $semantic['beat_code'] ?? null,
            'derivation_mode' => $semantic['derivation_mode'] ?? 'semantic_rule',
            'evidence' => $semantic['evidence'] ?? [],
            'projection_recommendations' => [],
            'export_recommendations' => [],
        ];
    }

    $suggestedTitle = prose_first_meaningful_line($proseBody);

    if ($suggestedTitle !== null) {
        $suggestedTitle = mb_substr($suggestedTitle, 0, 120);
    }

    return [
        'title' => null,
        'extractive_title_candidate' => $suggestedTitle,
        'summary' => prose_truncated_summary($proseBody),
        'beat_summary' => null,
        'beat_type_id' => null,
        'beat_code' => null,
        'derivation_mode' => 'extractive_only',
        'evidence' => [],
        'projection_recommendations' => [],
        'export_recommendations' => [],
    ];
}
