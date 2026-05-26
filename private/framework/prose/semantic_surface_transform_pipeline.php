<?php

declare(strict_types=1);

function semantic_surface_transform_pipeline_run(
    string $surface
): array {

    $surface = trim($surface);

    if ($surface === '') {
        return [];
    }

    $results = [];

    /*
     * Root surface.
     */
    $results[] = semantic_surface_transform_pipeline_build_result(
        $surface,
        'RAW_INPUT',
        null,
        [
            'RAW_INPUT',
        ]
    );

    /*
     * Lowercase normalization.
     */
    $lowercaseSurface = mb_strtolower($surface);

    if ($lowercaseSurface !== $surface) {

        $results[] =
            semantic_surface_transform_pipeline_build_result(
                $lowercaseSurface,
                'LOWERCASE_NORMALIZATION',
                $surface,
                [
                    'RAW_INPUT',
                    'LOWERCASE_NORMALIZATION',
                ]
            );
    }

    /*
     * Honorific stripping.
     */
    $honorificResult =
        semantic_surface_transform_pipeline_strip_honorific(
            $surface
        );

    if ($honorificResult !== null) {

        $results[] =
            semantic_surface_transform_pipeline_build_result(
                $honorificResult,
                'HONORIFIC_STRIP',
                $surface,
                [
                    'RAW_INPUT',
                    'HONORIFIC_STRIP',
                ]
            );

        /*
         * Honorific-stripped lowercase normalization.
         */
        $normalizedHonorificResult =
            mb_strtolower($honorificResult);

        if (
            $normalizedHonorificResult
            !== $honorificResult
        ) {

            $results[] =
                semantic_surface_transform_pipeline_build_result(
                    $normalizedHonorificResult,
                    'HONORIFIC_STRIP_LOWERCASE',
                    $honorificResult,
                    [
                        'RAW_INPUT',
                        'HONORIFIC_STRIP',
                        'LOWERCASE_NORMALIZATION',
                    ]
                );
        }

        /*
         * Surname extraction from honorific-stripped surface.
         */
        $surname =
            semantic_surface_transform_pipeline_extract_surname(
                $honorificResult
            );

        if (
            $surname !== null
            && $surname !== ''
            && $surname !== $honorificResult
        ) {

            $results[] =
                semantic_surface_transform_pipeline_build_result(
                    $surname,
                    'SURNAME_EXTRACTION',
                    $honorificResult,
                    [
                        'RAW_INPUT',
                        'HONORIFIC_STRIP',
                        'SURNAME_EXTRACTION',
                    ]
                );
        }
    }

    /*
     * Standalone surname extraction.
     */
    $directSurname =
        semantic_surface_transform_pipeline_extract_surname(
            $surface
        );

    if (
        $directSurname !== null
        && $directSurname !== ''
        && $directSurname !== $surface
    ) {

        $results[] =
            semantic_surface_transform_pipeline_build_result(
                $directSurname,
                'SURNAME_EXTRACTION',
                $surface,
                [
                    'RAW_INPUT',
                    'SURNAME_EXTRACTION',
                ]
            );
    }

    /*
     * Deduplicate transform surfaces while preserving
     * earliest transform lineage.
     */
    $deduplicated = [];

    foreach ($results as $result) {

        $key = mb_strtolower((string)(
            $result['surface'] ?? ''
        ));

        if ($key === '') {
            continue;
        }

        if (!isset($deduplicated[$key])) {
            $deduplicated[$key] = $result;
        }
    }

    return array_values($deduplicated);
}

function semantic_surface_transform_pipeline_build_result(
    string $surface,
    string $transformType,
    ?string $parentSurface,
    array $transformChain
): array {

    return [
        'surface' => trim($surface),
        'transform_type' => $transformType,
        'parent_surface' => $parentSurface,
        'transform_chain' => $transformChain,
    ];
}

function semantic_surface_transform_pipeline_strip_honorific(
    string $surface
): ?string {

    $surface = trim($surface);

    if ($surface === '') {
        return null;
    }

    $honorifics = [
        'mr',
        'mrs',
        'ms',
        'miss',
        'dr',
        'sir',
        'lady',
        'lord',
        'prof',
        'professor',
    ];

    foreach ($honorifics as $honorific) {

        $pattern =
            '/^'
            . preg_quote($honorific, '/')
            . '\.?\\s+/iu';

        $candidate = preg_replace(
            $pattern,
            '',
            $surface
        );

        if (
            is_string($candidate)
            && trim($candidate) !== ''
            && trim($candidate) !== $surface
        ) {
            return trim($candidate);
        }
    }

    return null;
}

function semantic_surface_transform_pipeline_extract_surname(
    string $surface
): ?string {

    $surface = trim($surface);

    if ($surface === '') {
        return null;
    }

    $tokens = preg_split(
        '/\\s+/u',
        $surface
    );

    if (!is_array($tokens)) {
        return null;
    }

    $tokens = array_values(array_filter(
        $tokens,
        static fn ($token) =>
            trim((string)$token) !== ''
    ));

    if (count($tokens) < 2) {
        return null;
    }

    return trim((string)(
        $tokens[count($tokens) - 1]
    ));
}